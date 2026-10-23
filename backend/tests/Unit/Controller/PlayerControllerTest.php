<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\PlayerController;
use App\Domain\Exception\DomainException;
use App\Dto\LinkMemberRequest;
use App\Dto\PlayerNameRequest;
use App\Dto\UpdatePlayerRequest;
use App\Entity\Party;
use App\Entity\User;
use App\Enum\PartyRole;
use App\Repository\PlayRepository;
use App\Service\GuestMerger;
use App\Service\Presenter;
use App\Tests\Support\ControllerHarness;
use App\Tests\Support\DoctrineMocks;
use App\Tests\Support\Fixtures;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/** Hráči party (členové a hosté): počty zápisů a ostatní data z DB jsou mocky. */
final class PlayerControllerTest extends TestCase
{
    use DoctrineMocks;

    private User $anna;
    private User $bob;
    private User $eve;
    private Party $party;
    private \ArrayObject $calls;
    /** @var array<int, int> hráč => počet zápisů (to, co by vrátil repozitář) */
    private array $counts = [];
    private ?object $playedGame = null;

    protected function setUp(): void
    {
        $this->anna = Fixtures::user('Anna');
        $this->bob = Fixtures::user('Bob');
        $this->eve = Fixtures::user('Eve');
        $this->party = Fixtures::party($this->anna);
        $this->calls = new \ArrayObject();
        $this->counts = [];
        $this->playedGame = null;
    }

    private function controller(?GuestMerger $merger = null): PlayerController
    {
        $plays = $this->createStub(PlayRepository::class);
        $plays->method('playCountsByPlayer')->willReturnCallback(fn (): array => $this->counts);
        $em = $this->mockEm([], $this->calls);
        $em->method('createQuery')->willReturnCallback(fn () => $this->mockQuery(['getOneOrNullResult' => $this->playedGame]));

        return new PlayerController(new Presenter(), $em, $plays, $merger ?? $this->createStub(GuestMerger::class));
    }

    public function testListIsSortedByNameAndHasPlayCountsAndVisibility(): void
    {
        $dora = Fixtures::guest($this->party, 'dora');
        $bobik = Fixtures::guest($this->party, 'Bobík');
        $cyril = Fixtures::guest($this->party, 'Cyril');
        $cyril->setVisible(false);
        $this->counts = [Fixtures::player($this->party, $this->anna)->getId() => 1, $bobik->getId() => 1];

        $list = ControllerHarness::json(ControllerHarness::as($this->anna)->call($this->controller(), 'list', ['party' => $this->party]));

        self::assertSame(['Anna', 'Bobík', 'Cyril', 'dora'], array_column($list, 'name'));
        self::assertSame([1, 1, 0, 0], array_column($list, 'playCount'));
        self::assertSame([true, true, false, true], array_column($list, 'visible'));
        self::assertSame([true, false, false, false], array_column($list, 'hasAccount'));
        self::assertSame($dora->getId(), $list[3]['id']);
    }

    public function testStrangerCannotListOrAddGuests(): void
    {
        $harness = ControllerHarness::as($this->eve);
        foreach ([['list', ['party' => $this->party]], ['createGuest', ['party' => $this->party, 'req' => new PlayerNameRequest('X')]]] as [$action, $args]) {
            try {
                $harness->call($this->controller(), $action, $args);
                self::fail("Cizí uživatel nesmí volat $action.");
            } catch (AccessDeniedException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testPlainMemberCannotAddOrManageGuestsButWriterCan(): void
    {
        Fixtures::member($this->party, $this->bob, PartyRole::Member);
        Fixtures::member($this->party, $this->eve, PartyRole::Writer);
        $cyril = Fixtures::guest($this->party, 'Cyril');
        $calls = [
            ['createGuest', ['party' => $this->party, 'req' => new PlayerNameRequest('X')]],
            ['update', ['player' => $cyril, 'req' => new UpdatePlayerRequest('Y')]],
            ['delete', ['player' => $cyril]],
        ];

        foreach ($calls as [$action, $args]) {
            try {
                ControllerHarness::as($this->bob)->call($this->controller(), $action, $args);
                self::fail("Obyčejný člen nesmí volat $action.");
            } catch (AccessDeniedException) {
                $this->addToAssertionCount(1);
            }
        }
        // čtení hráčů zůstává členovi (výběr hráčů se zobrazuje všem), zapisovač hosty spravuje
        ControllerHarness::as($this->bob)->call($this->controller(), 'list', ['party' => $this->party]);
        foreach ($calls as [$action, $args]) {
            ControllerHarness::as($this->eve)->call($this->controller(), $action, $args);
        }
    }

    public function testGuestIsCreatedWithTrimmedNameAndDuplicatesAreRejected(): void
    {
        $harness = ControllerHarness::as($this->anna);
        $response = $harness->call($this->controller(), 'createGuest', ['party' => $this->party, 'req' => new PlayerNameRequest(' Host ')]);

        self::assertSame(201, $response->getStatusCode());
        $guest = ControllerHarness::json($response);
        self::assertSame(['Host', false, true], [$guest['name'], $guest['hasAccount'], $guest['visible']]);
        self::assertSame(1, $this->calls['flush']);

        foreach (['host', 'ANNA'] as $duplicate) {
            try {
                $harness->call($this->controller(), 'createGuest', ['party' => $this->party, 'req' => new PlayerNameRequest($duplicate)]);
                self::fail('Duplicitní jméno se musí odmítnout.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testGuestCanBeRenamedAndHiddenSeparately(): void
    {
        $bob = Fixtures::guest($this->party, 'Bob');
        Fixtures::guest($this->party, 'Cyril');
        $harness = ControllerHarness::as($this->anna);

        self::assertSame('Bobík', ControllerHarness::json($harness->call($this->controller(), 'update', ['player' => $bob, 'req' => new UpdatePlayerRequest(' Bobík ')]))['name']);

        $hidden = ControllerHarness::json($harness->call($this->controller(), 'update', ['player' => $bob, 'req' => new UpdatePlayerRequest(visible: false)]));
        self::assertSame(['Bobík', false], [$hidden['name'], $hidden['visible']], 'jméno zůstane');
        self::assertTrue(ControllerHarness::json($harness->call($this->controller(), 'update', ['player' => $bob, 'req' => new UpdatePlayerRequest('Bobík', true)]))['visible']);
    }

    public function testInvalidPlayerEditsAreRejected(): void
    {
        $bob = Fixtures::guest($this->party, 'Bob');
        Fixtures::guest($this->party, 'Cyril');
        $annaPlayer = Fixtures::player($this->party, $this->anna);
        $harness = ControllerHarness::as($this->anna);

        foreach ([
            [$bob, new UpdatePlayerRequest('cyril')],     // jméno je obsazené
            [$bob, new UpdatePlayerRequest('  ')],        // prázdné jméno
            [$bob, new UpdatePlayerRequest()],            // není co měnit
            [$annaPlayer, new UpdatePlayerRequest('Annie')],         // člen s účtem
            [$annaPlayer, new UpdatePlayerRequest(visible: false)],  // ani příznak člena
        ] as [$player, $req]) {
            try {
                $harness->call($this->controller(), 'update', ['player' => $player, 'req' => $req]);
                self::fail('Úprava se musí odmítnout.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
        self::assertSame('Bob', $bob->getName());
    }

    public function testOnlyPartyMembersCanEditOrDeletePlayers(): void
    {
        $cyril = Fixtures::guest($this->party, 'Cyril');
        $harness = ControllerHarness::as($this->eve);

        foreach ([['update', ['player' => $cyril, 'req' => new UpdatePlayerRequest('X')]], ['delete', ['player' => $cyril]]] as [$action, $args]) {
            try {
                $harness->call($this->controller(), $action, $args);
                self::fail("Cizí uživatel nesmí volat $action.");
            } catch (AccessDeniedException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testUnusedGuestIsDeletedAndHisInvitationsForgotten(): void
    {
        $cyril = Fixtures::guest($this->party, 'Cyril');
        $merger = $this->createMock(GuestMerger::class);
        $merger->expects(self::once())->method('forgetInvitations')->with($cyril);

        $response = ControllerHarness::as($this->anna)->call($this->controller($merger), 'delete', ['player' => $cyril]);

        self::assertSame(204, $response->getStatusCode());
        self::assertSame([$cyril], $this->calls['remove']);
    }

    public function testGuestWhoPlayedMemberAndGamePlayerCannotBeDeleted(): void
    {
        $bob = Fixtures::guest($this->party, 'Bob');
        $dora = Fixtures::guest($this->party, 'Dora');
        $annaPlayer = Fixtures::player($this->party, $this->anna);
        $harness = ControllerHarness::as($this->anna);

        // host, který hrál
        $this->counts = [$bob->getId() => 2];
        $this->expectDomainError(fn () => $harness->call($this->controller(), 'delete', ['player' => $bob]), 'v 2 zápisech');

        // člen s účtem
        $this->counts = [];
        $this->expectDomainError(fn () => $harness->call($this->controller(), 'delete', ['player' => $annaPlayer]), 'Člena s účtem');

        // hráč hry
        $this->playedGame = $this->party->findGameLink(Fixtures::game($this->party, 'Coop'));
        $this->expectDomainError(fn () => $harness->call($this->controller(), 'delete', ['player' => $dora]), 'Coop');

        self::assertArrayNotHasKey('remove', $this->calls->getArrayCopy());
    }

    private function expectDomainError(callable $action, string $messagePart): void
    {
        try {
            $action();
            self::fail('Očekávala se DomainException.');
        } catch (DomainException $e) {
            self::assertStringContainsString($messagePart, $e->getMessage());
        }
    }

    public function testLinkMemberMergesGuestIntoMembersPlayerAndReturnsHisCount(): void
    {
        Fixtures::member($this->party, $this->bob);
        $host = Fixtures::guest($this->party, 'Bobík');
        $bobPlayer = Fixtures::player($this->party, $this->bob);
        $this->counts = [$bobPlayer->getId() => 3];
        $merger = $this->createMock(GuestMerger::class);
        $merger->expects(self::once())->method('mergeGuestIntoMember')->with($host, $bobPlayer);

        $linked = ControllerHarness::json(ControllerHarness::as($this->anna)->call($this->controller($merger), 'linkMember', ['player' => $host, 'req' => new LinkMemberRequest($this->bob->getId())]));

        self::assertSame(['Bob', 3, true], [$linked['name'], $linked['playCount'], $linked['hasAccount']]);
    }

    public function testLinkMemberIsAdminOnlyAndNeedsAMemberOfTheParty(): void
    {
        Fixtures::member($this->party, $this->bob);
        $host = Fixtures::guest($this->party, 'Bobík');
        $merger = $this->createMock(GuestMerger::class);
        $merger->expects(self::never())->method('mergeGuestIntoMember');

        try {
            ControllerHarness::as($this->bob)->call($this->controller($merger), 'linkMember', ['player' => $host, 'req' => new LinkMemberRequest($this->bob->getId())]);
            self::fail('Propojovat smí jen správce.');
        } catch (AccessDeniedException) {
        }

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('není členem');
        ControllerHarness::as($this->anna)->call($this->controller($merger), 'linkMember', ['player' => $host, 'req' => new LinkMemberRequest(999999)]);
    }

    public function testWriterCannotLinkMembers(): void
    {
        Fixtures::member($this->party, $this->bob, PartyRole::Writer);
        $host = Fixtures::guest($this->party, 'Bobík');

        $this->expectException(AccessDeniedException::class);
        ControllerHarness::as($this->bob)->call($this->controller(), 'linkMember', ['player' => $host, 'req' => new LinkMemberRequest($this->bob->getId())]);
    }
}
