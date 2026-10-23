<?php

declare(strict_types=1);

namespace App\Domain;

use function Symfony\Component\String\u;

/**
 * Klíč pro porovnávání jmen (hráči, tagy, rozšíření, postavy): bez okolních mezer, bez diakritiky a malými písmeny.
 * Odpovídá kolaci databáze (`utf8mb4_general_ci` nerozlišuje velikost písmen ani diakritiku), takže dvě jména
 * se stejným klíčem by v DB porušila unikát; podle klíče se tedy jména porovnávají a slučují vždy.
 */
final class NameKey
{
    public static function of(string $name): string
    {
        return (string) u(trim($name))->ascii()->lower();
    }
}
