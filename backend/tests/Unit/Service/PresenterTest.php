<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\GameSession;
use App\Entity\Play;
use App\Entity\PlayResult;
use App\Entity\User;
use App\Enum\PartyRole;
use App\Service\Presenter;
use App\Tests\Support\Fixtures;
use PHPUnit\Framework\TestCase;

final class PresenterTest extends TestCase
{
    public function testMeTellsWhetherTheNicknameIsSet(): void
    {
        $withoutNickname = Fixtures::id(new User('g', 'x@example.com'), 4);

        self::assertSame(['id' => 4, 'nickname' => null, 'email' => 'x@example.com', 'hasNickname' => false], (new Presenter())->me($withoutNickname));
        self::assertTrue((new Presenter())->me(Fixtures::user('Anna'))['hasNickname']);
    }

    public function testPartyShowsTheRoleOfTheViewerOnly(): void
    {
        $anna = Fixtures::user('Anna');
        $bob = Fixtures::user('Bob');
        $party = Fixtures::party($anna);
        Fixtures::member($party, $bob, PartyRole::Writer);
        $presenter = new Presenter();

        self::assertSame('writer', $presenter->party($party, $bob)['role']);
        self::assertArrayNotHasKey('role', $presenter->party($party));
        self::assertSame(['Anna' => 'admin', 'Bob' => 'writer'], array_column($presenter->partyDetail($party, $anna)['members'], 'role', 'nickname'));
        self::assertNull($presenter->party($party, Fixtures::user('Eve'))['role'], 'cizí uživatel roli nemá');
    }

    public function testGameWithStatsListsSortedWinnersAndTheFirstWinnerScore(): void
    {
        $anna = Fixtures::user('Anna');
        $party = Fixtures::party($anna);
        $game = Fixtures::game($party);
        $when = new \DateTimeImmutable('2026-03-01T18:00:00+00:00');
        $play = Fixtures::id(new Play($party, $game, $when, $when, Fixtures::id(new GameSession($party, 4, $when, $when))));
        $play->replaceResults([
            new PlayResult($play, Fixtures::guest($party, 'Zora'), 7.0, 1, true),
            new PlayResult($play, Fixtures::player($party, $anna), 7.0, 1, true),
            new PlayResult($play, Fixtures::guest($party, 'Bob'), 3.0, 3, false),
        ]);

        $data = (new Presenter())->gameWithStats($game, $play, 5);

        self::assertSame(5, $data['playCount']);
        self::assertSame(['Anna', 'Zora'], $data['lastPlay']['winners']);
        self::assertEquals(7, $data['lastPlay']['winnerScore']);
        self::assertSame(4, $data['lastPlay']['sessionNumber']);
        self::assertNull((new Presenter())->gameWithStats($game, null, 0)['lastPlay']);
    }

    public function testPlayResultsAreOrderedByRankAndCarryTheCharacter(): void
    {
        $anna = Fixtures::user('Anna');
        $party = Fixtures::party($anna);
        $game = Fixtures::game($party);
        $game->update('Azul', null, $game->getScoreType(), null, null, null, [], [['id' => null, 'name' => 'Gigazaur']]);
        $character = Fixtures::id($game->getCharacters()->first());
        $when = new \DateTimeImmutable('2026-03-01T18:00:00+00:00');
        $play = Fixtures::id(new Play($party, $game, $when, $when->modify('+1 hour'), Fixtures::id(new GameSession($party, 1, $when, $when))));
        $play->replaceResults([
            new PlayResult($play, Fixtures::guest($party, 'Bob'), 1.0, 2, false),
            new PlayResult($play, Fixtures::player($party, $anna), 2.0, 1, true, true, $character),
        ]);

        $data = (new Presenter())->play($play);

        self::assertSame(['Anna', 'Bob'], array_column($data['results'], 'name'));
        self::assertSame(['id' => $character->getId(), 'name' => 'Gigazaur'], $data['results'][0]['character']);
        self::assertNull($data['results'][1]['character']);
        self::assertSame('2026-03-01T19:00:00+00:00', $data['endedAt']);
    }
}
