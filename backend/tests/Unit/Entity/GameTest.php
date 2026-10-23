<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Game;
use App\Enum\ScoreType;
use PHPUnit\Framework\TestCase;

final class GameTest extends TestCase
{
    private function game(): Game
    {
        return new Game('Catan', ScoreType::MostPoints);
    }

    /** @param list<string> $names @return list<array{id: ?int, name: string}> */
    private static function named(array $names): array
    {
        return array_map(static fn (string $n): array => ['id' => null, 'name' => $n], $names);
    }

    /** @param list<array{id: ?int, name: string}> $addons @param list<array{id: ?int, name: string}> $characters */
    private function update(Game $g, array $addons = [], array $characters = [], ?string $shortName = null): void
    {
        $g->update('Catan', $shortName, ScoreType::MostPoints, null, null, null, $addons, $characters);
    }

    /** Id přidělí až databáze; v testu ho nastavíme ručně. */
    private static function persisted(object $entity, int $id): object
    {
        (new \ReflectionProperty($entity, 'id'))->setValue($entity, $id);

        return $entity;
    }

    /** @return list<string> */
    private function names(\Traversable|array $items): array
    {
        return array_values(array_map(static fn ($a) => $a->getName(), $items instanceof \Traversable ? iterator_to_array($items) : $items));
    }

    public function testAddonsWithoutIdAreMatchedByNameAndCleaned(): void
    {
        $game = $this->game();
        $this->update($game, self::named(['Seafarers', ' Cities ', 'seafarers', '']));
        self::assertSame(['Seafarers', 'Cities'], $this->names($game->getAddons()), 'blank and case-insensitive duplicates are dropped');

        $kept = $game->getAddons()->first();
        $this->update($game, self::named(['Seafarers', 'Traders']));

        self::assertSame(['Seafarers', 'Traders'], $this->names($game->getAddons()));
        self::assertSame($kept, $game->getAddons()->first(), 'existing addon instance is preserved');
    }

    public function testAddonsDifferingOnlyInAccentsAreDuplicatesAndMatchTheExistingOne(): void
    {
        $game = $this->game();
        $this->update($game, self::named(['Čaj', 'caj', 'Dráha']));
        self::assertSame(['Čaj', 'Dráha'], $this->names($game->getAddons()));

        $kept = $game->getAddons()->first();
        $this->update($game, self::named(['CAJ']));

        self::assertSame($kept, $game->getAddons()->first(), 'párování podle názvu ignoruje diakritiku');
        self::assertSame(['CAJ'], $this->names($game->getAddons()));
    }

    public function testItemWithIdIsRenamedInsteadOfRecreated(): void
    {
        $game = $this->game();
        $this->update($game, self::named(['Seafarers', 'Cities']));
        [$a, $b] = $game->getAddons()->toArray();
        self::persisted($a, 10);
        self::persisted($b, 11);

        $this->update($game, [['id' => 11, 'name' => 'Cities & Knights'], ['id' => 10, 'name' => 'Seafarers']]);

        self::assertSame([$a, $b], $game->getAddons()->toArray(), 'same instances, so plays keep their links');
        self::assertSame('Cities & Knights', $b->getName());
    }

    public function testRemovedAndNewItemsAreHandledIncludingCharacters(): void
    {
        $game = $this->game();
        $this->update($game, [], self::named(['Gigazaur', 'Kraken']));
        $giga = self::persisted($game->getCharacters()->first(), 1);

        $this->update($game, [], [['id' => 1, 'name' => 'Giga'], ['id' => null, 'name' => 'Alienoid']]);

        self::assertSame(['Giga', 'Alienoid'], $this->names($game->getCharacters()));
        self::assertSame($giga, $game->getCharacters()->first());
    }

    public function testShortNameIsTrimmedAndEmptyBecomesNull(): void
    {
        $game = $this->game();
        $this->update($game, shortName: '  Jungle ');
        self::assertSame('Jungle', $game->getShortName());
        $this->update($game, shortName: '   ');
        self::assertNull($game->getShortName());
    }

    public function testEmptyOptionalFieldsBecomeNull(): void
    {
        $game = $this->game();
        $game->update('Catan', null, ScoreType::WinnerOnly, '', '', '', []);

        self::assertNull($game->getImageUrl());
        self::assertNull($game->getDescription());
        self::assertNull($game->getCheatSheet());
        self::assertSame(ScoreType::WinnerOnly, $game->getScoreType());
    }
}
