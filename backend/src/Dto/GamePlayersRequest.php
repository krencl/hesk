<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Vlastní hráči hry v jedné partě (`id` = id hráče party). */
final readonly class GamePlayersRequest
{
    /** @param list<NamedItemRequest> $players */
    public function __construct(
        #[Assert\Valid]
        public array $players = [],
    ) {
    }
}
