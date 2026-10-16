<?php

declare(strict_types=1);

namespace App\Dto;

final readonly class PlayerRequest
{
    public function __construct(
        public int $playerId,
        public ?float $score = null,
        public ?int $rank = null,
        public ?bool $winner = null,
        public bool $starting = false,
    ) {
    }
}
