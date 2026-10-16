<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Exception\DomainException;
use App\Enum\ScoreType;

/**
 * Z hrubého vstupu hráčů spočítá pořadí a vítěze podle typu bodování hry.
 * Remízy sdílí pořadí (1, 1, 3), vítězové jsou všichni s pořadím 1. U her se skóre lze vítěze určit ručně
 * (`winner = true`) – např. když při shodě skóre vyhrává jen jeden z remízujících, nebo víc hráčů podle pravidel.
 * Začínající hráč je nejvýše jeden.
 */
final class ResultCalculator
{
    /**
     * @param list<PlayerInput> $inputs
     *
     * @return list<PlayerOutcome> ve stejném pořadí jako vstup
     */
    public function calculate(ScoreType $type, array $inputs): array
    {
        if ([] === $inputs) {
            throw new DomainException('Zápis musí obsahovat alespoň jednoho hráče.');
        }
        $ids = array_map(static fn (PlayerInput $i): int => $i->playerId, $inputs);
        if (\count($ids) !== \count(array_unique($ids))) {
            throw new DomainException('Hráč je v zápisu uveden vícekrát.');
        }

        if (\count(array_filter($inputs, static fn (PlayerInput $i): bool => $i->starting)) > 1) {
            throw new DomainException('Začínající hráč může být nejvýše jeden.');
        }

        $outcomes = match ($type) {
            ScoreType::MostPoints => $this->byScore($inputs, true),
            ScoreType::FewestPoints => $this->byScore($inputs, false),
            ScoreType::RankOrder => $this->byRank($inputs),
            ScoreType::WinnerOnly => $this->byWinnerFlag($inputs),
        };

        $byPlayer = [];
        foreach ($inputs as $input) {
            $byPlayer[$input->playerId] = $input;
        }

        return array_map(
            static fn (PlayerOutcome $o): PlayerOutcome => new PlayerOutcome($o->playerId, $o->score, $o->rank, $o->winner, $byPlayer[$o->playerId]->starting, $byPlayer[$o->playerId]->characterId),
            $outcomes,
        );
    }

    /**
     * @param list<PlayerInput> $inputs
     *
     * @return list<PlayerOutcome>
     */
    private function byScore(array $inputs, bool $higherWins): array
    {
        foreach ($inputs as $input) {
            if (null === $input->score) {
                throw new DomainException('U každého hráče je nutné zadat skóre.');
            }
        }
        $ranks = [];
        foreach ($inputs as $input) {
            $better = 0;
            foreach ($inputs as $other) {
                if ($higherWins ? $other->score > $input->score : $other->score < $input->score) {
                    ++$better;
                }
            }
            $ranks[$input->playerId] = $better + 1;
        }

        // Ručně označení vítězové mají přednost před automatikou podle skóre.
        $manual = array_filter($inputs, static fn (PlayerInput $i): bool => true === $i->winner);

        return array_map(
            static fn (PlayerInput $i): PlayerOutcome => new PlayerOutcome(
                $i->playerId,
                $i->score,
                $ranks[$i->playerId],
                [] === $manual ? 1 === $ranks[$i->playerId] : true === $i->winner,
            ),
            $inputs,
        );
    }

    /**
     * @param list<PlayerInput> $inputs
     *
     * @return list<PlayerOutcome>
     */
    private function byRank(array $inputs): array
    {
        foreach ($inputs as $input) {
            if (null === $input->rank || $input->rank < 1) {
                throw new DomainException('U každého hráče je nutné zadat pořadí (od 1).');
            }
        }
        if (!array_filter($inputs, static fn (PlayerInput $i): bool => 1 === $i->rank)) {
            throw new DomainException('Alespoň jeden hráč musí mít pořadí 1.');
        }

        return array_map(
            static fn (PlayerInput $i): PlayerOutcome => new PlayerOutcome($i->playerId, null, $i->rank, 1 === $i->rank),
            $inputs,
        );
    }

    /**
     * @param list<PlayerInput> $inputs
     *
     * @return list<PlayerOutcome>
     */
    private function byWinnerFlag(array $inputs): array
    {
        if (!array_filter($inputs, static fn (PlayerInput $i): bool => true === $i->winner)) {
            throw new DomainException('Je nutné určit alespoň jednoho vítěze.');
        }

        return array_map(
            static fn (PlayerInput $i): PlayerOutcome => new PlayerOutcome($i->playerId, null, true === $i->winner ? 1 : 2, true === $i->winner),
            $inputs,
        );
    }
}
