<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Domain\Exception\DomainException;
use App\Entity\Game;
use App\Entity\Party;
use App\Entity\PartyInvitation;
use App\Entity\Player;
use App\Entity\User;
use App\Enum\ScoreType;
use App\Service\GuestMerger;
use App\Tests\Support\DoctrineMocks;
use App\Tests\Support\Fixtures;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

/** Propojení hosta s členem: dotazy do databáze jsou namockované, kontroluje se, co se s entitami stane. */
final class GuestMergerTest extends TestCase
{
    use DoctrineMocks;

    private User $anna;
    private User $bob;
    private Party $party;
    private Player $host;
    private Player $bobPlayer;
    private \ArrayObject $calls;
    private int $sharedPlays = 0;
    /** @var list<Game> hry, ve kterých je host hráčem */
    private array $gamesOfHost = [];
    /** @var list<PartyInvitation> */
    private array $invitations = [];

    protected function setUp(): void
    {
        $this->anna = Fixtures::user('Anna');
        $this->bob = Fixtures::user('Bob');
        $this->party = Fixtures::party($this->anna);
        Fixtures::member($this->party, $this->bob);
        $this->host = Fixtures::guest($this->party, 'Bobík');
        $this->bobPlayer = Fixtures::player($this->party, $this->bob);
        $this->calls = new \ArrayObject();
        $this->sharedPlays = 0;
        $this->gamesOfHost = [];
        $this->invitations = [];
    }

    private function merger(): GuestMerger
    {
        $invitations = $this->createStub(EntityRepository::class);
        $invitations->method('findBy')->willReturnCallback(fn (): array => $this->invitations);
        $em = $this->mockEm([], $this->calls);
        $em->method('getRepository')->willReturn($invitations);
        $em->method('createQuery')->willReturnCallback(function (string $dql) {
            return str_starts_with($dql, 'SELECT COUNT')
                ? $this->mockQuery(['getSingleScalarResult' => $this->sharedPlays])
                : $this->mockQuery(['getResult' => $this->gamesOfHost, 'execute' => 2]);
        });

        return new GuestMerger($em);
    }

    public function testMergeMovesGuestGamesToTheMemberAndRemovesTheGuest(): void
    {
        $coop = Fixtures::game($this->party, 'Coop', ScoreType::WinnerOnly);
        $coop->getPlayers()->add($this->host);
        $this->gamesOfHost = [$coop];
        $invitation = new PartyInvitation($this->party, $this->anna, guest: $this->host);
        $this->invitations = [$invitation];

        $this->merger()->mergeGuestIntoMember($this->host, $this->bobPlayer);

        self::assertSame([$this->bobPlayer], array_values($coop->getPlayers()->toArray()), 'hráč hry se přepsal na člena');
        self::assertNotContains($this->host, $this->party->getPlayers());
        self::assertSame([$this->host], $this->calls['remove']);
        self::assertNull($invitation->getGuest(), 'pozvánky hosta ztratí vazbu');
        self::assertSame('Bob', $this->bobPlayer->getName());
    }

    public function testMemberKeepsExistingGamePlayerSlotWithoutDuplicate(): void
    {
        $coop = Fixtures::game($this->party, 'Coop', ScoreType::WinnerOnly);
        $coop->getPlayers()->add($this->host);
        $coop->getPlayers()->add($this->bobPlayer);
        $this->gamesOfHost = [$coop];

        $this->merger()->mergeGuestIntoMember($this->host, $this->bobPlayer);

        self::assertSame([$this->bobPlayer], array_values($coop->getPlayers()->toArray()));
    }

    public function testMemberNameFollowsNicknameOnceTheGuestNameIsFree(): void
    {
        // při přidání člena bylo jméno „Bob“ obsazené hostem, takže hráč člena dostal „Bob 2“
        $other = Fixtures::user('Bob', 'bob2@example.com');
        $party = Fixtures::party($this->anna, 'Druhá');
        Fixtures::guest($party, 'Bob');
        Fixtures::member($party, $other);
        $guest = Fixtures::guest($party, 'Bobík');
        $member = Fixtures::player($party, $other);
        self::assertSame('Bob 2', $member->getName());
        $party->getPlayers()->removeElement($party->getPlayers()->filter(static fn (Player $p): bool => 'Bob' === $p->getName() && null === $p->getUser())->first());

        $this->merger()->mergeGuestIntoMember($guest, $member);

        self::assertSame('Bob', $member->getName());
    }

    /** @return iterable<string, array{callable(self): array{Player, Player}, string}> */
    public static function invalidMerges(): iterable
    {
        yield 'stejný hráč' => [static fn (self $t): array => [$t->bobPlayer, $t->bobPlayer], 'stejný hráč'];
        yield 'člen s účtem jako host' => [static fn (self $t): array => [$t->bobPlayer, Fixtures::player($t->party, $t->anna)], 'jen host bez účtu'];
        yield 'cíl je host' => [static fn (self $t): array => [$t->host, Fixtures::guest($t->party, 'Jiný host')], 'ne s jiným hostem'];
        yield 'jiná parta' => [static function (self $t): array {
            $eve = Fixtures::user('Eve');
            $other = Fixtures::party($eve, 'Cizí');

            return [$t->host, Fixtures::player($other, $eve)];
        }, 'jiné party'];
    }

    /** @param callable(self): array{Player, Player} $pair */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidMerges')]
    public function testInvalidMergesAreRejectedAndNothingChanges(callable $pair, string $message): void
    {
        [$guest, $member] = $pair($this);

        try {
            $this->merger()->mergeGuestIntoMember($guest, $member);
            self::fail('Slučování se musí odmítnout.');
        } catch (DomainException $e) {
            self::assertStringContainsString($message, $e->getMessage());
        }
        self::assertArrayNotHasKey('remove', $this->calls->getArrayCopy());
        self::assertContains($this->host, $this->party->getPlayers());
    }

    public function testGuestAndMemberFromTheSamePlayCannotBeMerged(): void
    {
        $this->sharedPlays = 2;

        try {
            $this->merger()->mergeGuestIntoMember($this->host, $this->bobPlayer);
            self::fail('Společné zápisy slučování blokují.');
        } catch (DomainException $e) {
            self::assertStringContainsString('2 stejných zápisech', $e->getMessage());
        }
        self::assertContains($this->host, $this->party->getPlayers(), 'nic se nezměnilo');
        self::assertArrayNotHasKey('remove', $this->calls->getArrayCopy());
    }

    public function testForgetInvitationsClearsTheGuestOnEveryInvitation(): void
    {
        $one = new PartyInvitation($this->party, $this->anna, guest: $this->host);
        $two = new PartyInvitation($this->party, $this->anna, guest: $this->host);
        $this->invitations = [$one, $two];

        $this->merger()->forgetInvitations($this->host);

        self::assertSame([null, null], [$one->getGuest(), $two->getGuest()]);
    }
}
