<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ChangeRoleRequest;
use App\Dto\CreatePartyRequest;
use App\Entity\Party;
use App\Entity\PartyInvitation;
use App\Entity\User;
use App\Security\PartyVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class PartyController extends ApiController
{
    public function __construct(\App\Service\Presenter $presenter, private readonly EntityManagerInterface $em)
    {
        parent::__construct($presenter);
    }

    #[Route('/api/parties', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $user = $this->currentUser();
        $parties = $this->em->createQuery(
            'SELECT p FROM App\Entity\Party p JOIN p.members m WHERE m.user = :u ORDER BY p.name',
        )->setParameter('u', $user)->getResult();

        return new JsonResponse(array_map(fn (Party $p): array => $this->presenter->party($p, $user), $parties));
    }

    #[Route('/api/parties', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreatePartyRequest $req): JsonResponse
    {
        $user = $this->currentUser();
        $party = Party::create(trim($req->name), $user);
        $this->em->persist($party);
        $this->em->flush();

        return new JsonResponse($this->presenter->partyDetail($party, $user), Response::HTTP_CREATED);
    }

    #[Route('/api/parties/{id}', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(PartyVoter::VIEW, 'party')]
    public function show(Party $party): JsonResponse
    {
        return new JsonResponse($this->presenter->partyDetail($party, $this->currentUser()));
    }

    #[Route('/api/parties/{id}', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    #[IsGranted(PartyVoter::ADMIN, 'party')]
    public function rename(Party $party, #[MapRequestPayload] CreatePartyRequest $req): JsonResponse
    {
        $party->rename(trim($req->name));
        $this->em->flush();

        return new JsonResponse($this->presenter->partyDetail($party, $this->currentUser()));
    }

    /** Admin vygeneruje odkaz (token), kterým se může kdokoli přihlášený do skupiny připojit. */
    #[Route('/api/parties/{id}/invitations', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(PartyVoter::ADMIN, 'party')]
    public function invite(Party $party): JsonResponse
    {
        $invitation = new PartyInvitation($party, $this->currentUser());
        $this->em->persist($invitation);
        $this->em->flush();

        return new JsonResponse(
            ['token' => $invitation->getToken(), 'expiresAt' => $invitation->getExpiresAt()->format(\DATE_ATOM)],
            Response::HTTP_CREATED,
        );
    }

    #[Route('/api/invitations/{token}/accept', methods: ['POST'])]
    public function accept(string $token): JsonResponse
    {
        $invitation = $this->em->getRepository(PartyInvitation::class)->findOneBy(['token' => $token]);
        if (!$invitation || $invitation->isExpired()) {
            throw $this->createNotFoundException('Pozvánka neexistuje nebo vypršela.');
        }
        $user = $this->currentUser();
        $party = $invitation->getParty();
        $party->addMember($user);
        $this->em->flush();

        return new JsonResponse($this->presenter->partyDetail($party, $user));
    }

    #[Route('/api/parties/{id}/members/{userId}', requirements: ['id' => '\d+', 'userId' => '\d+'], methods: ['PATCH'])]
    #[IsGranted(PartyVoter::ADMIN, 'party')]
    public function changeRole(Party $party, int $userId, #[MapRequestPayload] ChangeRoleRequest $req): JsonResponse
    {
        $party->changeRole($this->member($userId), $req->role);
        $this->em->flush();

        return new JsonResponse($this->presenter->partyDetail($party, $this->currentUser()));
    }

    /** Člena odebírá admin; kdokoli může opustit skupinu sám. */
    #[Route('/api/parties/{id}/members/{userId}', requirements: ['id' => '\d+', 'userId' => '\d+'], methods: ['DELETE'])]
    #[IsGranted(PartyVoter::VIEW, 'party')]
    public function removeMember(Party $party, int $userId): Response
    {
        $me = $this->currentUser();
        if ($me->getId() !== $userId) {
            $this->denyAccessUnlessGranted(PartyVoter::ADMIN, $party);
        }
        $party->removeMember($this->member($userId));
        $this->em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function member(int $userId): User
    {
        return $this->em->find(User::class, $userId) ?? throw $this->createNotFoundException();
    }
}
