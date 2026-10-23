<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GameSession;
use App\Entity\Party;
use App\Entity\Play;
use App\Repository\GameSessionRepository;

/** Přiřadí zápis k existující seanci (do 24 h od jejích hranic), nebo založí novou. */
final class SessionAssigner
{
    public function __construct(private readonly GameSessionRepository $sessions)
    {
    }

    /**
     * @param int|null $number výslovně zvolené číslo seance (existující seanci rozšíří, jinak založí novou s tímto číslem);
     *                         bez čísla se seance určí podle 24h pravidla
     */
    public function assign(Party $party, \DateTimeImmutable $start, \DateTimeImmutable $end, ?int $number = null): GameSession
    {
        if (null !== $number) {
            if (null !== $session = $this->sessions->findOneByNumber($party, $number)) {
                $session->extendTo($start, $end);

                return $session;
            }

            return new GameSession($party, $number, $start, $end);
        }

        ['session' => $match, 'number' => $next] = $this->match($party, $start, $end);
        if (null !== $match) {
            $match->extendTo($start, $end);

            return $match;
        }

        return new GameSession($party, $next, $start, $end);
    }

    /**
     * Do které seance by zápis v daném čase spadl, a jaké číslo by tedy dostal (nic se nemění).
     *
     * @return array{number: int, session: ?GameSession}
     */
    public function suggest(Party $party, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        ['session' => $match, 'number' => $next] = $this->match($party, $start, $end);

        return ['number' => $match?->getNumber() ?? $next, 'session' => $match];
    }

    /** @return array{session: ?GameSession, number: int} nalezená seance a číslo pro případ, že se bude zakládat nová */
    private function match(Party $party, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $session = $this->sessions->findAccepting($party, $start, $end);

        return null !== $session
            ? ['session' => $session, 'number' => $session->getNumber()]
            : ['session' => null, 'number' => $this->sessions->maxNumber($party) + 1];
    }

    /** Po smazání/přesunu zápisu přepočítá hranice seance; prázdnou seanci vrátí jako ke smazání. */
    public function recalculate(GameSession $session, iterable $plays): bool
    {
        $start = $end = null;
        foreach ($plays as $play) {
            /** @var Play $play */
            $start = null === $start ? $play->getStartedAt() : min($start, $play->getStartedAt());
            $end = null === $end ? $play->getEndedAt() : max($end, $play->getEndedAt());
        }
        if (null === $start || null === $end) {
            return true;
        }
        $session->resetBounds($start, $end);

        return false;
    }
}
