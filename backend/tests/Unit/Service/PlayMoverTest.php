<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Domain\Exception\DomainException;
use App\Domain\NamedItems;
use App\Entity\GameSession;
use App\Entity\Party;
use App\Entity\Play;
use App\Entity\PlayResult;
use App\Entity\PlayTag;
use App\Entity\Player;
use App\Entity\User;
use App\Enum\ScoreType;
use App\Repository\PlayRepository;
use App\Repository\PlayTagRepository;
use App\Service\PlayMover;
use App\Service\PlayerMapping;
use App\Service\SessionAssigner;
use App\Tests\Support\DoctrineMocks;
use App\Tests\Support\Fixtures;
use App\Tests\Support\InMemorySessions;
use PHPUnit\Framework\TestCase;

/** Přesun zápisu mezi partami: databáze je mock, ověřuje se převod hráčů, her, tagů a seancí. */
final class PlayMoverTest extends TestCase
{
    use DoctrineMocks;
    use InMemorySessions;

    private User $anna;
    private User $bob;
    private Party $source;
    private Party $target;
    private \ArrayObject $calls;
    /** @var list<GameSession> seance, které „v databázi“ jsou */
    private array $sessions = [];
    /** @var list<Play> zápisy původní seance */
    private array $playsInSession = [];

    protected function setUp(): void
    {
        $this->anna = Fixtures::user('Anna');
        $this->bob = Fixtures::user('Bob');
        $this->source = Fixtures::party($this->anna, 'Pátek');
        Fixtures::member($this->source, $this->bob);
        $this->target = Fixtures::party($this->anna, 'Sobota');
        $this->calls = new \ArrayObject();
        $this->sessions = [];
        $this->playsInSession = [];
    }

    private function mover(): PlayMover
    {
        $plays = $this->createStub(PlayRepository::class);
        $plays->method('findBySession')->willReturnCallback(fn (): array => $this->playsInSession);
        $tags = $this->createStub(PlayTagRepository::class);
        $tags->method('findOrCreate')->willReturnCallback(fn (Party $party, array $names): array => NamedItems::resolve(
            $names,
            [],
            static fn (PlayTag $t): string => $t->getName(),
            static fn (string $name): PlayTag => new PlayTag($party, $name),
        ));
        $sessions = new SessionAssigner($this->sessionRepository(fn (): array => $this->sessions));

        return new PlayMover($this->mockEm([], $this->calls), $sessions, $plays, $tags);
    }

    /** @param list<Player> $players */
    private function play(array $players, string $start = '2026-03-01T18:00:00+00:00'): Play
    {
        $game = Fixtures::game($this->source, 'Azul', ScoreType::MostPoints);
        $session = Fixtures::id(new GameSession($this->source, 4, Fixtures::date($start), Fixtures::date($start)));
        $play = Fixtures::id(new Play($this->source, $game, Fixtures::date($start), Fixtures::date($start), $session));
        $play->replaceResults(array_map(static fn (Player $p, int $i): PlayResult => new PlayResult($play, $p, 10.0 - $i, $i + 1, 0 === $i), $players, array_keys($players)));
        $this->sessions = [$session];
        $this->playsInSession = [$play];

        return $play;
    }

    /** @return list<string> */
    private function names(Play $play): array
    {
        return array_values(array_map(static fn (PlayResult $r): string => $r->getPlayer()->getName(), $play->getResults()->toArray()));
    }

    public function testMovesPlayAndMapsMembersByAccountAndHostsByName(): void
    {
        Fixtures::member($this->target, $this->bob);
        $petrTarget = Fixtures::guest($this->target, 'petr'); // host stejného jména, jiná velikost písmen
        $petr = Fixtures::guest($this->source, 'Petr');
        $play = $this->play([Fixtures::player($this->source, $this->anna), Fixtures::player($this->source, $this->bob), $petr]);
        $before = $this->target->getPlayers()->count();

        $created = $this->mover()->move($play, $this->target);

        self::assertSame([], $created);
        self::assertSame($this->target, $play->getParty());
        self::assertSame(['Anna', 'Bob', 'petr'], $this->names($play));
        self::assertSame($petrTarget, $play->getResults()[2]->getPlayer(), 'host téhož jména = stejná osoba');
        self::assertSame($before, $this->target->getPlayers()->count(), 'nic nevzniklo');
        foreach ($play->getResults() as $result) {
            self::assertSame($this->target, $result->getPlayer()->getParty());
        }
    }

    public function testMissingPlayersAreCreatedAsGuestsAndKeepVisibility(): void
    {
        $jana = Fixtures::guest($this->source, 'Jana');
        $jana->setVisible(false);
        $play = $this->play([Fixtures::player($this->source, $this->bob), $jana]);

        $created = $this->mover()->move($play, $this->target);

        self::assertSame(['Bob', 'Jana'], $created);
        self::assertSame(['Bob', 'Jana'], $this->names($play));
        [$bob, $janaTarget] = array_map(static fn (PlayResult $r): Player => $r->getPlayer(), $play->getResults()->toArray());
        self::assertNull($bob->getUser(), 've cílové partě je Bob jen host');
        self::assertTrue($bob->isVisible());
        self::assertFalse($janaTarget->isVisible());
        self::assertContains($janaTarget, $this->target->getPlayers()->toArray());
    }

    public function testNameTakenByAnotherMemberGetsANumber(): void
    {
        $other = Fixtures::member($this->target, Fixtures::user('Bob', 'bob2@example.com')); // jiný člověk se stejnou přezdívkou
        $play = $this->play([Fixtures::player($this->source, $this->bob)]);

        $created = $this->mover()->move($play, $this->target);

        self::assertSame(['Bob 2'], $created);
        self::assertSame('Bob', Fixtures::player($this->target, $other)->getName());
    }

    public function testTwoSourcePlayersNeverShareOneTargetPlayer(): void
    {
        $this->target->addGuest('Bob 2');
        Fixtures::persist($this->target);
        Fixtures::member($this->target, Fixtures::user('Bob', 'bob2@example.com'));
        $host = Fixtures::guest($this->source, 'Bob 2');
        $play = $this->play([Fixtures::player($this->source, $this->bob), $host]);

        $this->mover()->move($play, $this->target);

        $players = array_map(static fn (PlayResult $r): Player => $r->getPlayer(), $play->getResults()->toArray());
        self::assertNotSame($players[0], $players[1]);
        self::assertSame(['Bob 3', 'Bob 2'], $this->names($play));
    }

    public function testGameIsLinkedToTargetPartyWithItsGamePlayers(): void
    {
        $boss = Fixtures::guest($this->source, 'Boss');
        $play = $this->play([Fixtures::player($this->source, $this->anna), $boss]);
        $this->source->findGameLink($play->getGame())->getPlayers()->add($boss);
        self::assertFalse($this->target->hasGame($play->getGame()));

        $this->mover()->move($play, $this->target);

        $link = $this->target->findGameLink($play->getGame());
        self::assertNotNull($link);
        self::assertSame(['Boss'], array_map(static fn (Player $p): string => $p->getName(), $link->getPlayers()->toArray()));
        self::assertTrue($this->source->hasGame($play->getGame()), 've zdrojové partě hra zůstává');
    }

    public function testTagsAreRecreatedInTheTargetParty(): void
    {
        $play = $this->play([Fixtures::player($this->source, $this->anna)]);
        $play->setTags([new PlayTag($this->source, 'víkend'), new PlayTag($this->source, 'liga')]);

        $this->mover()->move($play, $this->target);

        self::assertCount(2, $play->getTags());
        foreach ($play->getTags() as $tag) {
            self::assertSame($this->target, $tag->getParty());
        }
    }

    public function testPlayJoinsTargetSessionAndEmptiedSourceSessionIsRemoved(): void
    {
        $play = $this->play([Fixtures::player($this->source, $this->anna)]);
        $old = $play->getSession();
        $existing = new GameSession($this->target, 12, Fixtures::date('2026-02-28T20:00:00+00:00'), Fixtures::date('2026-02-28T22:00:00+00:00'));
        $this->sessions[] = $existing;

        $this->mover()->move($play, $this->target);

        self::assertSame($existing, $play->getSession(), 'do 24 h od seance cílové party');
        self::assertContains($old, $this->calls['remove'], 'prázdná seance zdrojové party zanikne');
        self::assertEquals(Fixtures::date('2026-03-01T18:00:00+00:00'), $existing->getEndedAt());
    }

    public function testNewSessionIsCreatedInTargetPartyAndRemainingSourceSessionIsRecalculated(): void
    {
        $play = $this->play([Fixtures::player($this->source, $this->anna)], '2026-03-02T18:00:00+00:00');
        $old = $play->getSession();
        $stay = Fixtures::id(new Play($this->source, $play->getGame(), Fixtures::date('2026-03-01T18:00:00+00:00'), Fixtures::date('2026-03-01T19:00:00+00:00'), $old));
        $this->playsInSession = [$play, $stay];
        $this->target->addGame($play->getGame());

        $this->mover()->move($play, $this->target);

        self::assertNotSame($old, $play->getSession());
        self::assertSame($this->target, $play->getSession()->getParty());
        self::assertSame(1, $play->getSession()->getNumber());
        self::assertEquals(Fixtures::date('2026-03-01T19:00:00+00:00'), $old->getEndedAt(), 'seance se zkrátí na zápisy, které v ní zůstaly');
        self::assertNotContains($old, $this->calls['remove'] ?? []);
    }

    /** @return list<string> „Jméno → cílové jméno“ + druh změny, jak ji ukazuje náhled */
    private function describe(Play $play): array
    {
        return array_map(static fn (PlayerMapping $m): string => sprintf(
            '%s → %s (%s%s)',
            $m->from->getName(),
            $m->targetName(),
            null !== $m->from->getUser() ? 'člen' : 'host',
            ($m->targetIsMember() ? '→člen' : '→host').($m->isCreated() ? ', nový' : ''),
        ), $this->mover()->plan($play, $this->target));
    }

    public function testPlanDescribesEveryPlayerWithoutChangingAnything(): void
    {
        Fixtures::member($this->target, $this->bob);
        $anna = Fixtures::player($this->source, $this->anna);
        $lukas = Fixtures::member($this->source, Fixtures::user('Lukáš'));
        Fixtures::guest($this->target, 'Káťa');
        $play = $this->play([$anna, Fixtures::player($this->source, $this->bob), Fixtures::player($this->source, $lukas), Fixtures::guest($this->source, 'Káťa'), Fixtures::guest($this->source, 'Nový')]);
        $playersBefore = $this->target->getPlayers()->count();

        self::assertSame([
            'Anna → Anna (člen→člen)',
            'Bob → Bob (člen→člen)',
            'Lukáš → Lukáš (člen→host, nový)',
            'Káťa → Káťa (host→host)',
            'Nový → Nový (host→host, nový)',
        ], $this->describe($play));
        self::assertSame($playersBefore, $this->target->getPlayers()->count());
        self::assertSame($this->source, $play->getParty());
        self::assertSame([], $this->calls->getArrayCopy(), 'nic se neukládá');
    }

    public function testPlanShowsAMemberMatchedToAnExistingTargetGuest(): void
    {
        Fixtures::guest($this->target, 'bob');
        $play = $this->play([Fixtures::player($this->source, $this->bob)]);

        self::assertSame(['Bob → bob (člen→host)'], $this->describe($play));
    }

    public function testPlanNumbersNamesTakenByAnotherMemberAndByOtherNewGuests(): void
    {
        Fixtures::member($this->target, Fixtures::user('Bob', 'bob2@example.com'));
        $play = $this->play([Fixtures::player($this->source, $this->bob), Fixtures::guest($this->source, 'Bob 2')]);

        self::assertSame(['Bob → Bob 2 (člen→host, nový)', 'Bob 2 → Bob 2 2 (host→host, nový)'], $this->describe($play));
    }

    public function testMovingToTheSamePartyIsRejected(): void
    {
        $play = $this->play([Fixtures::player($this->source, $this->anna)]);

        $this->expectException(DomainException::class);
        $this->mover()->move($play, $this->source);
    }
}
