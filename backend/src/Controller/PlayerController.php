<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Exception\DomainException;
use App\Dto\LinkMemberRequest;
use App\Dto\PlayerNameRequest;
use App\Dto\UpdatePlayerRequest;
use App\Entity\Game;
use App\Entity\Party;
use App\Entity\Player;
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
    public function __construct(Presenter $presenter, private readonly EntityManagerInterface $em, private readonly PlayRepository $plays, private readonly GuestMerger $merger)
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

        return new JsonResponse(array_map(fn (Player $p): array => $this->presenter->player($p) + ['playCount' => $counts[$p->getId()] ?? 0], $players));
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
    public function update(Player $player, #[MapRequestPayload] UpdatePlayerRequest $req): JsonResponse
    {
        $this->denyAccessUnlessGranted(PartyVoter::VIEW, $player->getParty());
        if (null !== $player->getUser()) {
            throw new DomainException('Člena s účtem tu upravit nejde, jeho jméno se mění přezdívkou.');
        }
        if (null === $req->name && null === $req->visible) {
            throw new DomainException('Není co měnit.');
        }
        if (null !== $req->name) {
            $player->getParty()->renamePlayer($player, $req->name);
        }
        if (null !== $req->visible) {
            $player->setVisible($req->visible);
        }
        $this->em->flush();

        return new JsonResponse($this->presenter->player($player));
    }

    /** Smazat jde jen host, který nikde nehrál a není hráčem žádné hry (jinak by zápisy přišly o hráče). */
    #[Route('/api/players/{id}', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(Player $player): Response
    {
        $this->denyAccessUnlessGranted(PartyVoter::VIEW, $player->getParty());
        if (null !== $player->getUser()) {
            throw new DomainException('Člena s účtem nelze smazat, nejdřív ho odeber z party.');
        }
        $played = $this->plays->playCountsByPlayer($player->getParty())[$player->getId()] ?? 0;
        if ($played > 0) {
            throw new DomainException(sprintf('Host už hrál v %d zápisech, smazat ho nejde.', $played));
        }
        if ($game = $this->em->createQuery('SELECT g FROM '.Game::class.' g WHERE :player MEMBER OF g.players')->setParameter('player', $player)->setMaxResults(1)->getOneOrNullResult()) {
            throw new DomainException(sprintf('Host je hráčem hry %s, smazat ho nejde.', $game->getName()));
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
        $user = null;
        foreach ($party->getMembers() as $member) {
            if ($member->getUser()->getId() === $req->userId) {
                $user = $member->getUser();
            }
        }
        $target = $user ? $party->findPlayerFor($user) : null;
        if (!$user || !$target) {
            throw new DomainException('Uživatel není členem této party.');
        }
        $this->merger->mergeGuestIntoMember($player, $target);
        $counts = $this->plays->playCountsByPlayer($party);

        return new JsonResponse($this->presenter->player($target) + ['playCount' => $counts[$target->getId()] ?? 0]);
    }
}
