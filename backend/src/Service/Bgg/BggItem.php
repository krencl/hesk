<?php

declare(strict_types=1);

namespace App\Service\Bgg;

/**
 * Hra z BoardGameGeek; `imageUrl` je adresa originálu na BGG CDN (vyplněná jen u detailu, ne u hledání), stejně jako
 * `alternateNames` (BGG k nim neuvádí jazyk, český název se z nich odhaduje v `CzechName`).
 */
final readonly class BggItem
{
    /** @param list<string> $alternateNames */
    public function __construct(
        public int $id,
        public string $name,
        public ?int $year = null,
        public ?string $imageUrl = null,
        public array $alternateNames = [],
    ) {
    }
}
