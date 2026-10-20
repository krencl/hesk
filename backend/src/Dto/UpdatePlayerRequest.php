<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Změna hosta: jméno a/nebo zda se nabízí při zápisu hry; co se neposlá, zůstane. */
final readonly class UpdatePlayerRequest
{
    public function __construct(
        #[Assert\Length(max: 100)]
        public ?string $name = null,
        public ?bool $visible = null,
    ) {
    }
}
