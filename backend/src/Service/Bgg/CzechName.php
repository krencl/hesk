<?php

declare(strict_types=1);

namespace App\Service\Bgg;

/**
 * Odhad českého názvu hry z alternativních názvů na BGG. API u názvů jazyk neuvádí, takže se pozná jen podle znaků:
 * název musí být latinkou a bez písmen s diakritikou, která česká abeceda nemá (ä, ł, ñ, ö, …). Pořadí jistoty:
 * 1. písmeno jen české (ě, ř, ů),
 * 2. písmeno sdílené se slovenštinou (č, š, ž, ď, ť, ň; mají je i jiné jazyky, např. chorvatština),
 * 3. jen í/ý (španělština, portugalština…; „Osadníci z Katanu“ i „Domínio de Carcassonne“), samotné á, é, ó, ú nestačí.
 * Automaticky se vybírá jen 1. a 2., názvy ze 3. skupiny se jen nabízejí k ruční volbě.
 */
final class CzechName
{
    private const CZECH_ONLY = 'ěřů';
    private const SHARED = 'čšžďťň';
    private const WEAK = 'íýáéóú';

    /**
     * Názvy, které by mohly být české, od nejjistějšího; uvnitř skupiny nejdřív ty, které nejsou jen příponou
     * původního názvu („Catan: Základní hra“), a jinak v pořadí z BGG.
     *
     * @param list<string> $alternates
     *
     * @return list<string>
     */
    public static function candidates(string $primary, array $alternates): array
    {
        return array_column(self::ranked($primary, $alternates), 1);
    }

    /**
     * Názvy, kterým se dá věřit natolik, že se předvyplní (1. a 2. skupina).
     *
     * @param list<string> $alternates
     *
     * @return list<string>
     */
    public static function confident(string $primary, array $alternates): array
    {
        return array_column(array_filter(self::ranked($primary, $alternates), static fn (array $r): bool => $r[0] <= 1), 1);
    }

    /**
     * @param list<string> $alternates
     *
     * @return list<array{int, string}> dvojice (skupina, název)
     */
    private static function ranked(string $primary, array $alternates): array
    {
        $ranked = [];
        foreach (array_values(array_unique(array_map('trim', $alternates))) as $order => $name) {
            $rank = self::rank($name);
            if (null === $rank || 0 === strcasecmp($name, $primary)) {
                continue;
            }
            $variant = '' !== $primary && str_starts_with(mb_strtolower($name), mb_strtolower($primary));
            $ranked[] = [[$rank, $variant ? 1 : 0, $order], [$rank, $name]];
        }
        usort($ranked, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return array_column($ranked, 1);
    }

    /** 0 = písmeno jen české, 1 = písmeno sdílené se slovenštinou, 2 = jen í/ý, null = nevypadá česky. */
    private static function rank(string $name): ?int
    {
        $letters = preg_replace('/[^\p{L}]/u', '', mb_strtolower($name)) ?? '';
        if ('' === $letters || preg_match('/[^a-z'.self::CZECH_ONLY.self::SHARED.self::WEAK.']/u', $letters)) {
            return null;
        }

        return match (true) {
            (bool) preg_match('/['.self::CZECH_ONLY.']/u', $letters) => 0,
            (bool) preg_match('/['.self::SHARED.']/u', $letters) => 1,
            (bool) preg_match('/[íý]/u', $letters) => 2,
            default => null,
        };
    }
}
