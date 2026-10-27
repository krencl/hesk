<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\PartyController;
use App\Domain\Exception\DomainException;
use App\Dto\ChangeRoleRequest;
use App\Dto\CreatePartyRequest;
use App\Dto\InviteRequest;
use App\Dto\PartyNicknameRequest;
use App\Dto\UpdatePartyRequest;
use App\Entity\Party;
use App\Entity\PartyInvitation;
use App\Entity\User;
use App\Enum\PartyRole;
use App\Repository\PartyRepository;
use App\Service\GuestMerger;
use App\Service\Presenter;
use App\Tests\Support\ControllerHarness;
use App\Tests\Support\DoctrineMocks;
use App\Tests\Support\Fixtures;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/** Party, členové a pozvánky: repozitáře jsou mocky, pravidla běží na entitách v paměti. */
final class PartyControllerTest extends TestCase
{
    use DoctrineMocks;

    private User $anna;
    private User $bob;
    private User $eve;
    private Party $party;
    private \ArrayObject $calls;
    /** @var array<string, PartyInvitation> */
    private array $invitations = [];

    protected function setUp(): void
    {
        $this->anna = Fixtures::user('Anna');
        $this->bob = Fixtures::user('Bob');
        $this->eve = Fixtures::user('Eve');
        $this->party = Fixtures::party($this->anna);
        $this->calls = new \ArrayObject();
        $this->invitations = [];
    }

    /** @param list<Party> $memberOf strany, které vrátí `findByMember` */
    private function controller(array $memberOf = [], ?GuestMerger $merger = null, array $known = []): PartyController
    {
        $parties = $this->createStub(PartyRepository::class);
        $parties->method('findByMember')->willReturn($memberOf);
        $invitations = $this->createStub(EntityRepository::class);
        $invitations->method('findOneBy')->willReturnCallback(fn (array $criteria): ?PartyInvitation => $this->invitations[$criteria['token']] ?? null);
        $em = $this->mockEm([$this->anna, $this->bob, $this->eve, ...$known], $this->calls);
        $em->method('getRepository')->willReturnCallback(static fn (string $class): object => PartyInvitation::class === $class ? $invitations : $parties);

        return new PartyController(new Presenter(), $em, $merger ?? $this->createStub(GuestMerger::class));
    }

    private function members(array $payload): array
    {
        return array_column($payload['members'], 'role', 'nickname');
    }

    public function testListReturnsPartiesOfTheUserWithHisRole(): void
    {
        $other = Fixtures::party($this->eve, 'Víkend');
        Fixtures::member($other, $this->anna, PartyRole::Writer);

        $list = ControllerHarness::json(ControllerHarness::as($this->anna)->call($this->controller([$this->party, $other]), 'list'));

        self::assertSame([['Pátek', 'Pá', 'admin'], ['Víkend', 'Ví', 'writer']], array_map(static fn (array $p): array => [$p['name'], $p['shortName'], $p['role']], $list));
    }

    public function testCreateMakesTheCreatorAdminWithAGeneratedShortName(): void
    {
        $response = ControllerHarness::as($this->anna)->call($this->controller(), 'create', ['req' => new CreatePartyRequest(' Viki + Marek ')]);
        $party = ControllerHarness::json($response);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame(['Viki + Marek', 'VM', 'admin'], [$party['name'], $party['shortName'], $party['role']]);
        self::assertSame(['Anna' => 'admin'], $this->members($party));
        self::assertSame(1, $this->calls['flush']);
    }

    public function testShowIsForMembersOnly(): void
    {
        Fixtures::member($this->party, $this->bob);
        $detail = ControllerHarness::json(ControllerHarness::as($this->bob)->call($this->controller(), 'show', ['party' => $this->party]));
        self::assertSame(['Anna' => 'admin', 'Bob' => 'member'], $this->members($detail));
        self::assertSame('member', $detail['role']);

        $this->expectException(AccessDeniedException::class);
        ControllerHarness::as($this->eve)->call($this->controller(), 'show', ['party' => $this->party]);
    }

    public function testAdminEditsNameAndShortNameButMembersCannot(): void
    {
        Fixtures::member($this->party, $this->bob);
        $edited = ControllerHarness::json(ControllerHarness::as($this->anna)->call($this->controller(), 'update', ['party' => $this->party, 'req' => new UpdatePartyRequest(' Pátek večer ', ' PV ')]));
        self::assertSame(['Pátek večer', 'PV'], [$edited['name'], $edited['shortName']], 'název i zkratka se ořezávají');

        $this->expectException(AccessDeniedException::class);
        ControllerHarness::as($this->bob)->call($this->controller(), 'update', ['party' => $this->party, 'req' => new UpdatePartyRequest('Cizí', 'C')]);
    }

    public function testOnlyAdminCreatesInvitations(): void
    {
        $response = ControllerHarness::as($this->anna)->call($this->controller(), 'invite', ['party' => $this->party, 'req' => new InviteRequest()]);
        $invitation = ControllerHarness::json($response);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame(48, \strlen($invitation['token']));
        self::assertNotNull(new \DateTimeImmutable($invitation['expiresAt']));
        self::assertInstanceOf(PartyInvitation::class, $this->calls['persist'][0]);

        Fixtures::member($this->party, $this->bob, PartyRole::Writer);
        $this->expectException(AccessDeniedException::class);
        ControllerHarness::as($this->bob)->call($this->controller(), 'invite', ['party' => $this->party, 'req' => new InviteRequest()]);
    }

    public function testInvitationForAGuestNeedsAGuestOfThisParty(): void
    {
        $host = Fixtures::guest($this->party, 'Bob');
        $annaPlayer = Fixtures::player($this->party, $this->anna);
        $foreignOwner = Fixtures::user('Zdeněk');
        $foreignGuest = Fixtures::guest(Fixtures::party($foreignOwner, 'Cizí'), 'Host');
        $controller = $this->controller(known: [$host, $annaPlayer, $foreignGuest]);
        $harness = ControllerHarness::as($this->anna);

        foreach ([$annaPlayer->getId(), 999999, $foreignGuest->getId()] as $bad) {
            try {
                $harness->call($controller, 'invite', ['party' => $this->party, 'req' => new InviteRequest($bad)]);
                self::fail('Pozvat jde jen hosta z této party.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }

        self::assertSame(201, $harness->call($controller, 'invite', ['party' => $this->party, 'req' => new InviteRequest($host->getId())])->getStatusCode());
        $stored = $this->calls['persist'][0];
        self::assertSame($host, $stored->getGuest());
    }

    public function testAcceptAddsTheUserAsPlainMember(): void
    {
        $invitation = new PartyInvitation($this->party, $this->anna);
        $this->invitations[$invitation->getToken()] = $invitation;

        $joined = ControllerHarness::json(ControllerHarness::as($this->bob)->call($this->controller(), 'accept', ['token' => $invitation->getToken()]));

        self::assertSame(['Anna' => 'admin', 'Bob' => 'member'], $this->members($joined));
        self::assertSame('member', $joined['role']);
        self::assertNull($joined['linkedGuest']);
        self::assertSame('Bob', Fixtures::player($this->party, $this->bob)->getName(), 'nový člen dostane hráče');
    }

    public function testExpiredOrUnknownInvitationIsNotFound(): void
    {
        $expired = new PartyInvitation($this->party, $this->anna, new \DateTimeImmutable('2020-01-01'));
        $this->invitations[$expired->getToken()] = $expired;
        $harness = ControllerHarness::as($this->bob);

        foreach ([$expired->getToken(), 'neexistuje'] as $token) {
            try {
                $harness->call($this->controller(), 'accept', ['token' => $token]);
                self::fail('Pozvánka neplatí.');
            } catch (NotFoundHttpException) {
                $this->addToAssertionCount(1);
            }
        }
        self::assertFalse($this->party->isMember($this->bob));
    }

    public function testInvitationForAGuestLinksHimWhenAccepted(): void
    {
        $host = Fixtures::guest($this->party, 'Bob');
        $invitation = new PartyInvitation($this->party, $this->anna, guest: $host);
        $this->invitations[$invitation->getToken()] = $invitation;
        $merger = $this->createMock(GuestMerger::class);
        $merger->expects(self::once())->method('mergeGuestIntoMember')->with($host, self::callback(fn ($player): bool => $player === $this->party->findPlayerFor($this->bob)));

        $joined = ControllerHarness::json(ControllerHarness::as($this->bob)->call($this->controller(merger: $merger), 'accept', ['token' => $invitation->getToken()]));

        self::assertSame('Bob', $joined['linkedGuest']);
    }

    public function testInvitationStillJoinsWhenTheGuestCannotBeMerged(): void
    {
        $host = Fixtures::guest($this->party, 'Bob');
        $invitation = new PartyInvitation($this->party, $this->anna, guest: $host);
        $this->invitations[$invitation->getToken()] = $invitation;
        $merger = $this->createStub(GuestMerger::class);
        $merger->method('mergeGuestIntoMember')->willThrowException(new DomainException('Host a člen hráli v 1 stejných zápisech, takže je nejde sloučit.'));

        $joined = ControllerHarness::json(ControllerHarness::as($this->bob)->call($this->controller(merger: $merger), 'accept', ['token' => $invitation->getToken()]));

        self::assertNull($joined['linkedGuest']);
        self::assertContains('Bob', array_column($joined['members'], 'nickname'));
    }

    public function testPlainInvitationNeverTriesToMergeAGuest(): void
    {
        Fixtures::guest($this->party, 'Cyril');
        $invitation = new PartyInvitation($this->party, $this->anna);
        $this->invitations[$invitation->getToken()] = $invitation;
        $merger = $this->createMock(GuestMerger::class);
        $merger->expects(self::never())->method('mergeGuestIntoMember');

        $joined = ControllerHarness::json(ControllerHarness::as(Fixtures::user('Cyril'))->call($this->controller(merger: $merger), 'accept', ['token' => $invitation->getToken()]));

        self::assertNull($joined['linkedGuest']);
        self::assertContains('Cyril', array_column($joined['members'], 'nickname'));
    }

    public function testAdminChangesRolesButNotTheLastAdmin(): void
    {
        Fixtures::member($this->party, $this->bob);
        $harness = ControllerHarness::as($this->anna);
        $controller = $this->controller();

        $promoted = ControllerHarness::json($harness->call($controller, 'changeRole', ['party' => $this->party, 'userId' => $this->bob->getId(), 'req' => new ChangeRoleRequest(PartyRole::Writer)]));
        self::assertSame(['Anna' => 'admin', 'Bob' => 'writer'], $this->members($promoted));

        foreach ([PartyRole::Writer, PartyRole::Member] as $role) {
            try {
                $harness->call($controller, 'changeRole', ['party' => $this->party, 'userId' => $this->anna->getId(), 'req' => new ChangeRoleRequest($role)]);
                self::fail('Poslední správce se nesmí degradovat.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
        self::assertTrue($this->party->isAdmin($this->anna));
    }

    public function testMemberSetsHisNicknameInThePartyOnly(): void
    {
        Fixtures::member($this->party, $this->bob);
        $controller = $this->controller();

        $detail = ControllerHarness::json(ControllerHarness::as($this->bob)->call($controller, 'updateMyNickname', ['party' => $this->party, 'req' => new PartyNicknameRequest('Bobík')]));
        $bob = array_values(array_filter($detail['members'], fn (array $m): bool => $m['userId'] === $this->bob->getId()))[0];
        self::assertSame(['Bobík', 'Bobík'], [$bob['nickname'], $bob['partyNickname']]);
        self::assertSame('Bob', $this->bob->getNickname(), 'přezdívka účtu se nemění');
        self::assertSame('Bobík', $this->party->findPlayerFor($this->bob)?->getName());

        $cleared = ControllerHarness::json(ControllerHarness::as($this->bob)->call($controller, 'updateMyNickname', ['party' => $this->party, 'req' => new PartyNicknameRequest(null)]));
        $bob = array_values(array_filter($cleared['members'], fn (array $m): bool => $m['userId'] === $this->bob->getId()))[0];
        self::assertSame(['Bob', null], [$bob['nickname'], $bob['partyNickname']]);
    }

    public function testNicknameInThePartyMustBeFreeAndOnlyMembersMaySetIt(): void
    {
        Fixtures::member($this->party, $this->bob);
        try {
            ControllerHarness::as($this->bob)->call($this->controller(), 'updateMyNickname', ['party' => $this->party, 'req' => new PartyNicknameRequest('anna')]);
            self::fail('Jméno Anny je v partě obsazené.');
        } catch (DomainException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(AccessDeniedException::class);
        ControllerHarness::as($this->eve)->call($this->controller(), 'updateMyNickname', ['party' => $this->party, 'req' => new PartyNicknameRequest('Eva')]);
    }

    public function testWriterCannotChangeRolesAndUnknownUserIsNotFound(): void
    {
        Fixtures::member($this->party, $this->bob, PartyRole::Writer);
        try {
            ControllerHarness::as($this->bob)->call($this->controller(), 'changeRole', ['party' => $this->party, 'userId' => $this->bob->getId(), 'req' => new ChangeRoleRequest(PartyRole::Admin)]);
            self::fail('Změnit roli smí jen správce.');
        } catch (AccessDeniedException) {
        }

        $this->expectException(NotFoundHttpException::class);
        ControllerHarness::as($this->anna)->call($this->controller(), 'changeRole', ['party' => $this->party, 'userId' => 999999, 'req' => new ChangeRoleRequest(PartyRole::Writer)]);
    }

    public function testAdminRemovesAMemberAndTheMemberKeepsHistoryAsGuest(): void
    {
        Fixtures::member($this->party, $this->bob);
        $bobPlayer = Fixtures::player($this->party, $this->bob);

        $response = ControllerHarness::as($this->anna)->call($this->controller(), 'removeMember', ['party' => $this->party, 'userId' => $this->bob->getId()]);

        self::assertSame(204, $response->getStatusCode());
        self::assertFalse($this->party->isMember($this->bob));
        self::assertNull($bobPlayer->getUser(), 'hráč zůstává jako host');
        self::assertContains($bobPlayer, $this->party->getPlayers());
    }

    public function testMemberCanLeaveButCannotRemoveOthers(): void
    {
        Fixtures::member($this->party, $this->bob);
        try {
            ControllerHarness::as($this->bob)->call($this->controller(), 'removeMember', ['party' => $this->party, 'userId' => $this->anna->getId()]);
            self::fail('Cizího člena smí odebrat jen správce.');
        } catch (AccessDeniedException) {
        }

        self::assertSame(204, ControllerHarness::as($this->bob)->call($this->controller(), 'removeMember', ['party' => $this->party, 'userId' => $this->bob->getId()])->getStatusCode());
        self::assertFalse($this->party->isMember($this->bob));
    }

    public function testLastAdminCannotLeaveAndStrangersAreDenied(): void
    {
        try {
            ControllerHarness::as($this->anna)->call($this->controller(), 'removeMember', ['party' => $this->party, 'userId' => $this->anna->getId()]);
            self::fail('Poslední správce nesmí odejít.');
        } catch (DomainException) {
        }

        $this->expectException(AccessDeniedException::class);
        ControllerHarness::as($this->eve)->call($this->controller(), 'removeMember', ['party' => $this->party, 'userId' => $this->eve->getId()]);
    }
}
