<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Pojmenovaná položka hry (rozšíření, postava). S `id` se existující položka přejmenuje, bez něj se vytvoří (nebo spáruje podle názvu). */
final readonly class NamedItemRequest
{
    public function __construct(
        #[Assert\NotBlank, Assert\Length(max: 150)]
        public string $name = '',
        public ?int $id = null,
    ) {
    }
}
