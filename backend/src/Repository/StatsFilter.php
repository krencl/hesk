<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Filtr statistik podle her a tagů her: zápis projde, když hra patří mezi vybrané hry NEBO má některý z vybraných tagů
 * (`include*`; bez výběru projde vše) a zároveň hra není mezi vyřazenými hrami ani nemá žádný z vyřazených tagů (`exclude*`).
 */
final readonly class StatsFilter
{
    /**
     * @param list<string> $includeTags
     * @param list<string> $excludeTags
     * @param list<int>    $includeGames
     * @param list<int>    $excludeGames
     */
    public function __construct(
        public array $includeTags = [],
        public array $excludeTags = [],
        public array $includeGames = [],
        public array $excludeGames = [],
    ) {
    }
}
