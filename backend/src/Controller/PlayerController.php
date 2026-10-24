<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Exception\DomainException;
use App\Dto\LinkMemberRequest;
use App\Dto\PlayerNameRequest;
use App\Dto\UpdatePlayerRequest;
use App\Entity\Party;
use App\Entity\Player;
use App\Repository\PartyGameRepository;
use App\Repository\PlayRepository;
use App\Security\PartyVoter;
use App\Service\GuestMerger;
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
    public function __construct(Presenter $presenter, private readonly EntityManagerInterface $em, private readonly PlayRepository $plays, private readonly PartyGameRepository $partyGames, private readonly GuestMerger $merger)
    {
        parent::__construct($presenter);
    }

    #[Route('/api/parties/{id}/players', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(PartyVoter::VIEW, 'party')]
    public function list(Party $party): JsonResponse
    {
        $players = $party->getPlayers()->toArray();
        usort($players, static fn (Player $a, Player $b): int => strcasecmp($a->getName(), $b->getName()));

        $counts = $this->plays->playCountsByPlayer($party);
        $gamePlayers = array_flip($this->partyGames->gamePlayerIds($party));

        return new JsonResponse(array_map(fn (Player $p): array => $this->presenter->player($p) + ['playCount' => $counts[$p->getId()] ?? 0, 'gamePlayer' => isset($gamePlayers[$p->getId()])], $players));
    }

    #[Route('/api/parties/{id}/players', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(PartyVoter::WRITE, 'party')]
    public function createGuest(Party $party, #[MapRequestPayload] PlayerNameRequest $req): JsonResponse
    {
        $player = $party->addGuest($req->name);
        $this->em->flush();

        return new JsonResponse($this->presenter->player($player), Response::HTTP_CREATED);
    }

    #[Route('/api/players/{id}', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function update(Player $player, #[MapRequestPayload] UpdatePlayerRequest $req): JsonResponse
    {
        $this->denyAccessUnlessGranted(PartyVoter::WRITE, $player->getParty());
        $player->getParty()->updateGuest($player, $req->name, $req->visible);
        $this->em->flush();

        return new JsonResponse($this->presenter->player($player));
    }

    /** Smazat jde jen host, který nikde nehrál a není hráčem žádné hry (jinak by zápisy přišly o hráče). */
    #[Route('/api/players/{id}', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(Player $player): Response
    {
        $this->denyAccessUnlessGranted(PartyVoter::WRITE, $player->getParty());
        if (null !== $player->getUser()) {
            throw new DomainException('Člena s účtem nelze smazat, nejdřív ho odeber z party.');
        }
        $played = $this->plays->countForPlayer($player);
        if ($played > 0) {
            throw new DomainException(sprintf('Host už hrál v %d zápisech, smazat ho nejde.', $played));
        }
        if ($link = $this->partyGames->findByPlayer($player, 1)[0] ?? null) {
            throw new DomainException(sprintf('Host je hráčem hry %s, smazat ho nejde.', $link->getGame()->getName()));
        }
        $this->merger->forgetInvitations($player);
        $this->em->remove($player);
        $this->em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /** Admin propojí hosta s existujícím členem: zápisy hosta přejdou na hráče člena a host zmizí. */
    #[Route('/api/players/{id}/link-member', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function linkMember(Player $player, #[MapRequestPayload] LinkMemberRequest $req): JsonResponse
    {
        $party = $player->getParty();
        $this->denyAccessUnlessGranted(PartyVoter::ADMIN, $party);
        $user = $party->findMemberByUserId($req->userId)?->getUser();
        $target = $user ? $party->findPlayerFor($user) : null;
        if (!$user || !$target) {
            throw new DomainException('Uživatel není členem této party.');
        }
        $this->merger->mergeGuestIntoMember($player, $target);
        $counts = $this->plays->playCountsByPlayer($party);

        return new JsonResponse($this->presenter->player($target) + ['playCount' => $counts[$target->getId()] ?? 0]);
    }
}
