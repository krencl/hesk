<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Game;
use App\Entity\Party;
use App\Entity\User;
use App\Enum\ScoreType;
use PHPUnit\Framework\TestCase;

final class GameTest extends TestCase
{
    private function game(): Game
    {
        return new Game(Party::create('P', new User('g', 'a@example.com')), 'Catan', ScoreType::MostPoints);
    }

    /** @return list<string> */
    private function addonNames(Game $g): array
    {
        return array_values(array_map(static fn ($a) => $a->getName(), $g->getAddons()->toArray()));
    }

    public function testAddonsAreSyncedByName(): void
    {
        $game = $this->game();
        $game->update('Catan', ScoreType::MostPoints, null, null, null, ['Seafarers', ' Cities ', 'Seafarers', '']);
        self::assertSame(['Seafarers', 'Cities'], $this->addonNames($game));

        $kept = $game->getAddons()->first();
        $game->update('Catan', ScoreType::MostPoints, null, null, null, ['Seafarers', 'Traders']);

        self::assertSame(['Seafarers', 'Traders'], $this->addonNames($game));
        self::assertSame($kept, $game->getAddons()->first(), 'existing addon instance is preserved');
    }

    public function testEmptyOptionalFieldsBecomeNull(): void
    {
        $game = $this->game();
        $game->update('Catan', ScoreType::WinnerOnly, '', '', '', []);

        self::assertNull($game->getImageUrl());
        self::assertNull($game->getDescription());
        self::assertNull($game->getCheatSheet());
        self::assertSame(ScoreType::WinnerOnly, $game->getScoreType());
    }
}
