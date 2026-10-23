<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain;

use App\Domain\NameKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NameKeyTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function keys(): iterable
    {
        yield 'prostý název' => ['Anna', 'anna'];
        yield 'okolní mezery' => ["  Anna \t", 'anna'];
        yield 'diakritika' => ['Čeněk', 'cenek'];
        yield 'velká diakritika' => ['ŠIMON', 'simon'];
        yield 'vnitřní mezery zůstávají' => ['Viki a Marek', 'viki a marek'];
        yield 'prázdný' => ['   ', ''];
    }

    #[DataProvider('keys')]
    public function testKey(string $name, string $key): void
    {
        self::assertSame($key, NameKey::of($name));
    }

    public function testNamesDifferingOnlyInCaseAccentsOrOuterSpacesShareTheKey(): void
    {
        self::assertSame(NameKey::of('Čeněk'), NameKey::of(' čeněk'));
        self::assertSame(NameKey::of('Šimon'), NameKey::of('Simon'));
        self::assertNotSame(NameKey::of('Simon 2'), NameKey::of('Simon'));
    }
}
