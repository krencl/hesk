<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Jednorázový import dat ze staré aplikace (legacy_* tabulky, viz Version20261003000000).
 * Legacy tabulky zůstávají beze změny. Pravidla převodu: docs/adr/0007-legacy-data-import.md.
 */
final class Version20261003000200 extends AbstractMigration
{
    private const NEW_TABLES = [
        'play_tag', 'play_addon', 'play_result', 'play', 'game_session', 'game_addon', 'game', 'tag', 'place',
        'player', 'party_invitation', 'party_member', 'party', 'app_user',
    ];

    public function getDescription(): string
    {
        return 'Import data from legacy tables';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(!$schema->hasTable('legacy_round'), 'No legacy data to import.');
        $db = $this->connection;
        $prague = new \DateTimeZone('Europe/Prague');
        $utc = new \DateTimeZone('UTC');
        $toUtc = static fn (string $local): string => (new \DateTimeImmutable($local, $prague))->setTimezone($utc)->format('Y-m-d H:i:s');

        // --- uživatelé a party (id zachováno) ---
        foreach ($db->fetchAllAssociative('SELECT u.id, u.email, u.google_id, u.created_at, p.name FROM legacy_user u LEFT JOIN legacy_player p ON p.id = u.player_id') as $u) {
            $db->insert('app_user', [
                'id' => $u['id'], 'google_id' => $u['google_id'] ?: null, 'email' => $u['email'],
                'nickname' => $u['name'], 'created_at' => $toUtc($u['created_at']),
            ]);
        }
        foreach ($db->fetchAllAssociative('SELECT id, name, created_at FROM legacy_party') as $p) {
            $db->insert('party', ['id' => $p['id'], 'name' => $p['name'], 'created_at' => $toUtc($p['created_at'])]);
        }
        foreach ($db->fetchAllAssociative('SELECT party_id, user_id, role FROM legacy_party_user') as $m) {
            $db->insert('party_member', ['party_id' => $m['party_id'], 'user_id' => $m['user_id'], 'role' => 'owner' === $m['role'] ? 'admin' : 'member']);
        }

        // --- hráči: starý hráč je globální, nový patří partě → jeden na dvojici (hráč, parta) ---
        $pairs = $db->fetchAllAssociative(
            'SELECT DISTINCT x.player_id, x.party_id FROM (
                SELECT player_id, party_id FROM legacy_party_player
                UNION SELECT rp.player_id, r.party_id FROM legacy_round_player rp JOIN legacy_round r ON r.id = rp.round_id
                UNION SELECT u.player_id, pu.party_id FROM legacy_user u JOIN legacy_party_user pu ON pu.user_id = u.id WHERE u.player_id IS NOT NULL
             ) x ORDER BY x.party_id, x.player_id',
        );
        $legacyPlayers = array_column($db->fetchAllAssociative('SELECT id, name FROM legacy_player'), 'name', 'id');
        $userByPlayer = array_column($db->fetchAllAssociative('SELECT player_id, id FROM legacy_user WHERE player_id IS NOT NULL'), 'id', 'player_id');
        $members = [];
        foreach ($db->fetchAllAssociative('SELECT party_id, user_id FROM legacy_party_user') as $m) {
            $members[$m['party_id']][$m['user_id']] = true;
        }
        $playerMap = []; // "legacyPlayer:party" => new player id
        $usedNames = [];
        foreach ($pairs as $pair) {
            $legacyId = $pair['player_id'];
            $partyId = $pair['party_id'];
            $base = trim($legacyPlayers[$legacyId]);
            $name = $base;
            for ($i = 2; isset($usedNames[$partyId][mb_strtolower($name)]); ++$i) {
                $name = "$base $i";
            }
            $usedNames[$partyId][mb_strtolower($name)] = true;
            $userId = $userByPlayer[$legacyId] ?? null;
            $db->insert('player', [
                'party_id' => $partyId, 'name' => $name,
                'user_id' => null !== $userId && isset($members[$partyId][$userId]) ? $userId : null,
            ]);
            $playerMap["$legacyId:$partyId"] = (int) $db->lastInsertId();
        }

        // --- hry: globální katalog → jedna hra na dvojici (hra, parta), kde se opravdu hrálo ---
        $scoreTypes = ['highest_score' => 'most_points', 'lowest_score' => 'fewest_points', 'order' => 'rank_order', 'only_winner' => 'winner_only'];
        $legacyGames = array_column($db->fetchAllAssociative('SELECT * FROM legacy_game'), null, 'id');
        $gameMap = []; // "legacyGame:party" => [new id, score type]
        foreach ($db->fetchAllAssociative('SELECT DISTINCT game_id, party_id FROM legacy_round ORDER BY party_id, game_id') as $pair) {
            $g = $legacyGames[$pair['game_id']];
            $db->insert('game', [
                'party_id' => $pair['party_id'], 'name' => $g['name'], 'score_type' => $scoreTypes[$g['scoring_type']],
                'cheat_sheet' => $g['cheat_sheet'] ?: null,
            ]);
            $gameMap[$pair['game_id'].':'.$pair['party_id']] = [(int) $db->lastInsertId(), $scoreTypes[$g['scoring_type']]];
        }

        // --- verze hry → rozšíření (verze „Základní“ = hra sama, rozšíření nevzniká) ---
        $versions = array_column($db->fetchAllAssociative('SELECT id, game_id, name FROM legacy_game_version'), null, 'id');
        $addonMap = []; // "version:party" => addon id
        foreach ($db->fetchAllAssociative('SELECT DISTINCT game_version_id, party_id FROM legacy_round') as $pair) {
            $v = $versions[$pair['game_version_id']];
            if ('Základní' === trim($v['name'])) {
                continue;
            }
            $db->insert('game_addon', ['game_id' => $gameMap[$v['game_id'].':'.$pair['party_id']][0], 'name' => $v['name']]);
            $addonMap[$pair['game_version_id'].':'.$pair['party_id']] = (int) $db->lastInsertId();
        }

        // --- seance: zachováváme staré session_number (ručně udržované), hranice z časů kol ---
        $sessionMap = []; // "party:number" => session id
        foreach ($db->fetchAllAssociative('SELECT party_id, session_number, MIN(created_at) a, MAX(created_at) b FROM legacy_round GROUP BY party_id, session_number') as $s) {
            $db->insert('game_session', ['party_id' => $s['party_id'], 'started_at' => $toUtc($s['a']), 'ended_at' => $toUtc($s['b'])]);
            $sessionMap[$s['party_id'].':'.$s['session_number']] = (int) $db->lastInsertId();
        }

        // --- tagy kol (patří partě) ---
        $tagNames = array_column($db->fetchAllAssociative('SELECT id, name FROM legacy_tag'), 'name', 'id');
        $tagMap = [];
        $tagRows = $db->fetchAllAssociative('SELECT DISTINCT tr.tag_id, r.party_id FROM legacy_tag_round tr JOIN legacy_round r ON r.id = tr.round_id ORDER BY r.party_id, tr.tag_id');
        foreach ($tagRows as $t) {
            $db->insert('tag', ['party_id' => $t['party_id'], 'name' => $tagNames[$t['tag_id']]]);
            $tagMap[$t['tag_id'].':'.$t['party_id']] = (int) $db->lastInsertId();
        }

        // --- zápisy (id kola zachováno) ---
        foreach ($db->fetchAllAssociative('SELECT * FROM legacy_round ORDER BY id') as $r) {
            $when = $toUtc($r['created_at']);
            $db->insert('play', [
                'id' => $r['id'], 'party_id' => $r['party_id'], 'game_id' => $gameMap[$r['game_id'].':'.$r['party_id']][0],
                'session_id' => $sessionMap[$r['party_id'].':'.$r['session_number']], 'started_at' => $when, 'ended_at' => $when,
                'notes' => $r['note'] ?: null,
            ]);
            if ($addon = $addonMap[$r['game_version_id'].':'.$r['party_id']] ?? null) {
                $db->insert('play_addon', ['play_id' => $r['id'], 'game_addon_id' => $addon]);
            }
        }
        foreach ($db->fetchAllAssociative('SELECT tr.tag_id, tr.round_id, r.party_id FROM legacy_tag_round tr JOIN legacy_round r ON r.id = tr.round_id') as $t) {
            $db->insert('play_tag', ['play_id' => $t['round_id'], 'tag_id' => $tagMap[$t['tag_id'].':'.$t['party_id']]]);
        }

        // --- výsledky: pořadí ve staré DB není, dopočítáme ho podle typu bodování ---
        $rows = $db->fetchAllAssociative('SELECT rp.round_id, rp.player_id, rp.score, rp.is_winner, r.party_id, r.game_id FROM legacy_round_player rp JOIN legacy_round r ON r.id = rp.round_id ORDER BY rp.round_id, rp.id');
        $byRound = [];
        foreach ($rows as $row) {
            $byRound[$row['round_id']][] = $row;
        }
        foreach ($byRound as $roundId => $players) {
            $type = $gameMap[$players[0]['game_id'].':'.$players[0]['party_id']][1];
            foreach ($players as $row) {
                $score = null !== $row['score'] ? (float) $row['score'] : null;
                $winner = (bool) $row['is_winner'];
                $rank = $winner ? 1 : 2;
                if ('rank_order' === $type) {
                    $rank = null !== $score ? max(1, (int) $score) : $rank;
                    $score = null;
                } elseif (null !== $score && \in_array($type, ['most_points', 'fewest_points'], true)) {
                    $better = 0;
                    foreach ($players as $o) {
                        if (null !== $o['score'] && ('most_points' === $type ? (float) $o['score'] > $score : (float) $o['score'] < $score)) {
                            ++$better;
                        }
                    }
                    $rank = $better + 1;
                } else {
                    $score = null;
                }
                $db->insert('play_result', [
                    'play_id' => $roundId, 'player_id' => $playerMap[$row['player_id'].':'.$row['party_id']],
                    'score' => $score, 'placement' => $rank, 'winner' => (int) $winner,
                ]);
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::NEW_TABLES as $table) {
            $this->addSql("TRUNCATE TABLE `$table`");
        }
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1');
    }
}
