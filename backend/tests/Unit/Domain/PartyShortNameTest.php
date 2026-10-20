<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain;

use App\Domain\PartyShortName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PartyShortNameTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function names(): iterable
    {
        yield 'two words' => ['Viki Marek', 'VM'];
        yield 'special character between words' => ['Viki + Marek', 'VM'];
        yield 'conjunction is skipped' => ['Viki a Marek', 'VM'];
        yield 'preposition is skipped' => ['Pátek u Kuby', 'PK'];
        yield 'only the first two words count' => ['Pátek večer u Kuby', 'PV'];
        yield 'single word gives two letters' => ['Deskobraní', 'De'];
        yield 'single upper case word' => ['ŠACH', 'Ša'];
        yield 'diacritics are kept' => ['čtvrteční hry', 'ČH'];
        yield 'special characters are dropped' => ['  --Hry!!  ', 'Hr'];
        yield 'digits count as letters' => ['Hráči 2024', 'H2'];
        yield 'one letter name' => ['X', 'X'];
        yield 'only filler words falls back to them' => ['a u', 'AU'];
        yield 'nothing usable' => ['+++', '?'];
    }

    #[DataProvider('names')]
    public function testGenerate(string $name, string $expected): void
    {
        $short = PartyShortName::generate($name);

        self::assertSame($expected, $short);
        self::assertLessThanOrEqual(PartyShortName::MAX_LENGTH, mb_strlen($short));
    }
}
