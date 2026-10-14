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

    public function assign(Party $party, \DateTimeImmutable $start, \DateTimeImmutable $end): GameSession
    {
        $lastNumber = 0;
        foreach ($this->sessions->findByParty($party) as $session) {
            if ($session->accepts($start, $end)) {
                $session->extendTo($start, $end);

                return $session;
            }
            $lastNumber = max($lastNumber, $session->getNumber());
        }

        return new GameSession($party, $lastNumber + 1, $start, $end);
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
