<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Zařazení hry z katalogu do party. */
final readonly class GameLinkRequest
{
    public function __construct(
        #[Assert\Positive]
        public int $gameId = 0,
    ) {
    }
}
