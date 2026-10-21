<?php

declare(strict_types=1);

namespace App\Service\Bgg;

/** Hra z BoardGameGeek; `imageUrl` je adresa originálu na BGG CDN (vyplněná jen u detailu, ne u hledání). */
final readonly class BggItem
{
    public function __construct(
        public int $id,
        public string $name,
        public ?int $year = null,
        public ?string $imageUrl = null,
    ) {
    }
}
