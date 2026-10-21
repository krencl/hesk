<?php

declare(strict_types=1);

namespace App\Legacy;

/** Přehled změn jednoho běhu importu podle entit: created / updated / unchanged / deleted. */
final class ImportReport
{
    public const KINDS = ['created', 'updated', 'unchanged', 'deleted'];

    /** @var array<string, array<string, int>> */
    public array $entities = [];
    public bool $dryRun = false;
    /** @var list<string> podrobnosti změn („tabulka #id: sloupec: před → po“), pro výpis s -v */
    public array $changes = [];

    public function count(string $entity, string $kind, int $n = 1): void
    {
        $this->entities[$entity] ??= array_fill_keys(self::KINDS, 0);
        $this->entities[$entity][$kind] += $n;
    }

    public function total(string $kind): int
    {
        return array_sum(array_column($this->entities, $kind));
    }

    /** Nic se nezměnilo (ani nepřidalo, ani neodebralo). */
    public function isNoop(): bool
    {
        return 0 === $this->total('created') + $this->total('updated') + $this->total('deleted');
    }
}
