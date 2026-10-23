<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\DevLoginRequest;
use App\Dto\NicknameRequest;
use App\Entity\Party;
use App\Entity\PartyInvitation;
use App\Entity\User;
use App\Service\Presenter;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\SecurityBundle\Security;

final class AuthController extends ApiController
{
    /** Klíč v session: token pozvánky, se kterým uživatel začal přihlášení (smí si s ním založit účet). */
    private const INVITATION_SESSION_KEY = 'registration_invitation';

    public function __construct(
        Presenter $presenter,
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        #[Autowire('%env(FRONTEND_URL)%')]
        private readonly string $frontendUrl,
    ) {
        parent::__construct($presenter);
    }

    /** `?invitation=<token>` – token pozvánky z odkazu `/join/<token>`; bez něj si nový uživatel účet založit nesmí. */
    #[Route('/api/auth/google', name: 'auth_google', methods: ['GET'])]
    public function google(ClientRegistry $clients, Request $request): RedirectResponse
    {
        $token = $request->query->getString('invitation');
        if ('' !== $token) {
            $request->getSession()->set(self::INVITATION_SESSION_KEY, $token);
        }

        return $clients->getClient('google')->redirect(['openid', 'email', 'profile'], []);
    }

    // Redirect URI zaregistrovaná v Google Console je /oauth/google/check (stejná jako u prototypu).
    #[Route('/oauth/google/check', name: 'auth_google_check', methods: ['GET'])]
    #[Route('/api/auth/google/callback', name: 'auth_google_callback', methods: ['GET'])]
    public function googleCallback(ClientRegistry $clients, Request $request): RedirectResponse
    {
        /** @var GoogleUser $google */
        $google = $clients->getClient('google')->fetchUser();
        $invitationToken = $request->getSession()->remove(self::INVITATION_SESSION_KEY);
        $repo = $this->em->getRepository(User::class);
        $user = $repo->findOneBy(['googleId' => $google->getId()]);
        if (!$user && ($existing = $repo->findOneBy(['email' => $google->getEmail()]))) {
            // účet převzatý ze staré aplikace: Google ID doplníme při prvním přihlášení
            $existing->linkGoogle((string) $google->getId());
            $user = $existing;
        }
        if (!$user) {
            // registrace je uzavřená: nový účet jen s platnou pozvánkou
            if (!$this->isValidInvitation($invitationToken)) {
                return new RedirectResponse(rtrim($this->frontendUrl, '/').'/?registration=denied');
            }
            $user = new User((string) $google->getId(), (string) $google->getEmail());
        }
        $this->em->persist($user);
        $this->em->flush();
        $this->security->login($user, null, 'main');

        return new RedirectResponse($this->frontendUrl);
    }

    private function isValidInvitation(mixed $token): bool
    {
        if (!\is_string($token) || '' === $token) {
            return false;
        }
        $invitation = $this->em->getRepository(PartyInvitation::class)->findOneBy(['token' => $token]);

        return $invitation instanceof PartyInvitation && !$invitation->isExpired();
    }

    /** Pouze pro vývoj a testy: přihlášení bez Googlu. */
    #[Route('/api/auth/dev-login', methods: ['POST'])]
    public function devLogin(#[MapRequestPayload] DevLoginRequest $req): Response
    {
        if (!\in_array($this->getParameter('kernel.environment'), ['dev', 'test'], true)) {
            throw $this->createNotFoundException();
        }
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $req->email])
            ?? new User('dev-'.$req->email, $req->email);
        if (!$user->hasNickname() && $req->nickname) {
            $user->setNickname($req->nickname);
        }
        $this->em->persist($user);
        $this->em->flush();
        $this->security->login($user, null, 'main');

        return new JsonResponse($this->presenter->me($user));
    }

    #[Route('/api/me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        return new JsonResponse($this->presenter->me($this->currentUser()));
    }

    #[Route('/api/me', methods: ['PATCH'])]
    public function updateMe(#[MapRequestPayload] NicknameRequest $req): JsonResponse
    {
        $user = $this->currentUser();
        $user->setNickname(trim($req->nickname));
        foreach ($this->em->getRepository(Party::class)->findByMember($user) as $party) {
            $party->syncPlayerName($user);
        }
        $this->em->flush();

        return new JsonResponse($this->presenter->me($user));
    }
}
