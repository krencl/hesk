<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Exception\DomainException;
use App\Dto\GameLinkRequest;
use App\Dto\GamePlayersRequest;
use App\Dto\GameRequest;
use App\Dto\NamedItemRequest;
use App\Entity\Game;
use App\Entity\Party;
use App\Entity\PartyGame;
use App\Entity\User;
use App\Repository\GameRepository;
use App\Repository\PlayRepository;
use App\Security\PartyVoter;
use App\Service\CoverStorage;
use App\Service\Presenter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Sdílený katalog her (ADR 0023): hru může upravit a smazat jen správce her (`ROLE_GAMES_ADMIN`), zařadit ji do party nebo
 * ji odebrat smí člen party. Vlastní hráči hry patří partě.
 */
final class GameController extends ApiController
{
    public function __construct(Presenter $presenter, private readonly EntityManagerInterface $em, private readonly PlayRepository $plays, private readonly GameRepository $games, private readonly CoverStorage $covers)
    {
        parent::__construct($presenter);
    }

    #[Route('/api/parties/{id}/games', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(PartyVoter::VIEW, 'party')]
    public function list(Party $party, Request $request): JsonResponse
    {
        $sort = GameRepository::SORT_NAME === $request->query->get('sort') ? GameRepository::SORT_NAME : GameRepository::SORT_ACTIVITY;
        $limit = $request->query->has('limit') ? max(1, min(100, $request->query->getInt('limit'))) : null;
        $sessionNumber = $request->query->has('sessionNumber') && '' !== $request->query->get('sessionNumber') ? $request->query->getInt('sessionNumber') : null;
        $games = $this->games->search($party, $request->query->get('q'), $sort, $sessionNumber, $limit, max(0, $request->query->getInt('offset')));
        $stats = $this->games->statsFor($party, $games);

        return new JsonResponse(array_map(
            fn (Game $g): array => $this->presenter->gameWithStats($g, $party->findGameLink($g), $stats[$g->getId()]['lastPlay'], $stats[$g->getId()]['playCount']),
            $games,
        ));
    }

    /** Založí novou hru v katalogu a rovnou ji zařadí do party. Duplicitu (stejné `bgg_id`, u vlastní hry stejný název) odmítne. */
    #[Route('/api/parties/{id}/games', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(PartyVoter::VIEW, 'party')]
    public function create(Party $party, #[MapRequestPayload] GameRequest $req): JsonResponse
    {
        $this->assertNoDuplicate(null, $req);
        $game = new Game(trim($req->name), $req->scoreType);
        $this->apply($game, $req);
        $link = $party->addGame($game);
        $link->syncPlayers($this->items($req->players));
        $this->em->persist($game);
        $this->em->flush();

        return new JsonResponse($this->presenter->game($game, $link), Response::HTTP_CREATED);
    }

    /** Zařadí hru z katalogu do party (idempotentní: je-li už v partě, vrátí ji). */
    #[Route('/api/parties/{id}/game-links', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(PartyVoter::VIEW, 'party')]
    public function link(Party $party, #[MapRequestPayload] GameLinkRequest $req): JsonResponse
    {
        $game = $this->em->find(Game::class, $req->gameId) ?? throw new NotFoundHttpException('Hra neexistuje.');
        $existing = $party->hasGame($game);
        $link = $party->addGame($game);
        if (!$existing) {
            $this->em->flush();
        }

        return new JsonResponse($this->presenter->game($game, $link), $existing ? Response::HTTP_OK : Response::HTTP_CREATED);
    }

    /** Odebere hru z party; hra s odehranými zápisy v partě se odebrat nedá. Hra zůstává v katalogu. */
    #[Route('/api/parties/{id}/games/{gameId}', requirements: ['id' => '\d+', 'gameId' => '\d+'], methods: ['DELETE'])]
    #[IsGranted(PartyVoter::VIEW, 'party')]
    public function unlink(Party $party, #[MapEntity(id: 'gameId')] Game $game): Response
    {
        $this->linkOrFail($party, $game);
        if ($this->plays->countForGame($game, $party) > 0) {
            throw new DomainException('Hru s odehranými zápisy nelze z party odebrat.');
        }
        $party->removeGame($game);
        $this->em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/parties/{id}/games/{gameId}', requirements: ['id' => '\d+', 'gameId' => '\d+'], methods: ['GET'])]
    #[IsGranted(PartyVoter::VIEW, 'party')]
    public function show(Party $party, #[MapEntity(id: 'gameId')] Game $game): JsonResponse
    {
        $link = $this->linkOrFail($party, $game);
        $stats = $this->games->statsFor($party, [$game])[$game->getId()];

        return new JsonResponse($this->presenter->gameWithStats($game, $link, $stats['lastPlay'], $stats['playCount']));
    }

    /** Vlastní hráči hry v této partě. */
    #[Route('/api/parties/{id}/games/{gameId}/players', requirements: ['id' => '\d+', 'gameId' => '\d+'], methods: ['PUT'])]
    #[IsGranted(PartyVoter::VIEW, 'party')]
    public function setPlayers(Party $party, #[MapEntity(id: 'gameId')] Game $game, #[MapRequestPayload] GamePlayersRequest $req): JsonResponse
    {
        $link = $this->linkOrFail($party, $game);
        $link->syncPlayers($this->items($req->players));
        $this->em->flush();

        return new JsonResponse($this->presenter->game($game, $link));
    }

    /** Nahraje obrázek hry (už oříznutý na čtverec v prohlížeči); vrací adresu pro `imageUrl`. */
    #[Route('/api/parties/{id}/game-images', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(PartyVoter::VIEW, 'party')]
    public function uploadImage(Party $party, Request $request): JsonResponse
    {
        return new JsonResponse(['url' => $this->covers->store($request->files->get('file'))], Response::HTTP_CREATED);
    }

    /** Úprava sdílených údajů hry (jen správce her); `players` z požadavku se ignorují, patří partě. */
    #[Route('/api/games/{id}', requirements: ['id' => '\d+'], methods: ['PUT'])]
    #[IsGranted(User::ROLE_GAMES_ADMIN)]
    public function update(Game $game, #[MapRequestPayload] GameRequest $req): JsonResponse
    {
        if ($req->scoreType !== $game->getScoreType() && $this->plays->countForGame($game) > 0) {
            throw new DomainException('Typ bodování nelze změnit u hry s odehranými zápisy.');
        }
        $this->assertNoDuplicate($game, $req);
        $this->apply($game, $req);
        $this->em->flush();

        return new JsonResponse($this->presenter->game($game));
    }

    /** Smazání hry z katalogu (jen správce her); hra s odehranými zápisy v jakékoli partě se smazat nedá. */
    #[Route('/api/games/{id}', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[IsGranted(User::ROLE_GAMES_ADMIN)]
    public function delete(Game $game): Response
    {
        if ($this->plays->countForGame($game) > 0) {
            throw new DomainException('Hru s odehranými zápisy nelze smazat.');
        }
        $this->em->remove($game);
        $this->em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function linkOrFail(Party $party, Game $game): PartyGame
    {
        return $party->findGameLink($game) ?? throw new NotFoundHttpException('Hra není v této partě.');
    }

    /** V katalogu nesmí vzniknout duplicita: hra s `bgg_id` je jen jednou, vlastní hra (bez `bgg_id`) se nesmí jmenovat jako jiná. */
    private function assertNoDuplicate(?Game $self, GameRequest $req): void
    {
        $duplicate = $this->games->findDuplicate($req->bggId, trim($req->name), $self);
        if (null !== $duplicate) {
            throw new DomainException(null !== $req->bggId
                ? sprintf('Hra s tímto BoardGameGeek ID už v databázi je („%s“), přidej ji do party z vyhledávání.', $duplicate->getName())
                : sprintf('Hra „%s“ už v databázi je, přidej ji do party z vyhledávání.', $duplicate->getName()));
        }
    }

    /**
     * @param list<NamedItemRequest> $list
     *
     * @return list<array{id: ?int, name: string}>
     */
    private function items(array $list): array
    {
        return array_map(static fn (NamedItemRequest $i): array => ['id' => $i->id, 'name' => $i->name], $list);
    }

    /** Přenese sdílené údaje z požadavku na hru. */
    private function apply(Game $game, GameRequest $req): void
    {
        $game->update(trim($req->name), $req->shortName, $req->scoreType, $req->imageUrl, $req->description, $req->cheatSheet, $this->items($req->addons), $this->items($req->characters), $req->bggId);
    }
}
