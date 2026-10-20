<?php

declare(strict_types=1);

namespace App\Domain;

/** Zkratka party odvozená z názvu (kulaté tlačítko v hlavičce). */
final class PartyShortName
{
    /** Nejdelší zkratka; odvozená má nejvýše dva znaky. */
    public const MAX_LENGTH = 3;

    /** Spojky a předložky, které se ve víceslovném názvu přeskakují („Viki a Marek“ → VM). */
    private const FILLER_WORDS = ['a', 'i', 'u', 'o', 'v', 's', 'z', 'k', 'na', 'do', 'od', 'po', 'za', 'ze', 'se', 've', 'ke', 'pro', 'the', 'and', 'of'];

    /**
     * Počáteční písmena prvních dvou slov („Viki a Marek“ → „VM“, „Viki + Marek“ → „VM“),
     * u jednoho slova jeho první dvě písmena („Deskobraní“ → „De“). Speciální znaky se vynechávají.
     */
    public static function generate(string $name): string
    {
        preg_match_all('/[\p{L}\p{N}]+/u', $name, $matches);
        $words = $matches[0];
        $significant = array_values(array_filter($words, static fn (string $w): bool => !\in_array(mb_strtolower($w), self::FILLER_WORDS, true)));
        $words = [] === $significant ? $words : $significant;

        if ([] === $words) {
            return '?';
        }
        if (1 === \count($words)) {
            $letters = mb_str_split($words[0]);

            return mb_strtoupper($letters[0]).mb_strtolower($letters[1] ?? '');
        }

        return mb_strtoupper(mb_substr($words[0], 0, 1)).mb_strtoupper(mb_substr($words[1], 0, 1));
    }
}
