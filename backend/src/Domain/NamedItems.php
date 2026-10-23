<?php

declare(strict_types=1);

namespace App\Domain;

/** Úklid seznamů pojmenovaných položek (rozšíření, postavy, hráči hry, tagy): ořezat, zahodit prázdné, sloučit opakované. */
final class NamedItems
{
    /**
     * Položky s ořezaným názvem; prázdné a opakované názvy (podle {@see NameKey}) se zahodí, vyhrává první.
     *
     * @param list<array{id?: ?int, name: string}> $items
     *
     * @return list<array{id: ?int, name: string}>
     */
    public static function normalize(array $items): array
    {
        $seen = [];
        $wanted = [];
        foreach ($items as $item) {
            $name = trim($item['name']);
            $key = NameKey::of($name);
            if ('' === $name || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $wanted[] = ['id' => $item['id'] ?? null, 'name' => $name];
        }

        return $wanted;
    }

    /**
     * @param list<string> $names
     *
     * @return list<string> ořezané názvy bez prázdných a opakovaných (podle {@see NameKey}), v původním pořadí
     */
    public static function uniqueNames(array $names): array
    {
        return array_column(self::normalize(array_map(static fn (string $n): array => ['name' => $n], $names)), 'name');
    }

    /**
     * Ke každému jménu najde existující entitu podle {@see NameKey}, jinak ji vytvoří; každá entita je ve výsledku jednou.
     *
     * @template T of object
     *
     * @param list<string>        $names    názvy (nemusí být očištěné)
     * @param iterable<T>         $existing kandidáti z databáze
     * @param callable(T): string $nameOf   název entity
     * @param callable(string): T $create   založí (a uloží) chybějící entitu
     *
     * @return list<T>
     */
    public static function resolve(array $names, iterable $existing, callable $nameOf, callable $create): array
    {
        $byKey = [];
        foreach ($existing as $entity) {
            $byKey[NameKey::of($nameOf($entity))] ??= $entity;
        }
        $resolved = [];
        foreach (self::uniqueNames($names) as $name) {
            $entity = $byKey[NameKey::of($name)] ??= $create($name);
            $resolved[spl_object_id($entity)] = $entity;
        }

        return array_values($resolved);
    }
}
