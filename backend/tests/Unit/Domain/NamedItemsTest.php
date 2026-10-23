<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain;

use App\Domain\NamedItems;
use PHPUnit\Framework\TestCase;

final class NamedItemsTest extends TestCase
{
    public function testNormalizeTrimsDropsEmptyAndMergesDuplicatesKeepingTheFirst(): void
    {
        $items = [
            ['id' => 4, 'name' => ' Čeněk '],
            ['id' => null, 'name' => 'cenek'],
            ['name' => ''],
            ['id' => 7, 'name' => "  \t"],
            ['name' => 'Eva'],
        ];

        self::assertSame([['id' => 4, 'name' => 'Čeněk'], ['id' => null, 'name' => 'Eva']], NamedItems::normalize($items));
    }

    public function testUniqueNamesKeepsTheOriginalOrderAndFirstSpelling(): void
    {
        self::assertSame(['víkend', 'Doma'], NamedItems::uniqueNames([' víkend', 'Doma', 'doma', ' ', 'Vikend']));
        self::assertSame([], NamedItems::uniqueNames([]));
    }

    public function testResolveReusesExistingEntitiesByKeyAndCreatesOnlyTheMissingOnes(): void
    {
        $existing = [(object) ['name' => 'Doma'], (object) ['name' => 'Víkend']];
        $created = [];

        $resolved = NamedItems::resolve(
            ['doma', 'DOMA', 'vikend', ' Hospoda ', 'hospoda', ''],
            $existing,
            static fn (object $t): string => $t->name,
            static function (string $name) use (&$created): object {
                return $created[] = (object) ['name' => $name];
            },
        );

        self::assertSame([$existing[0], $existing[1], $created[0]], $resolved, 'každá entita jednou, v pořadí názvů');
        self::assertSame(['Hospoda'], array_map(static fn (object $t): string => $t->name, $created));
    }
}
