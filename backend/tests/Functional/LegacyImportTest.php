<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Legacy\ImportReport;
use App\Legacy\LegacyImporter;
use App\Tests\Support\TestDatabase;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/** Import ze staré aplikace: cíl = SQLite se schématem aplikace, zdroj = druhá SQLite se starým schématem. */
final class LegacyImportTest extends KernelTestCase
{
    private Connection $target;
    private Connection $legacy;

    public static function setUpBeforeClass(): void
    {
        // jediné testy, které sahají na (SQLite) databázi: import je SQL z cizí DB do schématu aplikace, mockem by se neověřil
        TestDatabase::createSchema();
    }

    protected function setUp(): void
    {
        self::bootKernel();
        TestDatabase::clear(static::getContainer());
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->target = $em->getConnection();

        $this->legacy = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        foreach ([
            'CREATE TABLE user (id INTEGER PRIMARY KEY, email TEXT, role TEXT, player_id INT, google_id TEXT, created_at TEXT)',
            'CREATE TABLE party (id INTEGER PRIMARY KEY, name TEXT, created_at TEXT)',
            'CREATE TABLE party_user (party_id INT, user_id INT, role TEXT)',
            'CREATE TABLE player (id INTEGER PRIMARY KEY, name TEXT, type TEXT, game_id INT)',
            'CREATE TABLE party_player (party_id INT, player_id INT)',
            'CREATE TABLE game (id INTEGER PRIMARY KEY, name TEXT, short_name TEXT, scoring_type TEXT, cheat_sheet TEXT)',
            'CREATE TABLE game_version (id INTEGER PRIMARY KEY, game_id INT, name TEXT)',
            'CREATE TABLE game_character (id INTEGER PRIMARY KEY, game_id INT, name TEXT)',
            'CREATE TABLE tag (id INTEGER PRIMARY KEY, name TEXT)',
            'CREATE TABLE round (id INTEGER PRIMARY KEY, party_id INT, game_id INT, game_version_id INT, session_number INT, note TEXT, created_at TEXT)',
            'CREATE TABLE round_player (id INTEGER PRIMARY KEY, round_id INT, player_id INT, game_character_id INT, score INT, is_winner INT, is_starting INT)',
            'CREATE TABLE tag_round (tag_id INT, round_id INT)',
            'CREATE TABLE tag_game (tag_id INT, game_id INT)',
        ] as $sql) {
            $this->legacy->executeStatement($sql);
        }
        $this->seedLegacy();
    }

    private function seedLegacy(): void
    {
        $in = fn (string $table, array $row) => $this->legacy->insert($table, $row);
        $in('user', ['id' => 1, 'email' => 'anna@example.com', 'role' => 'admin', 'player_id' => 1, 'google_id' => null, 'created_at' => '2022-01-01 10:00:00']);
        $in('user', ['id' => 2, 'email' => 'bob@example.com', 'role' => 'user', 'player_id' => 2, 'google_id' => null, 'created_at' => '2022-01-02 10:00:00']);
        $in('party', ['id' => 1, 'name' => 'Pátek', 'created_at' => '2022-01-01 10:00:00']);
        $in('party', ['id' => 2, 'name' => 'Víkend', 'created_at' => '2022-01-01 10:00:00']);
        foreach ([[1, 1, 'owner'], [1, 2, 'user'], [2, 1, 'owner']] as [$party, $user, $role]) {
            $in('party_user', ['party_id' => $party, 'user_id' => $user, 'role' => $role]);
        }
        foreach ([[1, 'Anna', 'human', null], [2, 'Bob', 'human', null], [3, 'Boss', 'game', 10], [4, 'Cyril', 'human', null], [5, 'Cyril', 'human', null]] as [$id, $name, $type, $game]) {
            $in('player', ['id' => $id, 'name' => $name, 'type' => $type, 'game_id' => $game]);
        }
        foreach ([1, 2, 4, 5] as $player) {
            $in('party_player', ['party_id' => 1, 'player_id' => $player]);
        }
        $in('game', ['id' => 10, 'name' => 'King of Tokyo', 'short_name' => 'Tokyo', 'scoring_type' => 'only_winner', 'cheat_sheet' => '# Pravidla']);
        $in('game', ['id' => 11, 'name' => 'Azul', 'short_name' => 'Azul', 'scoring_type' => 'highest_score', 'cheat_sheet' => '']);
        foreach ([[100, 10, 'Základní'], [101, 10, 'Power Up'], [110, 11, 'Základní'], [111, 11, 'Nepoužité rozšíření']] as [$id, $game, $name]) {
            $in('game_version', ['id' => $id, 'game_id' => $game, 'name' => $name]);
        }
        $in('game_character', ['id' => 200, 'game_id' => 10, 'name' => 'Gigazaur']);
        $in('game_character', ['id' => 201, 'game_id' => 10, 'name' => 'Kraken']);
        $in('tag', ['id' => 300, 'name' => 'víkend']);
        $in('tag', ['id' => 301, 'name' => 'Rychlovka']);
        $in('tag_game', ['tag_id' => 301, 'game_id' => 11]);

        $in('round', ['id' => 1000, 'party_id' => 1, 'game_id' => 11, 'game_version_id' => 110, 'session_number' => 1, 'note' => 'Skvělé', 'created_at' => '2022-03-01 20:00:00']);
        $in('round', ['id' => 1001, 'party_id' => 1, 'game_id' => 10, 'game_version_id' => 101, 'session_number' => 1, 'note' => null, 'created_at' => '2022-03-01 22:00:00']);
        $in('round', ['id' => 1002, 'party_id' => 2, 'game_id' => 11, 'game_version_id' => 110, 'session_number' => 7, 'note' => null, 'created_at' => '2022-04-01 18:00:00']);
        foreach ([
            [1, 1000, 1, null, 30, 1, 0], [2, 1000, 2, null, 20, 0, 1],
            [3, 1001, 1, 200, null, 1, 1], [4, 1001, 3, null, null, 0, 0],
            [5, 1002, 1, null, 12, 1, 0],
        ] as [$id, $round, $player, $character, $score, $winner, $starting]) {
            $in('round_player', ['id' => $id, 'round_id' => $round, 'player_id' => $player, 'game_character_id' => $character, 'score' => $score, 'is_winner' => $winner, 'is_starting' => $starting]);
        }
        $in('tag_round', ['tag_id' => 300, 'round_id' => 1001]);
    }

    private function import(bool $dryRun = false): ImportReport
    {
        $importer = new LegacyImporter($this->legacy, $this->target);

        return $importer->import($dryRun);
    }

    /** @return list<array<string, mixed>> */
    private function rows(string $sql, array $params = []): array
    {
        return $this->target->fetchAllAssociative($sql, $params);
    }

    private function rowCount(string $table): int
    {
        return (int) $this->target->fetchOne("SELECT COUNT(*) FROM $table");
    }

    /** @return array<string, int> počty řádků ve všech importovaných tabulkách */
    private function snapshot(): array
    {
        $out = [];
        foreach (['user', 'party', 'party_member', 'player', 'game', 'party_game', 'party_game_player', 'game_tag', 'game_game_tag', 'game_addon', 'game_character', 'play_tag', 'game_session', 'play', 'play_addon', 'play_play_tag', 'play_result', 'legacy_map'] as $t) {
            $out[$t] = $this->rowCount($t);
        }

        return $out;
    }

    public function testFirstImportConvertsEverythingWithTheOriginalRules(): void
    {
        $report = $this->import();

        self::assertSame(2, $report->entities['user']['created']);
        self::assertSame(['Pá', 'Ví'], array_column($this->rows('SELECT short_name FROM party ORDER BY id'), 'short_name'), 'party short names are generated from the names');
        self::assertSame(['Anna', 'Bob'], array_column($this->rows('SELECT nickname FROM user ORDER BY id'), 'nickname'), 'nicknames come from the linked legacy player');
        self::assertSame([['role' => 'admin'], ['role' => 'member'], ['role' => 'admin']], $this->rows('SELECT role FROM party_member ORDER BY party_id, user_id'), 'owner -> admin, user -> member');

        // hráči: jeden na dvojici (hráč, parta), shodná jména dostanou číslo, hráč s účtem je s ním propojený
        $players = array_column($this->rows('SELECT name, user_id FROM player WHERE party_id = 1 ORDER BY id'), 'user_id', 'name');
        self::assertSame(['Anna', 'Bob', 'Boss', 'Cyril', 'Cyril 2'], array_keys($players));
        self::assertSame(1, (int) $players['Anna']);
        self::assertNull($players['Cyril']);

        // hry: jen kde se hrálo; zkrácený název, typ bodování, rozšíření (bez „Základní“), postavy; vlastní hráč patří do party
        $tokyo = $this->rows("SELECT * FROM game WHERE name = 'King of Tokyo'")[0];
        self::assertSame('Tokyo', $tokyo['short_name']);
        self::assertSame('winner_only', $tokyo['score_type']);
        self::assertSame(['Boss'], $this->target->fetchFirstColumn('SELECT p.name FROM party_game pg JOIN party_game_player gp ON gp.party_game_id = pg.id JOIN player p ON p.id = gp.player_id WHERE pg.game_id = ?', [$tokyo['id']]));
        self::assertSame(['Power Up'], $this->target->fetchFirstColumn('SELECT name FROM game_addon WHERE game_id = ?', [$tokyo['id']]));
        self::assertSame(['Gigazaur', 'Kraken'], $this->target->fetchFirstColumn('SELECT name FROM game_character WHERE game_id = ? ORDER BY id', [$tokyo['id']]));
        $azul = $this->rows("SELECT * FROM game WHERE name = 'Azul'")[0];
        self::assertNull($azul['short_name'], 'short name equal to the name is not stored');
        self::assertSame('most_points', $azul['score_type']);
        self::assertNull($azul['cheat_sheet']);
        self::assertSame(['Nepoužité rozšíření'], $this->target->fetchFirstColumn('SELECT name FROM game_addon WHERE game_id = ?', [$azul['id']]), 'unused extensions are imported too');
        // sdílený katalog: Azul je jedna hra zařazená do obou part, Tokyo jen do první
        self::assertSame(2, $this->rowCount('game'), 'Azul is one shared game, as in the old app');
        self::assertSame(3, $this->rowCount('party_game'));
        self::assertSame([[1, 'Azul'], [1, 'King of Tokyo'], [2, 'Azul']], array_map('array_values', $this->rows('SELECT pg.party_id, g.name FROM party_game pg JOIN game g ON g.id = pg.game_id ORDER BY pg.party_id, g.name')));
        // tagy her (typ „game“ ve staré aplikaci) patří hře v katalogu a jsou jiné než tagy zápisů; „víkend“ je tag zápisu, ne hry
        self::assertSame([['Azul', 'Rychlovka']], array_map('array_values', $this->rows('SELECT g.name AS game, t.name AS tag FROM game_game_tag x JOIN game g ON g.id = x.game_id JOIN game_tag t ON t.id = x.game_tag_id')));
    }

    public function testPlaysKeepIdsConvertTimesToUtcAndComputeResults(): void
    {
        $this->import();

        self::assertSame([1000, 1001, 1002], array_map('intval', $this->target->fetchFirstColumn('SELECT id FROM play ORDER BY id')));
        $play = $this->rows('SELECT * FROM play WHERE id = 1000')[0];
        self::assertSame('2022-03-01 19:00:00', $play['started_at'], 'winter time Europe/Prague -> UTC');
        self::assertSame($play['started_at'], $play['ended_at']);
        self::assertSame('Skvělé', $play['notes']);
        self::assertSame('2022-04-01 16:00:00', $this->target->fetchOne('SELECT started_at FROM play WHERE id = 1002'), 'summer time');

        // seance zachovávají staré číslo a hranice z časů zápisů
        $sessions = $this->rows('SELECT party_id, number, started_at, ended_at FROM game_session ORDER BY party_id');
        self::assertSame([[1, 1, '2022-03-01 19:00:00', '2022-03-01 21:00:00'], [2, 7, '2022-04-01 16:00:00', '2022-04-01 16:00:00']], array_map('array_values', $sessions));

        // výsledky: pořadí se dopočte podle typu bodování, příznaky se přenesou
        $azul = $this->rows('SELECT p.name, r.score, r.placement, r.winner, r.is_starting FROM play_result r JOIN player p ON p.id = r.player_id WHERE r.play_id = 1000 ORDER BY r.id');
        self::assertEquals([['Anna', 30, 1, 1, 0], ['Bob', 20, 2, 0, 1]], array_map('array_values', $azul));
        $tokyo = $this->rows('SELECT p.name, r.score, r.winner, r.is_starting, c.name AS character FROM play_result r JOIN player p ON p.id = r.player_id LEFT JOIN game_character c ON c.id = r.character_id WHERE r.play_id = 1001 ORDER BY r.id');
        self::assertEquals([['Anna', null, 1, 1, 'Gigazaur'], ['Boss', null, 0, 0, null]], array_map('array_values', $tokyo));

        // rozšíření a tag zápisu
        self::assertSame(['Power Up'], $this->target->fetchFirstColumn('SELECT a.name FROM play_addon pa JOIN game_addon a ON a.id = pa.game_addon_id WHERE pa.play_id = 1001'));
        self::assertSame([], $this->target->fetchFirstColumn('SELECT 1 FROM play_addon WHERE play_id = 1000'), 'base version is not an extension');
        self::assertSame(['víkend'], $this->target->fetchFirstColumn('SELECT t.name FROM play_play_tag pt JOIN play_tag t ON t.id = pt.play_tag_id WHERE pt.play_id = 1001'));
    }

    public function testRepeatedImportChangesNothing(): void
    {
        $this->import();
        $before = $this->snapshot();

        $second = $this->import();

        self::assertTrue($second->isNoop(), json_encode($second->entities, \JSON_THROW_ON_ERROR));
        self::assertSame($before, $this->snapshot());
        self::assertSame($this->rowCount('play') + $this->rowCount('play_result'), $second->entities['play']['unchanged'] + $second->entities['result']['unchanged']);
    }

    public function testChangesInTheOldAppAreSynchronisedIncludingDeletions(): void
    {
        $this->import();
        // věci vzniklé jen v nové aplikaci
        $this->target->insert('user', ['email' => 'nova@example.com', 'nickname' => 'Nova', 'created_at' => '2026-01-01 00:00:00']);
        $this->target->insert('party', ['name' => 'Jen v nové', 'short_name' => 'Je', 'created_at' => '2026-01-01 00:00:00']);
        $newParty = (int) $this->target->lastInsertId();

        // úpravy ve staré aplikaci: změněné skóre, přejmenování, nová postava, nový zápis, smazaný zápis
        $this->legacy->update('round_player', ['score' => 25], ['id' => 2]);               // Bob 20 -> 25 (stále hůř než Anna)
        $this->legacy->update('round_player', ['score' => 40], ['id' => 1]);               // Anna 30 -> 40
        $this->legacy->update('game', ['name' => 'King of Tokyo 2', 'short_name' => 'KoT'], ['id' => 10]);
        $this->legacy->insert('game_character', ['id' => 202, 'game_id' => 10, 'name' => 'Pandakai']);
        $this->legacy->insert('round', ['id' => 1003, 'party_id' => 1, 'game_id' => 11, 'game_version_id' => 110, 'session_number' => 2, 'note' => null, 'created_at' => '2022-05-01 20:00:00']);
        $this->legacy->insert('round_player', ['id' => 6, 'round_id' => 1003, 'player_id' => 2, 'game_character_id' => null, 'score' => 5, 'is_winner' => 1, 'is_starting' => 0]);
        $this->legacy->delete('tag_round', ['round_id' => 1001]);
        $this->legacy->delete('round_player', ['round_id' => 1002]);
        $this->legacy->delete('round', ['id' => 1002]);
        $this->target->update('party', ['short_name' => 'PTK'], ['name' => 'Pátek']);

        $report = $this->import();

        self::assertSame('PTK', $this->target->fetchOne("SELECT short_name FROM party WHERE name = 'Pátek'"), 'a short name edited in the new app is not overwritten');

        self::assertSame(1, $report->entities['play']['created']);
        self::assertSame(1, $report->entities['play']['deleted']);
        self::assertSame(1, $report->entities['session']['deleted'] ?? 0, 'the emptied session of the deleted play is removed');
        self::assertSame(1, $report->entities['character']['created']);
        self::assertSame([1000, 1001, 1003], array_map('intval', $this->target->fetchFirstColumn('SELECT id FROM play WHERE party_id = (SELECT id FROM party WHERE name = ?) ORDER BY id', ['Pátek'])));
        self::assertSame(0, $this->rowCount('play') - 3, 'the play of the second party is gone');
        self::assertEquals([['Anna', 40, 1], ['Bob', 25, 2]], array_map('array_values', $this->rows('SELECT p.name, r.score, r.placement FROM play_result r JOIN player p ON p.id = r.player_id WHERE r.play_id = 1000 ORDER BY r.id')));
        $game = $this->rows("SELECT id, name, short_name FROM game WHERE name LIKE 'King%'");
        self::assertSame([['id' => $game[0]['id'], 'name' => 'King of Tokyo 2', 'short_name' => 'KoT']], $game, 'renamed in place, not duplicated');
        self::assertSame(['Gigazaur', 'Kraken', 'Pandakai'], $this->target->fetchFirstColumn('SELECT name FROM game_character WHERE game_id = ? ORDER BY id', [$game[0]['id']]));
        self::assertSame([], $this->target->fetchFirstColumn('SELECT 1 FROM play_play_tag WHERE play_id = 1001'), 'tag removed from the play');
        self::assertSame([['number' => 2]], $this->rows('SELECT number FROM game_session WHERE party_id = 1 AND number = 2'));

        // co vzniklo jen v nové aplikaci, zůstalo
        self::assertSame(1, $this->rowCount('party') - 2);
        self::assertSame($newParty, (int) $this->target->fetchOne("SELECT id FROM party WHERE name = 'Jen v nové'"));
        self::assertSame(1, (int) $this->target->fetchOne("SELECT COUNT(*) FROM user WHERE email = 'nova@example.com'"));
    }

    public function testExistingRowsAreAdoptedWhenTheMappingIsLost(): void
    {
        $this->import();
        $before = $this->snapshot();
        $this->target->executeStatement('DROP TABLE legacy_map');

        $report = $this->import();

        self::assertSame(0, $report->total('created'), 'nothing duplicated');
        self::assertSame(0, $report->total('updated'));
        self::assertSame($before, $this->snapshot());
    }

    public function testTagsWithTheSameNameInTheOldAppAreMerged(): void
    {
        // stará aplikace dovoluje víc tagů se stejným jménem (jiná id); nová má jméno unikátní
        $this->legacy->insert('tag', ['id' => 302, 'name' => 'Rychlovka']);
        $this->legacy->insert('tag_game', ['tag_id' => 302, 'game_id' => 10]);
        $this->legacy->insert('tag', ['id' => 303, 'name' => 'víkend']);
        $this->legacy->insert('tag_round', ['tag_id' => 303, 'round_id' => 1000]);

        $this->import();
        $this->import();

        self::assertSame(['Rychlovka'], $this->target->fetchFirstColumn('SELECT name FROM game_tag'));
        self::assertSame(2, (int) $this->target->fetchOne('SELECT COUNT(*) FROM game_game_tag'), 'both games keep the merged tag');
        self::assertSame(['víkend'], $this->target->fetchFirstColumn('SELECT name FROM play_tag'));
        self::assertSame(2, (int) $this->target->fetchOne('SELECT COUNT(*) FROM play_play_tag'), 'both plays keep the merged tag');
    }

    public function testRenumberedTagsDoNotCollideWithTheStoredMapping(): void
    {
        $this->import();
        // stará aplikace tagy přečíslovala: číslo 300 teď patří jinému jménu než při prvním importu
        $this->legacy->update('tag', ['name' => 'Rychlovka'], ['id' => 300]);
        $this->legacy->update('tag', ['name' => 'víkend'], ['id' => 301]);

        $this->import();

        self::assertSame(['Rychlovka'], $this->target->fetchFirstColumn('SELECT t.name FROM play_play_tag pt JOIN play_tag t ON t.id = pt.play_tag_id WHERE pt.play_id = 1001'));
        self::assertSame(['víkend'], $this->target->fetchFirstColumn('SELECT t.name FROM game_game_tag x JOIN game_tag t ON t.id = x.game_tag_id JOIN game g ON g.id = x.game_id WHERE g.name = ? AND t.name = ?', ['Azul', 'víkend']));
    }

    public function testDryRunWritesNothing(): void
    {
        $report = $this->import(dryRun: true);

        self::assertTrue($report->dryRun);
        self::assertGreaterThan(0, $report->total('created'));
        foreach (['user', 'party', 'player', 'game', 'play', 'play_result', 'legacy_map'] as $table) {
            self::assertSame(0, $this->rowCount($table), $table);
        }
    }

    public function testAccountsAndIdsOfTheNewAppAreRespected(): void
    {
        // účet se stejným e-mailem a vlastní přezdívkou i Google ID se převezme beze změny
        $this->target->insert('user', ['email' => 'Anna@Example.com', 'nickname' => 'Annie', 'google_id' => 'g-123', 'created_at' => '2025-05-05 05:05:05']);
        $existingUser = (int) $this->target->lastInsertId();
        // zápis nové aplikace obsadil id, které má ve staré aplikaci jiný zápis
        $this->target->insert('party', ['id' => 50, 'name' => 'Cizí', 'short_name' => 'Ci', 'created_at' => '2025-01-01 00:00:00']);
        $this->target->insert('game', ['name' => 'Cizí hra', 'score_type' => 'most_points']);
        $gameId = (int) $this->target->lastInsertId();
        $this->target->insert('party_game', ['party_id' => 50, 'game_id' => $gameId]);
        $this->target->insert('game_session', ['party_id' => 50, 'number' => 1, 'started_at' => '2025-01-01 00:00:00', 'ended_at' => '2025-01-01 00:00:00']);
        $sessionId = (int) $this->target->lastInsertId();
        $this->target->insert('play', ['id' => 1000, 'party_id' => 50, 'game_id' => $gameId, 'session_id' => $sessionId, 'started_at' => '2025-01-01 00:00:00', 'ended_at' => '2025-01-01 00:00:00']);

        $this->import();

        self::assertSame(1, (int) $this->target->fetchOne("SELECT COUNT(*) FROM user WHERE LOWER(email) = 'anna@example.com'"));
        self::assertEquals(['Annie', 'g-123', '2025-05-05 05:05:05'], array_values($this->rows('SELECT nickname, google_id, created_at FROM user WHERE id = ?', [$existingUser])[0]));
        self::assertSame(50, (int) $this->target->fetchOne('SELECT party_id FROM play WHERE id = 1000'), 'the play of the new app keeps its id');
        $imported = (int) $this->target->fetchOne("SELECT new_id FROM legacy_map WHERE entity = 'play' AND legacy_key = '1000'");
        self::assertNotSame(1000, $imported);
        self::assertSame('Pátek', $this->target->fetchOne('SELECT p.name FROM play x JOIN party p ON p.id = x.party_id WHERE x.id = ?', [$imported]));
        // další běh nic nemění a nové zápisy se nezdvojují
        self::assertTrue($this->import()->isNoop());
    }
}
