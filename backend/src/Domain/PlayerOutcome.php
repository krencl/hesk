<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class PlayerOutcome
{
    public function __construct(
        public int $playerId,
        public ?float $score,
        public int $rank,
        public bool $winner,
        public bool $starting = false,
        public ?int $characterId = null,
    ) {
    }
}
