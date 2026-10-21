<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Service\Bgg\BggClient;
use App\Service\Bgg\BggItem;
use App\Service\Bgg\BggUnavailableException;

/**
 * BGG v testech: hry a počítadla volání jsou statické, protože `KernelBrowser` mezi požadavky restartuje kontejner.
 */
final class FakeBggClient extends BggClient
{
    /** @var array<int, BggItem> */
    public static array $games = [];
    public static bool $down = false;
    public static int $searchCalls = 0;
    public static int $thingCalls = 0;
    public static int $imageCalls = 0;

    public static function reset(): void
    {
        self::$games = [];
        self::$down = false;
        self::$searchCalls = self::$thingCalls = self::$imageCalls = 0;
    }

    public function __construct()
    {
    }

    public function isConfigured(): bool
    {
        return !self::$down;
    }

    public function search(string $query): array
    {
        ++self::$searchCalls;
        $this->guard();

        return array_values(array_map(
            static fn (BggItem $g): BggItem => new BggItem($g->id, $g->name, $g->year),
            array_filter(self::$games, static fn (BggItem $g): bool => str_contains(mb_strtolower($g->name), mb_strtolower($query))),
        ));
    }

    public function thing(int $id): ?BggItem
    {
        ++self::$thingCalls;
        $this->guard();

        return self::$games[$id] ?? null;
    }

    public function fetchImage(string $url): array
    {
        ++self::$imageCalls;
        $this->guard();

        return ['content' => 'fake-image', 'type' => 'image/jpeg'];
    }

    private function guard(): void
    {
        if (self::$down) {
            throw new BggUnavailableException('down');
        }
    }
}
