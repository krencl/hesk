<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Exception\DomainException;
use App\Dto\GameRequest;
use App\Dto\NamedItemRequest;
use App\Entity\Game;
use App\Entity\Party;
use App\Repository\GameRepository;
use App\Repository\PlayRepository;
use App\Security\PartyVoter;
use App\Service\Presenter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class GameController extends ApiController
{
    public function __construct(Presenter $presenter, private readonly EntityManagerInterface $em, private readonly PlayRepository $plays, private readonly GameRepository $games)
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
        $stats = $this->games->statsFor($games);

        return new JsonResponse(array_map(
            fn (Game $g): array => $this->presenter->gameWithStats($g, $stats[$g->getId()]['lastPlay'], $stats[$g->getId()]['playCount']),
            $games,
        ));
    }

    #[Route('/api/parties/{id}/games', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(PartyVoter::VIEW, 'party')]
    public function create(Party $party, #[MapRequestPayload] GameRequest $req): JsonResponse
    {
        $game = new Game($party, trim($req->name), $req->scoreType);
        $this->apply($game, $req);
        $this->em->persist($game);
        $this->em->flush();

        return new JsonResponse($this->presenter->game($game), Response::HTTP_CREATED);
    }

    #[Route('/api/games/{id}', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Game $game): JsonResponse
    {
        $this->denyAccessUnlessGranted(PartyVoter::VIEW, $game->getParty());
        $stats = $this->games->statsFor([$game])[$game->getId()];

        return new JsonResponse($this->presenter->gameWithStats($game, $stats['lastPlay'], $stats['playCount']));
    }

    #[Route('/api/games/{id}', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function update(Game $game, #[MapRequestPayload] GameRequest $req): JsonResponse
    {
        $this->denyAccessUnlessGranted(PartyVoter::VIEW, $game->getParty());
        if ($req->scoreType !== $game->getScoreType() && $this->plays->countForGame($game) > 0) {
            throw new DomainException('Typ bodování nelze změnit u hry s odehranými zápisy.');
        }
        $this->apply($game, $req);
        $this->em->flush();

        return new JsonResponse($this->presenter->game($game));
    }

    #[Route('/api/games/{id}', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(Game $game): Response
    {
        $this->denyAccessUnlessGranted(PartyVoter::VIEW, $game->getParty());
        if ($this->plays->countForGame($game) > 0) {
            throw new DomainException('Hru s odehranými zápisy nelze smazat.');
        }
        $this->em->remove($game);
        $this->em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /** Přenese údaje z požadavku na hru; hráči hry se řeší v entitě (musí patřit do stejné party). */
    private function apply(Game $game, GameRequest $req): void
    {
        $items = static fn (array $list): array => array_map(static fn (NamedItemRequest $i): array => ['id' => $i->id, 'name' => $i->name], $list);
        $game->update(trim($req->name), $req->shortName, $req->scoreType, $req->imageUrl, $req->description, $req->cheatSheet, $items($req->addons), $items($req->characters), $items($req->players));
    }
}
