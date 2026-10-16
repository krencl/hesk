<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Exception\DomainException;
use App\Dto\PlayerNameRequest;
use App\Entity\Party;
use App\Entity\Player;
use App\Security\PartyVoter;
use App\Service\Presenter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Hráči party: členové s účtem i hosté, které do her zapisujeme. */
final class PlayerController extends ApiController
{
    public function __construct(Presenter $presenter, private readonly EntityManagerInterface $em)
    {
        parent::__construct($presenter);
    }

    #[Route('/api/parties/{id}/players', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(PartyVoter::VIEW, 'party')]
    public function list(Party $party): JsonResponse
    {
        $players = $party->getPlayers()->toArray();
        usort($players, static fn (Player $a, Player $b): int => strcasecmp($a->getName(), $b->getName()));

        return new JsonResponse(array_map($this->presenter->player(...), $players));
    }

    #[Route('/api/parties/{id}/players', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(PartyVoter::VIEW, 'party')]
    public function createGuest(Party $party, #[MapRequestPayload] PlayerNameRequest $req): JsonResponse
    {
        $player = $party->addGuest($req->name);
        $this->em->flush();

        return new JsonResponse($this->presenter->player($player), Response::HTTP_CREATED);
    }

    #[Route('/api/players/{id}', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function rename(Player $player, #[MapRequestPayload] PlayerNameRequest $req): JsonResponse
    {
        $this->denyAccessUnlessGranted(PartyVoter::VIEW, $player->getParty());
        foreach ($player->getParty()->getPlayers() as $other) {
            if ($other !== $player && 0 === strcasecmp($other->getName(), trim($req->name))) {
                throw new DomainException('Hráč s tímto jménem už v partě existuje.');
            }
        }
        $player->rename(trim($req->name));
        $this->em->flush();

        return new JsonResponse($this->presenter->player($player));
    }
}
