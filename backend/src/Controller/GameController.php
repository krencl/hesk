<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Exception\DomainException;
use App\Dto\GameRequest;
use App\Entity\Game;
use App\Entity\Party;
use App\Repository\PlayRepository;
use App\Security\PartyVoter;
use App\Service\Presenter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class GameController extends ApiController
{
    public function __construct(Presenter $presenter, private readonly EntityManagerInterface $em, private readonly PlayRepository $plays)
    {
        parent::__construct($presenter);
    }

    #[Route('/api/parties/{id}/games', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(PartyVoter::VIEW, 'party')]
    public function list(Party $party): JsonResponse
    {
        $games = $this->em->getRepository(Game::class)->findBy(['party' => $party], ['name' => 'ASC']);

        return new JsonResponse(array_map($this->presenter->game(...), $games));
    }

    #[Route('/api/parties/{id}/games', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(PartyVoter::VIEW, 'party')]
    public function create(Party $party, #[MapRequestPayload] GameRequest $req): JsonResponse
    {
        $game = new Game($party, trim($req->name), $req->scoreType);
        $game->update(trim($req->name), $req->scoreType, $req->imageUrl, $req->description, $req->cheatSheet, $req->addons);
        $this->em->persist($game);
        $this->em->flush();

        return new JsonResponse($this->presenter->game($game), Response::HTTP_CREATED);
    }

    #[Route('/api/games/{id}', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Game $game): JsonResponse
    {
        $this->denyAccessUnlessGranted(PartyVoter::VIEW, $game->getParty());

        return new JsonResponse($this->presenter->game($game));
    }

    #[Route('/api/games/{id}', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function update(Game $game, #[MapRequestPayload] GameRequest $req): JsonResponse
    {
        $this->denyAccessUnlessGranted(PartyVoter::VIEW, $game->getParty());
        if ($req->scoreType !== $game->getScoreType() && $this->plays->countForGame($game) > 0) {
            throw new DomainException('Typ bodování nelze změnit u hry s odehranými zápisy.');
        }
        $game->update(trim($req->name), $req->scoreType, $req->imageUrl, $req->description, $req->cheatSheet, $req->addons);
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
}
