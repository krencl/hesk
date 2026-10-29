<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Bgg;

use App\Service\Bgg\CzechName;
use PHPUnit\Framework\TestCase;

/** Odhad českého názvu z alternativních názvů BGG; názvy v testech jsou skutečné alternativy z BGG. */
final class CzechNameTest extends TestCase
{
    public function testNamesWithLettersOnlyCzechHasAreConfident(): void
    {
        self::assertSame(['Na křídlech'], CzechName::confident('Wingspan', ['Fesztáv', 'Na křídlech', 'Flügelschlag']));
        self::assertSame(['7 divů světa'], CzechName::confident('7 Wonders', ['7 Cudów Świata', '7 divů světa']));
    }

    public function testLettersSharedWithSlovakAreConfidentButRankedBelowCzechOnes(): void
    {
        self::assertSame(['Čaj'], CzechName::confident('Tea', ['Čaj', 'Los Colonos de Catán']));
        self::assertSame(['Šachy a řeky', 'Čaj'], CzechName::candidates('Tea', ['Čaj', 'Šachy a řeky']), 'ř před č');
    }

    public function testOnlyIAndYAreOfferedButNotChosen(): void
    {
        $alternates = ['Los Colonos de Catán', 'Osadníci z Katanu', 'Catan: Základní hra'];

        self::assertSame([], CzechName::confident('Catan', $alternates), 'Domínio de Carcassonne by byla portugalština');
        self::assertSame(['Osadníci z Katanu', 'Catan: Základní hra'], CzechName::candidates('Catan', $alternates), 'á samotné nestačí, přípona původního názvu je až za jiným');
    }

    public function testNamesInOtherLanguagesOrScriptsAreRejected(): void
    {
        self::assertSame([], CzechName::candidates('Dominion', ['Dominion: Rozdarte Królestwo', 'Pandémie', 'Каркасон', 'Τα Κάστρα', 'Süße Šachy', 'Pandemic Légacy 🎲']));
    }

    public function testThePrimaryNameAndDuplicatesAreSkipped(): void
    {
        self::assertSame([], CzechName::candidates('Šperk', ['ŠPERK', 'Šperk']));
        self::assertSame(['Čáp'], CzechName::candidates('Dixit', ['Čáp', 'Čáp', ' Čáp ']));
    }
}
