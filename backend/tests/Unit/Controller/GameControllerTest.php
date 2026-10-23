<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\GameController;
use App\Domain\Exception\DomainException;
use App\Dto\GameLinkRequest;
use App\Dto\GamePlayersRequest;
use App\Dto\GameRequest;
use App\Dto\NamedItemRequest;
use App\Entity\Game;
use App\Entity\GameSession;
use App\Entity\Party;
use App\Entity\Play;
use App\Entity\PlayResult;
use App\Entity\User;
use App\Enum\PartyRole;
use App\Enum\ScoreType;
use App\Repository\GameRepository;
use App\Repository\PlayRepository;
use App\Service\CoverStorage;
use App\Service\Presenter;
use App\Tests\Support\ControllerHarness;
use App\Tests\Support\DoctrineMocks;
use App\Tests\Support\Fixtures;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/** Hry party: repozitáře a úložiště obrázků jsou mocky, entity žijí jen v paměti. */
final class GameControllerTest extends TestCase
{
    use DoctrineMocks;

    private User $anna;
    private User $eve;
    private Party $party;
    private \ArrayObject $calls;

    protected function setUp(): void
    {
        $this->anna = Fixtures::user('Anna');
        $this->eve = Fixtures::user('Eve');
        $this->party = Fixtures::party($this->anna);
        $this->calls = new \ArrayObject();
    }

    private function controller(?GameRepository $games = null, ?PlayRepository $plays = null, ?CoverStorage $covers = null, array $known = []): GameController
    {
        return new GameController(
            new Presenter(),
            $this->mockEm($known, $this->calls),
            $plays ?? $this->createStub(PlayRepository::class),
            $games ?? $this->createStub(GameRepository::class),
            $covers ?? $this->createStub(CoverStorage::class),
        );
    }

    /** Správce her: smí upravovat a mazat sdílené hry. */
    private function admin(): User
    {
        $this->anna->addRole(User::ROLE_GAMES_ADMIN);

        return $this->anna;
    }

    private function request(array $overrides = []): GameRequest
    {
        return new GameRequest(...($overrides + ['name' => 'Azul']));
    }

    private function lastPlay(Game $game): Play
    {
        $session = Fixtures::id(new GameSession($this->party, 3, new \DateTimeImmutable('2026-04-10T18:00:00+00:00'), new \DateTimeImmutable('2026-04-10T19:00:00+00:00')));
        $play = Fixtures::id(new Play($this->party, $game, new \DateTimeImmutable('2026-04-10T18:00:00+00:00'), new \DateTimeImmutable('2026-04-10T19:00:00+00:00'), $session));
        $play->replaceResults([
            new PlayResult($play, Fixtures::player($this->party, $this->anna), 5.0, 1, true),
            new PlayResult($play, Fixtures::guest($this->party, 'Bob'), 3.0, 2, false),
        ]);

        return $play;
    }

    public function testListPassesFiltersToTheRepositoryAndAddsStats(): void
    {
        $dixit = Fixtures::game($this->party, 'Dixit');
        $azul = Fixtures::game($this->party, 'Azul', ScoreType::WinnerOnly);
        $last = $this->lastPlay($dixit);
        $games = $this->createMock(GameRepository::class);
        $games->expects(self::once())->method('search')->with($this->party, 'dix', GameRepository::SORT_NAME, 2, 10, 4)->willReturn([$dixit, $azul]);
        $games->method('statsFor')->willReturn([
            $dixit->getId() => ['lastPlay' => $last, 'playCount' => 2],
            $azul->getId() => ['lastPlay' => null, 'playCount' => 0],
        ]);

        $request = new Request(['q' => 'dix', 'sort' => 'name', 'sessionNumber' => '2', 'limit' => '10', 'offset' => '4']);
        $list = ControllerHarness::json(ControllerHarness::as($this->anna)->call($this->controller($games), 'list', ['party' => $this->party, 'request' => $request]));

        self::assertSame(['Dixit', 'Azul'], array_column($list, 'name'));
        self::assertSame(2, $list[0]['playCount']);
        self::assertSame([$last->getId(), 3, ['Anna'], 5], [$list[0]['lastPlay']['id'], $list[0]['lastPlay']['sessionNumber'], $list[0]['lastPlay']['winners'], $list[0]['lastPlay']['winnerScore']]);
        self::assertNull($list[1]['lastPlay']);
        self::assertSame(0, $list[1]['playCount']);
    }

    public function testListDefaultsToActivitySortWithoutLimit(): void
    {
        $games = $this->createMock(GameRepository::class);
        $games->expects(self::once())->method('search')->with($this->party, null, GameRepository::SORT_ACTIVITY, null, null, 0)->willReturn([]);
        $games->method('statsFor')->willReturn([]);

        ControllerHarness::as($this->anna)->call($this->controller($games), 'list', ['party' => $this->party, 'request' => new Request(['sort' => 'nesmysl', 'sessionNumber' => ''])]);
    }

    public function testListLimitIsClamped(): void
    {
        $games = $this->createMock(GameRepository::class);
        $games->expects(self::once())->method('search')->with($this->party, null, GameRepository::SORT_ACTIVITY, null, 100, 0)->willReturn([]);
        $games->method('statsFor')->willReturn([]);

        ControllerHarness::as($this->anna)->call($this->controller($games), 'list', ['party' => $this->party, 'request' => new Request(['limit' => '9999'])]);
    }

    public function testStrangerCannotUseGames(): void
    {
        $game = Fixtures::game($this->party);
        $harness = ControllerHarness::as($this->eve);
        $controller = $this->controller();

        foreach ([
            ['list', ['party' => $this->party, 'request' => new Request()]],
            ['create', ['party' => $this->party, 'req' => $this->request()]],
            ['link', ['party' => $this->party, 'req' => new GameLinkRequest($game->getId())]],
            ['unlink', ['party' => $this->party, 'game' => $game]],
            ['show', ['party' => $this->party, 'game' => $game]],
            ['setPlayers', ['party' => $this->party, 'game' => $game, 'req' => new GamePlayersRequest()]],
            ['uploadImage', ['party' => $this->party, 'request' => new Request()]],
        ] as [$action, $args]) {
            try {
                $harness->call($controller, $action, $args);
                self::fail("Cizí uživatel nesmí volat $action.");
            } catch (AccessDeniedException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testPlainMemberOnlyReadsGamesOfThePartyWhileWriterManagesThem(): void
    {
        $bob = Fixtures::member($this->party, Fixtures::user('Bob'));
        $cyril = Fixtures::member($this->party, Fixtures::user('Cyril'), PartyRole::Writer);
        $game = Fixtures::game($this->party, 'Azul');
        $catalog = Fixtures::catalogGame('Tokaido');
        $games = $this->createStub(GameRepository::class);
        $games->method('statsFor')->willReturn([$game->getId() => ['lastPlay' => null, 'playCount' => 0]]);
        $controller = $this->controller($games, known: [$game, $catalog]);
        $manage = [
            ['create', ['party' => $this->party, 'req' => $this->request()]],
            ['link', ['party' => $this->party, 'req' => new GameLinkRequest($catalog->getId())]],
            ['setPlayers', ['party' => $this->party, 'game' => $game, 'req' => new GamePlayersRequest()]],
            ['uploadImage', ['party' => $this->party, 'request' => new Request()]],
            ['unlink', ['party' => $this->party, 'game' => $game]],
        ];

        foreach ($manage as [$action, $args]) {
            try {
                ControllerHarness::as($bob)->call($controller, $action, $args);
                self::fail("Obyčejný člen nesmí volat $action.");
            } catch (AccessDeniedException) {
                $this->addToAssertionCount(1);
            }
        }
        $harness = ControllerHarness::as($bob);
        $harness->call($controller, 'list', ['party' => $this->party, 'request' => new Request()]);
        $harness->call($controller, 'show', ['party' => $this->party, 'game' => $game]);

        foreach (array_slice($manage, 0, 3) as [$action, $args]) {
            ControllerHarness::as($cyril)->call($controller, $action, $args);
        }
    }

    public function testOnlyGamesAdminMayEditOrDeleteTheSharedGame(): void
    {
        $game = Fixtures::game($this->party);
        $controller = $this->controller(known: [$game]);
        $admin = $this->admin();

        foreach ([['update', ['game' => $game, 'req' => $this->request()]], ['delete', ['game' => $game]]] as [$action, $args]) {
            try {
                ControllerHarness::as($this->eve)->call($controller, $action, $args);
                self::fail("Uživatel bez role správce her nesmí volat $action.");
            } catch (AccessDeniedException) {
                $this->addToAssertionCount(1);
            }
            ControllerHarness::as($admin)->call($controller, $action, $args);
        }
    }

    public function testCreateStoresTheGameInTheCatalogAndPutsItIntoTheParty(): void
    {
        $response = ControllerHarness::as($this->anna)->call($this->controller(), 'create', ['party' => $this->party, 'req' => $this->request([
            'name' => ' King of Tokyo ', 'shortName' => ' Tokyo ', 'scoreType' => ScoreType::WinnerOnly, 'bggId' => 13,
            'addons' => [new NamedItemRequest('Power Up')],
            'characters' => [new NamedItemRequest('Gigazaur'), new NamedItemRequest('Kraken'), new NamedItemRequest('Kraken')],
        ])]);
        $game = ControllerHarness::json($response);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame(['King of Tokyo', 'Tokyo', 'winner_only', 13], [$game['name'], $game['shortName'], $game['scoreType'], $game['bggId']]);
        self::assertSame(['Power Up'], array_column($game['addons'], 'name'));
        self::assertSame(['Gigazaur', 'Kraken'], array_column($game['characters'], 'name'), 'opakované jméno se zahodí');
        [$created] = array_values(array_filter($this->calls['persist'], static fn (object $e): bool => $e instanceof Game));
        self::assertTrue($this->party->hasGame($created), 'nová hra je rovnou v partě');
        self::assertSame(1, $this->calls['flush']);
    }

    public function testCreateRefusesDuplicatesOfTheCatalog(): void
    {
        $existing = Fixtures::catalogGame('Catan');
        $games = $this->createMock(GameRepository::class);
        $games->expects(self::exactly(2))->method('findDuplicate')
            ->willReturnCallback(static fn (?int $bgg, string $name): Game => $existing);
        $controller = $this->controller($games);

        foreach ([['bggId' => 13], ['bggId' => null, 'name' => ' catan ']] as $overrides) {
            try {
                ControllerHarness::as($this->anna)->call($controller, 'create', ['party' => $this->party, 'req' => $this->request($overrides)]);
                self::fail('Duplicitní hra se nesmí založit.');
            } catch (DomainException $e) {
                self::assertStringContainsString('Catan', $e->getMessage());
            }
        }
        self::assertArrayNotHasKey('persist', $this->calls->getArrayCopy());
        self::assertCount(0, $this->party->getGameLinks());
    }

    public function testDuplicateCheckUsesTheBggIdOrTheTrimmedNameOfACustomGame(): void
    {
        $games = $this->createMock(GameRepository::class);
        $games->expects(self::exactly(2))->method('findDuplicate')
            ->willReturnCallback(function (?int $bgg, string $name, ?Game $except): ?Game {
                static $call = 0;
                self::assertSame(1 === ++$call ? [13, 'Azul'] : [null, 'Vlastní'], [$bgg, $name]);
                self::assertNull($except);

                return null;
            });
        $controller = $this->controller($games);
        $harness = ControllerHarness::as($this->anna);

        $harness->call($controller, 'create', ['party' => $this->party, 'req' => $this->request(['bggId' => 13])]);
        $harness->call($controller, 'create', ['party' => $this->party, 'req' => $this->request(['name' => ' Vlastní '])]);
    }

    public function testCreateGameWithoutOptionalFieldsHasNulls(): void
    {
        $game = ControllerHarness::json(ControllerHarness::as($this->anna)->call($this->controller(), 'create', ['party' => $this->party, 'req' => $this->request(['imageUrl' => '', 'bggId' => null])]));

        self::assertSame([null, null, null], [$game['imageUrl'], $game['bggId'], $game['shortName']]);
    }

    public function testLinkPutsACatalogGameIntoThePartyOnlyOnce(): void
    {
        $game = Fixtures::catalogGame('Tokaido');
        $controller = $this->controller(known: [$game]);
        $harness = ControllerHarness::as($this->anna);

        $first = $harness->call($controller, 'link', ['party' => $this->party, 'req' => new GameLinkRequest($game->getId())]);
        $second = $harness->call($controller, 'link', ['party' => $this->party, 'req' => new GameLinkRequest($game->getId())]);

        self::assertSame([201, 200], [$first->getStatusCode(), $second->getStatusCode()]);
        self::assertSame('Tokaido', ControllerHarness::json($first)['name']);
        self::assertCount(1, $this->party->getGameLinks());
        self::assertSame(1, $this->calls['flush'], 'podruhé se nic neukládá');
    }

    public function testLinkOfAnUnknownGameIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);
        ControllerHarness::as($this->anna)->call($this->controller(), 'link', ['party' => $this->party, 'req' => new GameLinkRequest(999999)]);
    }

    public function testUnlinkRemovesTheGameFromThePartyButKeepsItInTheCatalog(): void
    {
        $game = Fixtures::game($this->party, 'Tokaido');

        $response = ControllerHarness::as($this->anna)->call($this->controller(), 'unlink', ['party' => $this->party, 'game' => $game]);

        self::assertSame(204, $response->getStatusCode());
        self::assertFalse($this->party->hasGame($game));
        self::assertArrayNotHasKey('remove', $this->calls->getArrayCopy(), 'hra v katalogu zůstává');
    }

    public function testGameWithPlaysOfThePartyCannotBeUnlinked(): void
    {
        $game = Fixtures::game($this->party);
        $plays = $this->createMock(PlayRepository::class);
        $plays->expects(self::once())->method('countForGame')->with($game, $this->party)->willReturn(1);

        try {
            ControllerHarness::as($this->anna)->call($this->controller(plays: $plays), 'unlink', ['party' => $this->party, 'game' => $game]);
            self::fail('Hru se zápisy nelze z party odebrat.');
        } catch (DomainException) {
        }
        self::assertTrue($this->party->hasGame($game));
    }

    public function testGamesOfOtherPartiesAreNotReachableThroughThisParty(): void
    {
        $foreign = Fixtures::catalogGame('Cizí');
        $harness = ControllerHarness::as($this->anna);
        $controller = $this->controller();

        foreach ([
            ['show', ['party' => $this->party, 'game' => $foreign]],
            ['unlink', ['party' => $this->party, 'game' => $foreign]],
            ['setPlayers', ['party' => $this->party, 'game' => $foreign, 'req' => new GamePlayersRequest()]],
        ] as [$action, $args]) {
            try {
                $harness->call($controller, $action, $args);
                self::fail("$action nesmí fungovat pro hru, která v partě není.");
            } catch (NotFoundHttpException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testGamePlayersAreResolvedByIdOrNameAndGuestsAreCreated(): void
    {
        $boss = Fixtures::guest($this->party, 'Boss');
        $annaPlayer = Fixtures::player($this->party, $this->anna);

        // host podle id, nový podle jména (vznikne host), jméno člena se spáruje s jeho hráčem, prázdné a duplicitní se zahodí
        $game = ControllerHarness::json(ControllerHarness::as($this->anna)->call($this->controller(), 'create', ['party' => $this->party, 'req' => $this->request([
            'scoreType' => ScoreType::WinnerOnly,
            'players' => [new NamedItemRequest('Boss', $boss->getId()), new NamedItemRequest('Minion'), new NamedItemRequest('minion'), new NamedItemRequest(' '), new NamedItemRequest('anna')],
        ])]));

        $byName = array_column($game['players'], 'id', 'name');
        self::assertSame(['Boss', 'Minion', 'Anna'], array_keys($byName));
        self::assertSame([$boss->getId(), $annaPlayer->getId()], [$byName['Boss'], $byName['Anna']]);
        self::assertEqualsCanonicalizing(['Anna', 'Boss', 'Minion'], $this->party->getPlayers()->map(static fn ($p): string => $p->getName())->toArray());
    }

    public function testGameTagsBelongToTheSharedGameAndAreCleanedAndSorted(): void
    {
        $created = ControllerHarness::json(ControllerHarness::as($this->anna)->call($this->controller(), 'create', ['party' => $this->party, 'req' => $this->request(['tags' => ['strategie', ' Rychlovka ', 'Strategie', ' ']])]));
        self::assertSame(['Rychlovka', 'strategie'], $created['tags'], 'trimmed, deduplicated case-insensitively, sorted');

        $game = Fixtures::game($this->party, 'Coop', ScoreType::WinnerOnly);
        $updated = ControllerHarness::json(ControllerHarness::as($this->admin())->call($this->controller(known: [$game]), 'update', ['game' => $game, 'req' => $this->request(['name' => 'Coop', 'scoreType' => ScoreType::WinnerOnly, 'tags' => ['Párty', 'Kooperativní']])]));
        self::assertSame(['Kooperativní', 'Párty'], $updated['tags']);
        $cleared = ControllerHarness::json(ControllerHarness::as($this->admin())->call($this->controller(known: [$game]), 'update', ['game' => $game, 'req' => $this->request(['name' => 'Coop', 'scoreType' => ScoreType::WinnerOnly])]));
        self::assertSame([], $cleared['tags']);
    }

    public function testGamePlayerEditedByIdIsRenamedAndRemovedOneStaysInTheParty(): void
    {
        $boss = Fixtures::guest($this->party, 'Boss');
        $minion = Fixtures::guest($this->party, 'Minion');
        $game = Fixtures::game($this->party, 'Coop', ScoreType::WinnerOnly);
        $controller = $this->controller(known: [$game]);
        $harness = ControllerHarness::as($this->anna);
        $edit = fn (array $players): array => ControllerHarness::json($harness->call($controller, 'setPlayers', ['party' => $this->party, 'game' => $game, 'req' => new GamePlayersRequest($players)]));

        $edit([new NamedItemRequest('Boss', $boss->getId()), new NamedItemRequest('Minion', $minion->getId())]);
        $updated = $edit([new NamedItemRequest('Velký Boss', $boss->getId()), new NamedItemRequest('Minion', $minion->getId())]);
        self::assertSame([['id' => $boss->getId(), 'name' => 'Velký Boss'], ['id' => $minion->getId(), 'name' => 'Minion']], $updated['players']);

        $edit([new NamedItemRequest('Velký Boss', $boss->getId())]);
        self::assertContains('Minion', $this->party->getPlayers()->map(static fn ($p): string => $p->getName())->toArray(), 'odebraný hráč zůstává v partě');
    }

    public function testGamePlayersBelongToThePartyNotToTheSharedGame(): void
    {
        $other = Fixtures::party($this->eve, 'Jiná');
        $game = Fixtures::game($this->party, 'Coop', ScoreType::WinnerOnly);
        $other->addGame($game);
        $boss = Fixtures::guest($this->party, 'Boss');

        ControllerHarness::as($this->anna)->call($this->controller(known: [$game]), 'setPlayers', ['party' => $this->party, 'game' => $game, 'req' => new GamePlayersRequest([new NamedItemRequest('Boss', $boss->getId())])]);

        self::assertSame(['Boss'], $this->party->findGameLink($game)->getPlayers()->map(static fn ($p): string => $p->getName())->toArray());
        self::assertCount(0, $other->findGameLink($game)->getPlayers());
    }

    public function testGamePlayerRenameRejectsMembersDuplicatesAndForeignPlayers(): void
    {
        $boss = Fixtures::guest($this->party, 'Boss');
        $annaPlayer = Fixtures::player($this->party, $this->anna);
        $stranger = Fixtures::user('Zdeněk');
        $foreign = Fixtures::player(Fixtures::party($stranger, 'Cizí'), $stranger);
        $game = Fixtures::game($this->party, 'Coop', ScoreType::WinnerOnly);
        $controller = $this->controller(known: [$game]);
        $harness = ControllerHarness::as($this->anna);

        foreach ([
            [new NamedItemRequest('Jiná', $annaPlayer->getId())], // člena s účtem přejmenovat nejde
            [new NamedItemRequest('Anna', $boss->getId())],       // jméno je obsazené
            [new NamedItemRequest('Cizí', $foreign->getId())],    // hráč z jiné party
        ] as $players) {
            try {
                $harness->call($controller, 'setPlayers', ['party' => $this->party, 'game' => $game, 'req' => new GamePlayersRequest($players)]);
                self::fail('Úprava hráče hry se musí odmítnout.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testUpdateRenamesItemsByIdKeepingTheirIds(): void
    {
        $game = Fixtures::game($this->party, 'King of Tokyo', ScoreType::WinnerOnly);
        $game->update('King of Tokyo', 'Tokyo', ScoreType::WinnerOnly, null, null, null, [['id' => null, 'name' => 'Power Up']], [['id' => null, 'name' => 'Gigazaur'], ['id' => null, 'name' => 'Kraken']]);
        $addon = Fixtures::id($game->getAddons()->first());
        [$giga, $kraken] = array_map(static fn ($c): object => Fixtures::id($c), $game->getCharacters()->toArray());
        $controller = $this->controller(known: [$game]);

        $updated = ControllerHarness::json(ControllerHarness::as($this->admin())->call($controller, 'update', ['game' => $game, 'req' => $this->request([
            'name' => 'King of Tokyo', 'scoreType' => ScoreType::WinnerOnly, 'shortName' => null,
            'addons' => [new NamedItemRequest('Power Up 2', $addon->getId())],
            'characters' => [new NamedItemRequest('Giga', $giga->getId()), new NamedItemRequest('Alienoid')],
        ])]));

        self::assertSame($giga->getId(), $updated['characters'][0]['id'], 'přejmenováno, ne znovu vytvořeno');
        self::assertSame(['Giga', 'Alienoid'], array_column($updated['characters'], 'name'));
        self::assertSame([$addon->getId()], array_column($updated['addons'], 'id'));
        self::assertNull($updated['shortName']);
        self::assertSame(1, $this->calls['flush']);
    }

    public function testUpdateIgnoresPlayersOfTheRequestBecauseTheyBelongToTheParty(): void
    {
        $game = Fixtures::game($this->party, 'Coop', ScoreType::WinnerOnly);
        $boss = Fixtures::guest($this->party, 'Boss');

        $updated = ControllerHarness::json(ControllerHarness::as($this->admin())->call($this->controller(known: [$game]), 'update', ['game' => $game, 'req' => $this->request(['name' => 'Coop', 'scoreType' => ScoreType::WinnerOnly, 'players' => [new NamedItemRequest('Boss', $boss->getId())]])]));

        self::assertSame([], $updated['players']);
        self::assertCount(0, $this->party->findGameLink($game)->getPlayers());
    }

    public function testUpdateRefusesADuplicateOfAnotherGame(): void
    {
        $game = Fixtures::game($this->party, 'Azul');
        $other = Fixtures::catalogGame('Catan');
        $games = $this->createMock(GameRepository::class);
        $games->expects(self::once())->method('findDuplicate')->with(13, 'Azul', $game)->willReturn($other);

        $this->expectException(DomainException::class);
        ControllerHarness::as($this->admin())->call($this->controller($games), 'update', ['game' => $game, 'req' => $this->request(['bggId' => 13])]);
    }

    public function testScoreTypeCannotChangeWhenGameHasPlays(): void
    {
        $game = Fixtures::game($this->party, 'Azul', ScoreType::MostPoints);
        $plays = $this->createStub(PlayRepository::class);
        $plays->method('countForGame')->willReturn(3);
        $controller = $this->controller(plays: $plays);
        $harness = ControllerHarness::as($this->admin());

        // stejný typ se uložit smí, jiný ne
        $same = $harness->call($controller, 'update', ['game' => $game, 'req' => $this->request(['name' => 'Azul 2', 'scoreType' => ScoreType::MostPoints])]);
        self::assertSame(200, $same->getStatusCode());

        $this->expectException(DomainException::class);
        $harness->call($controller, 'update', ['game' => $game, 'req' => $this->request(['scoreType' => ScoreType::WinnerOnly])]);
    }

    public function testGameKeepsItsBggIdOnUpdate(): void
    {
        $game = Fixtures::game($this->party);

        $updated = ControllerHarness::json(ControllerHarness::as($this->admin())->call($this->controller(), 'update', ['game' => $game, 'req' => $this->request(['bggId' => 13])]));

        self::assertSame(13, $updated['bggId']);
    }

    public function testShowAddsStatsOfTheGameInThisParty(): void
    {
        $game = Fixtures::game($this->party, 'Dixit');
        $last = $this->lastPlay($game);
        $games = $this->createMock(GameRepository::class);
        $games->expects(self::once())->method('statsFor')->with($this->party, [$game])->willReturn([$game->getId() => ['lastPlay' => $last, 'playCount' => 2]]);

        $detail = ControllerHarness::json(ControllerHarness::as($this->anna)->call($this->controller($games), 'show', ['party' => $this->party, 'game' => $game]));

        self::assertSame([2, $last->getId()], [$detail['playCount'], $detail['lastPlay']['id']]);
    }

    public function testGameWithPlaysCannotBeDeleted(): void
    {
        $game = Fixtures::game($this->party);
        $plays = $this->createMock(PlayRepository::class);
        $plays->expects(self::once())->method('countForGame')->with($game)->willReturn(1);

        try {
            ControllerHarness::as($this->admin())->call($this->controller(plays: $plays), 'delete', ['game' => $game]);
            self::fail('Hru se zápisy nelze smazat.');
        } catch (DomainException) {
        }
        self::assertArrayNotHasKey('remove', $this->calls->getArrayCopy());
    }

    public function testGameWithoutPlaysIsDeleted(): void
    {
        $game = Fixtures::game($this->party);

        $response = ControllerHarness::as($this->admin())->call($this->controller(), 'delete', ['game' => $game]);

        self::assertSame(204, $response->getStatusCode());
        self::assertSame([$game], $this->calls['remove']);
    }

    public function testUploadImageReturnsTheStoredUrl(): void
    {
        $file = new UploadedFile(__FILE__, 'cover.webp', null, null, true);
        $covers = $this->createMock(CoverStorage::class);
        $covers->expects(self::once())->method('store')->with($file)->willReturn('/uploads/covers/'.sha1('a').'.webp');

        $response = ControllerHarness::as($this->anna)->call($this->controller(covers: $covers), 'uploadImage', ['party' => $this->party, 'request' => new Request(files: ['file' => $file])]);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame(['url' => '/uploads/covers/'.sha1('a').'.webp'], ControllerHarness::json($response));
    }

    public function testUploadWithoutFileIsHandedOverAsNull(): void
    {
        $covers = $this->createMock(CoverStorage::class);
        $covers->expects(self::once())->method('store')->with(null)->willThrowException(new DomainException('Chybí soubor s obrázkem.'));

        $this->expectException(DomainException::class);
        ControllerHarness::as($this->anna)->call($this->controller(covers: $covers), 'uploadImage', ['party' => $this->party, 'request' => new Request()]);
    }
}
