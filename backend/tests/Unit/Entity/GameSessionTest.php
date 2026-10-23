<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\GameSession;
use App\Entity\Party;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class GameSessionTest extends TestCase
{
    private function session(string $start, string $end): GameSession
    {
        return new GameSession(Party::create('P', new User('g', 'a@example.com')), 1, new \DateTimeImmutable($start), new \DateTimeImmutable($end));
    }

    public function testAcceptsPlayWithin24HoursAfterEnd(): void
    {
        $s = $this->session('2026-01-01 18:00', '2026-01-01 20:00');

        self::assertTrue($s->accepts(new \DateTimeImmutable('2026-01-02 19:59'), new \DateTimeImmutable('2026-01-02 21:00')));
        self::assertFalse($s->accepts(new \DateTimeImmutable('2026-01-02 20:01'), new \DateTimeImmutable('2026-01-02 21:00')));
    }

    public function testAcceptsBackdatedPlayWithin24HoursBeforeStart(): void
    {
        $s = $this->session('2026-01-05 18:00', '2026-01-05 20:00');

        self::assertTrue($s->accepts(new \DateTimeImmutable('2026-01-04 17:00'), new \DateTimeImmutable('2026-01-04 18:30')));
        self::assertFalse($s->accepts(new \DateTimeImmutable('2026-01-04 10:00'), new \DateTimeImmutable('2026-01-04 11:00')));
    }

    public function testAcceptanceWindowIsTheMaxGapAroundThePlay(): void
    {
        [$from, $to] = GameSession::acceptanceWindow(new \DateTimeImmutable('2026-01-05 18:00'), new \DateTimeImmutable('2026-01-05 19:30'));

        self::assertEquals(new \DateTimeImmutable('2026-01-04 18:00'), $from);
        self::assertEquals(new \DateTimeImmutable('2026-01-06 19:30'), $to);
    }

    public function testExtendKeepsWidestBounds(): void
    {
        $s = $this->session('2026-01-01 18:00', '2026-01-01 20:00');
        $s->extendTo(new \DateTimeImmutable('2026-01-01 19:00'), new \DateTimeImmutable('2026-01-02 01:00'));

        self::assertEquals(new \DateTimeImmutable('2026-01-01 18:00'), $s->getStartedAt());
        self::assertEquals(new \DateTimeImmutable('2026-01-02 01:00'), $s->getEndedAt());
    }
}
