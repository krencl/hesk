<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\AuthController;
use App\Dto\DevLoginRequest;
use App\Dto\NicknameRequest;
use App\Entity\Party;
use App\Entity\PartyInvitation;
use App\Entity\User;
use App\Repository\PartyRepository;
use App\Service\Presenter;
use App\Tests\Support\ControllerHarness;
use App\Tests\Support\DoctrineMocks;
use App\Tests\Support\Fixtures;
use Doctrine\ORM\EntityRepository;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\OAuth2ClientInterface;
use League\OAuth2\Client\Provider\GoogleUser;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Přihlášení a profil: uživatelé z „databáze“ i přihlašovací služby jsou mocky. */
final class AuthControllerTest extends TestCase
{
    use DoctrineMocks;

    private \ArrayObject $calls;
    /** @var list<User> uživatelé, které by našel repozitář */
    private array $users = [];
    /** @var list<Party> */
    private array $parties = [];
    /** @var list<PartyInvitation> */
    private array $invitations = [];

    protected function setUp(): void
    {
        $this->calls = new \ArrayObject();
        $this->users = [];
        $this->parties = [];
        $this->invitations = [];
    }

    private function controller(?Security $security = null): AuthController
    {
        $users = $this->createStub(EntityRepository::class);
        $users->method('findOneBy')->willReturnCallback(function (array $criteria): ?User {
            foreach ($this->users as $user) {
                if (isset($criteria['email']) && $user->getEmail() === $criteria['email']) {
                    return $user;
                }
                if (isset($criteria['googleId']) && $user->getGoogleId() === $criteria['googleId']) {
                    return $user;
                }
            }

            return null;
        });
        $parties = $this->createStub(PartyRepository::class);
        $parties->method('findByMember')->willReturnCallback(fn (): array => $this->parties);
        $invitations = $this->createStub(EntityRepository::class);
        $invitations->method('findOneBy')->willReturnCallback(function (array $criteria): ?PartyInvitation {
            foreach ($this->invitations as $invitation) {
                if ($invitation->getToken() === ($criteria['token'] ?? null)) {
                    return $invitation;
                }
            }

            return null;
        });
        $em = $this->mockEm([], $this->calls);
        $em->method('getRepository')->willReturnCallback(static fn (string $class): object => match ($class) {
            User::class => $users,
            PartyInvitation::class => $invitations,
            default => $parties,
        });

        return new AuthController(new Presenter(), $em, $security ?? $this->createStub(Security::class), 'https://hesk.example');
    }

    public function testDevLoginCreatesAnAccountWithNicknameAndLogsItIn(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects(self::once())->method('login')->with(self::isInstanceOf(User::class), null, 'main');

        $me = ControllerHarness::json(ControllerHarness::as(Fixtures::user('X'))->call($this->controller($security), 'devLogin', ['req' => new DevLoginRequest('xena@example.com', 'Xena')]));

        self::assertSame(['xena@example.com', 'Xena', true], [$me['email'], $me['nickname'], $me['hasNickname']]);
        self::assertInstanceOf(User::class, $this->calls['persist'][0]);
    }

    public function testDevLoginWithoutNicknameLeavesItUnset(): void
    {
        $me = ControllerHarness::json(ControllerHarness::as(Fixtures::user('X'))->call($this->controller(), 'devLogin', ['req' => new DevLoginRequest('x@example.com')]));

        self::assertFalse($me['hasNickname']);
    }

    public function testDevLoginKeepsAnExistingNickname(): void
    {
        $this->users = [Fixtures::user('Anna', 'anna@example.com')];

        $me = ControllerHarness::json(ControllerHarness::as(Fixtures::user('X'))->call($this->controller(), 'devLogin', ['req' => new DevLoginRequest('anna@example.com', 'Jiná')]));

        self::assertSame('Anna', $me['nickname']);
        self::assertSame($this->users[0]->getId(), $me['id']);
    }

    public function testDevLoginIsDisabledOutsideDevAndTest(): void
    {
        $this->expectException(NotFoundHttpException::class);
        ControllerHarness::as(Fixtures::user('X'), 'prod')->call($this->controller(), 'devLogin', ['req' => new DevLoginRequest('x@example.com', 'X')]);
    }

    public function testMeReturnsTheCurrentUser(): void
    {
        $anna = Fixtures::user('Anna');

        $me = ControllerHarness::json(ControllerHarness::as($anna)->call($this->controller(), 'me'));

        self::assertSame([$anna->getId(), 'Anna', 'anna@example.com', true], [$me['id'], $me['nickname'], $me['email'], $me['hasNickname']]);
    }

    public function testNicknameIsTrimmedAndSyncedToPlayersInAllParties(): void
    {
        $user = new User('g-1', 'x@example.com');
        Fixtures::id($user);
        $party = Fixtures::party($user);
        $this->parties = [$party];
        self::assertSame('x', Fixtures::player($party, $user)->getName(), 'bez přezdívky se hráč jmenuje podle e-mailu');

        $me = ControllerHarness::json(ControllerHarness::as($user)->call($this->controller(), 'updateMe', ['req' => new NicknameRequest(' Xena ')]));

        self::assertSame(['Xena', true], [$me['nickname'], $me['hasNickname']]);
        self::assertSame('Xena', Fixtures::player($party, $user)->getName());
        self::assertSame(1, $this->calls['flush']);
    }

    private function registry(string $googleId, string $email): ClientRegistry
    {
        $client = $this->createStub(OAuth2ClientInterface::class);
        $client->method('fetchUser')->willReturn(new GoogleUser(['sub' => $googleId, 'email' => $email]));
        $client->method('redirect')->willReturn(new RedirectResponse('https://accounts.google.example'));
        $registry = $this->createStub(ClientRegistry::class);
        $registry->method('getClient')->willReturn($client);

        return $registry;
    }

    /** Požadavek s session; `$invitation` simuluje token uložený při startu přihlášení. */
    private function request(?string $invitation = null): Request
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        if (null !== $invitation) {
            $request->getSession()->set('registration_invitation', $invitation);
        }

        return $request;
    }

    private function invitation(?\DateTimeImmutable $now = null): PartyInvitation
    {
        $owner = Fixtures::user('Owner', 'owner@example.com');

        return $this->invitations[] = new PartyInvitation(Fixtures::party($owner), $owner, $now);
    }

    public function testGoogleLoginWithAnInvitationCreatesANewUserAndRedirectsToTheFrontend(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects(self::once())->method('login')->with(self::isInstanceOf(User::class), null, 'main');

        $invitation = $this->invitation();

        $response = ControllerHarness::as(Fixtures::user('X'))->call($this->controller($security), 'googleCallback', ['clients' => $this->registry('123', 'new@example.com'), 'request' => $this->request($invitation->getToken())]);

        self::assertSame('https://hesk.example', $response->getTargetUrl());
        $created = $this->calls['persist'][0];
        self::assertSame(['123', 'new@example.com'], [$created->getGoogleId(), $created->getEmail()]);
    }

    public function testGoogleLoginLinksAnAccountImportedFromTheOldApplicationByEmail(): void
    {
        $legacy = new User('legacy-1', 'old@example.com');
        $this->users = [$legacy];

        ControllerHarness::as(Fixtures::user('X'))->call($this->controller(), 'googleCallback', ['clients' => $this->registry('555', 'old@example.com'), 'request' => $this->request()]);

        self::assertSame('555', $legacy->getGoogleId(), 'Google ID se doplní při prvním přihlášení');
        self::assertSame([$legacy], $this->calls['persist']);
    }

    public function testGoogleLoginFindsTheUserByGoogleId(): void
    {
        $known = new User('777', 'someone@example.com');
        $this->users = [$known];

        ControllerHarness::as(Fixtures::user('X'))->call($this->controller(), 'googleCallback', ['clients' => $this->registry('777', 'changed@example.com'), 'request' => $this->request()]);

        self::assertSame([$known], $this->calls['persist'], 'žádný nový účet');
        self::assertSame('someone@example.com', $known->getEmail());
    }

    public function testGoogleLoginWithoutAnInvitationDoesNotCreateAnAccount(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects(self::never())->method('login');

        $response = ControllerHarness::as(Fixtures::user('X'))->call($this->controller($security), 'googleCallback', ['clients' => $this->registry('123', 'new@example.com'), 'request' => $this->request()]);

        self::assertSame('https://hesk.example/?registration=denied', $response->getTargetUrl());
        self::assertArrayNotHasKey('persist', $this->calls->getArrayCopy());
        self::assertArrayNotHasKey('flush', $this->calls->getArrayCopy());
    }

    public function testGoogleLoginWithAnUnknownOrExpiredInvitationDoesNotCreateAnAccount(): void
    {
        $expired = $this->invitation(new \DateTimeImmutable('-30 days'));

        foreach (['unknown-token', $expired->getToken()] as $token) {
            $response = ControllerHarness::as(Fixtures::user('X'))->call($this->controller(), 'googleCallback', ['clients' => $this->registry('123', 'new@example.com'), 'request' => $this->request($token)]);

            self::assertSame('https://hesk.example/?registration=denied', $response->getTargetUrl());
        }
        self::assertArrayNotHasKey('persist', $this->calls->getArrayCopy());
    }

    public function testInvitationTokenIsUsedOnlyOnceAndStoredWhenLoginStarts(): void
    {
        $request = $this->request();
        $request->query->set('invitation', 'abc');
        $clients = $this->registry('1', 'a@example.com');

        ControllerHarness::as(Fixtures::user('X'))->call($this->controller(), 'google', ['clients' => $clients, 'request' => $request]);
        self::assertSame('abc', $request->getSession()->get('registration_invitation'));

        ControllerHarness::as(Fixtures::user('X'))->call($this->controller(), 'googleCallback', ['clients' => $clients, 'request' => $request]);
        self::assertNull($request->getSession()->get('registration_invitation'), 'token se po pokusu o přihlášení maže');
    }
}
