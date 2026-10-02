<?php

declare(strict_types=1);

namespace App\Domain;

/** Vstup za jednoho hráče; které pole se vyžaduje, určuje typ bodování hry. */
final readonly class PlayerInput
{
    public function __construct(
        public int $playerId,
        public ?float $score = null,
        public ?int $rank = null,
        public ?bool $winner = null,
    ) {
    }
}
