<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\GameSession;
use App\Entity\Party;
use App\Repository\GameSessionRepository;

/** Repozitář seancí nad seznamem v paměti: stejná pravidla jako dotazy v `GameSessionRepository` (24h okno, nejnovější první, nejvyšší číslo). */
trait InMemorySessions
{
    /**
     * @param callable(): list<GameSession>                                          $sessions seance „v databázi“ (volá se při každém dotazu)
     * @param (callable(GameSession, \DateTimeImmutable, \DateTimeImmutable): bool)|null $accepts  kdy seance zápis přijme (výchozí `GameSession::accepts()` nad hranicemi v paměti)
     */
    protected function sessionRepository(callable $sessions, ?callable $accepts = null): GameSessionRepository
    {
        $accepts ??= static fn (GameSession $s, \DateTimeImmutable $start, \DateTimeImmutable $end): bool => $s->accepts($start, $end);
        $repo = $this->createStub(GameSessionRepository::class);
        $ofParty = static fn (Party $party): array => array_values(array_filter($sessions(), static fn (GameSession $s): bool => $s->getParty() === $party));

        $repo->method('findOneByNumber')->willReturnCallback(static function (Party $party, int $number) use ($ofParty): ?GameSession {
            foreach ($ofParty($party) as $session) {
                if ($session->getNumber() === $number) {
                    return $session;
                }
            }

            return null;
        });
        $repo->method('findAccepting')->willReturnCallback(static function (Party $party, \DateTimeImmutable $start, \DateTimeImmutable $end) use ($ofParty, $accepts): ?GameSession {
            $accepting = array_values(array_filter($ofParty($party), static fn (GameSession $s): bool => $accepts($s, $start, $end)));
            usort($accepting, static fn (GameSession $a, GameSession $b): int => $b->getEndedAt() <=> $a->getEndedAt());

            return $accepting[0] ?? null;
        });
        $repo->method('maxNumber')->willReturnCallback(static fn (Party $party): int => array_reduce($ofParty($party), static fn (int $max, GameSession $s): int => max($max, $s->getNumber()), 0));

        return $repo;
    }
}
