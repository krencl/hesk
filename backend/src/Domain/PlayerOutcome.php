<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class PlayerOutcome
{
    public function __construct(
        public int $userId,
        public ?float $score,
        public int $rank,
        public bool $winner,
    ) {
    }
}
