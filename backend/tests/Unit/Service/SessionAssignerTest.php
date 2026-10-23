<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\GameSession;
use App\Entity\Party;
use App\Entity\Play;
use App\Entity\Game;
use App\Entity\User;
use App\Enum\ScoreType;
use App\Service\SessionAssigner;
use App\Tests\Support\InMemorySessions;
use App\Tests\Support\Fixtures;
use PHPUnit\Framework\TestCase;

final class SessionAssignerTest extends TestCase
{
    use InMemorySessions;

    private Party $party;

    protected function setUp(): void
    {
        $this->party = Party::create('P', new User('g', 'a@example.com'));
    }

    /** @param list<GameSession> $existing */
    private function assigner(array $existing): SessionAssigner
    {
        return new SessionAssigner($this->sessionRepository(static fn (): array => $existing));
    }

    public function testCreatesNewSessionWhenNoneExists(): void
    {
        $s = $this->assigner([])->assign($this->party, Fixtures::date('2026-01-01 18:00'), Fixtures::date('2026-01-01 19:00'));

        self::assertEquals(Fixtures::date('2026-01-01 18:00'), $s->getStartedAt());
    }

    public function testJoinsRecentSessionAndExtendsIt(): void
    {
        $existing = new GameSession($this->party, 1, Fixtures::date('2026-01-01 18:00'), Fixtures::date('2026-01-01 19:00'));

        $s = $this->assigner([$existing])->assign($this->party, Fixtures::date('2026-01-02 10:00'), Fixtures::date('2026-01-02 11:00'));

        self::assertSame($existing, $s);
        self::assertEquals(Fixtures::date('2026-01-02 11:00'), $existing->getEndedAt());
    }

    public function testStartsNewSessionAfterLongGap(): void
    {
        $existing = new GameSession($this->party, 1, Fixtures::date('2026-01-01 18:00'), Fixtures::date('2026-01-01 19:00'));

        $s = $this->assigner([$existing])->assign($this->party, Fixtures::date('2026-01-05 18:00'), Fixtures::date('2026-01-05 19:00'));

        self::assertNotSame($existing, $s);
    }

    public function testNewSessionGetsNumberAfterHighestExisting(): void
    {
        $first = new GameSession($this->party, 1, Fixtures::date('2026-01-01 18:00'), Fixtures::date('2026-01-01 19:00'));
        $gap = new GameSession($this->party, 69, Fixtures::date('2026-02-01 18:00'), Fixtures::date('2026-02-01 19:00'));

        $s = $this->assigner([$gap, $first])->assign($this->party, Fixtures::date('2026-03-01 18:00'), Fixtures::date('2026-03-01 19:00'));

        self::assertSame(70, $s->getNumber());
        self::assertSame(1, $this->assigner([])->assign($this->party, Fixtures::date('2026-03-01 18:00'), Fixtures::date('2026-03-01 19:00'))->getNumber());
    }

    public function testExplicitNumberJoinsExistingSessionOrCreatesNewOne(): void
    {
        $existing = new GameSession($this->party, 95, Fixtures::date('2026-01-01 18:00'), Fixtures::date('2026-01-01 19:00'));
        $assigner = $this->assigner([$existing]);

        // i daleko od seance se zápis zařadí tam, kam uživatel řekl (a seanci rozšíří)
        $joined = $assigner->assign($this->party, Fixtures::date('2026-03-01 18:00'), Fixtures::date('2026-03-01 19:00'), 95);
        self::assertSame($existing, $joined);
        self::assertEquals(Fixtures::date('2026-03-01 19:00'), $existing->getEndedAt());

        $created = $assigner->assign($this->party, Fixtures::date('2026-03-01 18:00'), Fixtures::date('2026-03-01 19:00'), 120);
        self::assertNotSame($existing, $created);
        self::assertSame(120, $created->getNumber());
    }

    public function testSuggestKeepsNumberWithin24HoursAndIncrementsAfterwards(): void
    {
        $existing = new GameSession($this->party, 95, Fixtures::date('2026-01-01 18:00'), Fixtures::date('2026-01-01 19:00'));
        $assigner = $this->assigner([$existing]);

        $same = $assigner->suggest($this->party, Fixtures::date('2026-01-02 18:30'), Fixtures::date('2026-01-02 19:30'));
        self::assertSame(95, $same['number']);
        self::assertSame($existing, $same['session']);

        $next = $assigner->suggest($this->party, Fixtures::date('2026-02-01 18:00'), Fixtures::date('2026-02-01 19:00'));
        self::assertSame(96, $next['number']);
        self::assertNull($next['session']);
        self::assertEquals(Fixtures::date('2026-01-01 19:00'), $existing->getEndedAt(), 'návrh nic nemění');

        self::assertSame(1, $this->assigner([])->suggest($this->party, Fixtures::date('2026-02-01 18:00'), Fixtures::date('2026-02-01 19:00'))['number']);
    }

    public function testRecalculateUsesRemainingPlaysOrFlagsEmpty(): void
    {
        $session = new GameSession($this->party, 1, Fixtures::date('2026-01-01 10:00'), Fixtures::date('2026-01-03 10:00'));
        $game = new Game('G', ScoreType::MostPoints);
        $play = new Play($this->party, $game, Fixtures::date('2026-01-01 12:00'), Fixtures::date('2026-01-01 13:00'), $session);
        $assigner = $this->assigner([]);

        self::assertFalse($assigner->recalculate($session, [$play]));
        self::assertEquals(Fixtures::date('2026-01-01 13:00'), $session->getEndedAt());
        self::assertTrue($assigner->recalculate($session, []));
    }
}
