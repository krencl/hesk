<?php

declare(strict_types=1);

namespace App\Legacy;

use App\Domain\PartyShortName;
use Doctrine\DBAL\Connection;

/**
 * Opakovatelná synchronizace dat ze staré aplikace (databáze `hesk-old`, jen čtení) do aktuální databáze.
 *
 * Vazba „staré → nové“ se drží v tabulce `legacy_map`, takže další běh jen aktualizuje a přidává nové.
 * Při prvním běhu (prázdná vazba) se už existující řádky *převezmou* podle přirozených klíčů (název v partě,
 * e-mail, id zápisu…), nic se nemaže ani nezdvojuje. Co vzniklo jen v nové aplikaci (bez vazby), se nemění.
 * Zápisy a výsledky smazané ve staré aplikaci se smažou i v nové. Pravidla převodu: docs/adr/0007, 0012.
 */
final class LegacyImporter
{
    private const SCORE_TYPES = ['highest_score' => 'most_points', 'lowest_score' => 'fewest_points', 'order' => 'rank_order', 'only_winner' => 'winner_only'];
    private const BASE_VERSION = 'Základní';

    /** @var array<string, array<string, int>> entita => klíč ve staré DB => id v nové DB */
    private array $map = [];
    /** @var array<string, array<int, true>> */
    private array $mapped = [];
    private ImportReport $report;
    private bool $dryRun = false;
    private readonly \DateTimeZone $prague;
    private readonly \DateTimeZone $utc;

    public function __construct(
        private readonly Connection $legacy,
        private readonly Connection $target,
        /** Adresář s obálkami ze staré aplikace (zdroj kopírování). */
        private readonly ?string $coversSource = null,
        /** Adresář s obálkami nové aplikace; hra dostane `image_url`, jen pokud tam soubor je (nebo se tam zkopíruje). */
        private readonly ?string $coversTarget = null,
        private readonly string $coverUrlPrefix = '/static/covers/',
    ) {
        $this->prague = new \DateTimeZone('Europe/Prague');
        $this->utc = new \DateTimeZone('UTC');
    }

    public function import(bool $dryRun = false): ImportReport
    {
        $this->dryRun = $dryRun;
        $this->report = new ImportReport();
        $this->report->dryRun = $dryRun;
        $this->map = [];
        $this->mapped = [];
        // DDL mimo transakci (MySQL by ji jinak potvrdil předčasně)
        $this->target->executeStatement('CREATE TABLE IF NOT EXISTS legacy_map (entity VARCHAR(20) NOT NULL, legacy_key VARCHAR(60) NOT NULL, new_id INT NOT NULL, PRIMARY KEY (entity, legacy_key))');

        $this->target->beginTransaction();
        try {
            $this->loadMap();
            $this->run();
            $dryRun ? $this->target->rollBack() : $this->target->commit();
        } catch (\Throwable $e) {
            if ($this->target->isTransactionActive()) {
                $this->target->rollBack();
            }
            throw $e;
        }

        return $this->report;
    }

    private function run(): void
    {
        $legacy = $this->readLegacy();
        $this->deleteVanished($legacy);
        $this->importUsersAndParties($legacy);
        $this->importPlayers($legacy);
        $this->importCatalog($legacy);
        $this->importSessions($legacy);
        $this->importPlays($legacy);
        $this->deleteEmptySessions($legacy);
    }

    /** @return array<string, mixed> */
    private function readLegacy(): array
    {
        $all = fn (string $sql): array => $this->legacy->fetchAllAssociative($sql);
        $byId = static fn (array $rows): array => array_column($rows, null, 'id');

        $d = [
            'users' => $all('SELECT u.id, u.email, u.google_id, u.created_at, p.name AS nickname FROM user u LEFT JOIN player p ON p.id = u.player_id ORDER BY u.id'),
            'parties' => $all('SELECT id, name, created_at FROM party ORDER BY id'),
            'partyUsers' => $all('SELECT party_id, user_id, role FROM party_user'),
            'players' => $byId($all('SELECT id, name, type, game_id FROM player')),
            'userPlayer' => array_column($all('SELECT id, player_id FROM user WHERE player_id IS NOT NULL'), 'id', 'player_id'),
            'partyPlayers' => $all('SELECT party_id, player_id FROM party_player'),
            'games' => $byId($all('SELECT id, name, short_name, scoring_type, cover_image, cheat_sheet FROM game')),
            'versions' => $all('SELECT id, game_id, name FROM game_version ORDER BY id'),
            'characters' => $all('SELECT id, game_id, name FROM game_character ORDER BY id'),
            'tags' => array_column($all('SELECT id, name FROM tag'), 'name', 'id'),
            'rounds' => $all('SELECT id, party_id, game_id, game_version_id, session_number, note, created_at FROM round ORDER BY id'),
            'roundPlayers' => $all('SELECT id, round_id, player_id, game_character_id, score, is_winner, is_starting FROM round_player ORDER BY round_id, id'),
            'tagRounds' => $all('SELECT tag_id, round_id FROM tag_round'),
        ];
        $d['versionsById'] = $byId($d['versions']);
        $d['roundsById'] = $byId($d['rounds']);
        $d['playersByRound'] = [];
        foreach ($d['roundPlayers'] as $rp) {
            $d['playersByRound'][(int) $rp['round_id']][] = $rp;
        }

        return $d;
    }

    // ------------------------------------------------------------------ smazané ve staré aplikaci

    /** @param array<string, mixed> $d */
    private function deleteVanished(array $d): void
    {
        $roundIds = array_map('strval', array_column($d['rounds'], 'id'));
        $resultIds = array_map('strval', array_column($d['roundPlayers'], 'id'));
        foreach (['result' => ['play_result', $resultIds], 'play' => ['play', $roundIds]] as $entity => [$table, $alive]) {
            foreach ($this->map[$entity] ?? [] as $key => $id) {
                if (!\in_array((string) $key, $alive, true)) {
                    if ('play' === $entity) {
                        // závislé řádky mažeme sami, ať nezáleží na kaskádě v databázi
                        foreach (['play_result', 'play_tag', 'play_addon'] as $child) {
                            $this->target->delete($child, ['play_id' => $id]);
                        }
                    }
                    $this->target->delete($table, ['id' => $id]);
                    $this->forget($entity, (string) $key);
                    $this->report->count($entity, 'deleted');
                }
            }
        }
        // výsledky smazaných zápisů jsou pryč; vazby na ně uklidíme
        foreach ($this->map['result'] ?? [] as $key => $id) {
            if (!$this->exists('play_result', $id)) {
                $this->forget('result', (string) $key);
            }
        }
    }

    // ------------------------------------------------------------------ uživatelé, party, členové

    /** @param array<string, mixed> $d */
    private function importUsersAndParties(array $d): void
    {
        foreach ($d['users'] as $u) {
            $email = (string) $u['email'];
            $existing = $this->map('user', (string) $u['id']);
            $id = $this->upsert('user', (string) $u['id'], 'app_user', fn () => $this->findId('SELECT id FROM app_user WHERE LOWER(email) = LOWER(?)', [$email]), ['email' => $email], (int) $u['id'], $this->createdAt($u['created_at']));
            // přezdívku a Google účet z nové aplikace nepřepisujeme; vyplníme jen chybějící
            $row = $this->target->fetchAssociative('SELECT nickname, google_id FROM app_user WHERE id = ?', [$id]);
            $fill = [];
            if (null === $row['nickname'] && null !== $u['nickname'] && '' !== trim((string) $u['nickname'])) {
                $fill['nickname'] = trim((string) $u['nickname']);
            }
            if (null === $row['google_id'] && !empty($u['google_id']) && 0 === (int) $this->target->fetchOne('SELECT COUNT(*) FROM app_user WHERE google_id = ?', [$u['google_id']])) {
                $fill['google_id'] = $u['google_id'];
            }
            if ($fill) {
                $this->target->update('app_user', $fill, ['id' => $id]);
                if (null !== $existing) {
                    $this->report->count('user', 'updated');
                }
            }
        }

        foreach ($d['parties'] as $p) {
            $name = (string) $p['name'];
            $this->upsert(
                'party',
                (string) $p['id'],
                'party',
                fn () => $this->findId('SELECT id FROM party WHERE id = ? AND name = ?', [(int) $p['id'], $name]),
                ['name' => $name],
                (int) $p['id'],
                $this->createdAt($p['created_at']) + ['short_name' => PartyShortName::generate($name)],
            );
        }

        $seen = [];
        foreach ($d['partyUsers'] as $m) {
            $partyId = $this->map('party', (string) $m['party_id']);
            $userId = $this->map('user', (string) $m['user_id']);
            if (null === $partyId || null === $userId) {
                continue;
            }
            $role = 'owner' === $m['role'] ? 'admin' : 'member';
            $seen["$partyId:$userId"] = true;
            $row = $this->target->fetchAssociative('SELECT id, role FROM party_member WHERE party_id = ? AND user_id = ?', [$partyId, $userId]);
            if (!$row) {
                $this->target->insert('party_member', ['party_id' => $partyId, 'user_id' => $userId, 'role' => $role]);
                $this->report->count('member', 'created');
            } elseif ($row['role'] !== $role) {
                $this->target->update('party_member', ['role' => $role], ['id' => $row['id']]);
                $this->report->count('member', 'updated');
            } else {
                $this->report->count('member', 'unchanged');
            }
        }
        // člen, který se ze staré party vytratil (a jeho účet je ze staré aplikace), z party odejde
        foreach ($this->map['party'] ?? [] as $legacyPartyId => $partyId) {
            foreach ($this->target->fetchAllAssociative('SELECT id, user_id FROM party_member WHERE party_id = ?', [$partyId]) as $row) {
                $isImportedUser = \in_array((int) $row['user_id'], $this->map['user'] ?? [], true);
                if ($isImportedUser && !isset($seen["$partyId:{$row['user_id']}"]) && $this->hasOtherAdmin((int) $partyId, (int) $row['id'])) {
                    $this->target->delete('party_member', ['id' => $row['id']]);
                    $this->report->count('member', 'deleted');
                }
            }
        }
    }

    private function hasOtherAdmin(int $partyId, int $exceptMemberId): bool
    {
        return (int) $this->target->fetchOne("SELECT COUNT(*) FROM party_member WHERE party_id = ? AND role = 'admin' AND id <> ?", [$partyId, $exceptMemberId]) > 0;
    }

    // ------------------------------------------------------------------ hráči

    /**
     * Hráč je ve staré aplikaci globální, v nové patří partě: jeden na dvojici (hráč, parta).
     *
     * @param array<string, mixed> $d
     */
    private function importPlayers(array $d): void
    {
        $pairs = [];
        $add = static function (int $player, int $party) use (&$pairs): void {
            $pairs["$party:$player"] = [$player, $party];
        };
        foreach ($d['partyPlayers'] as $r) {
            $add((int) $r['player_id'], (int) $r['party_id']);
        }
        foreach ($d['roundPlayers'] as $rp) {
            $add((int) $rp['player_id'], (int) $d['roundsById'][(int) $rp['round_id']]['party_id']);
        }
        foreach ($d['partyUsers'] as $m) {
            foreach ($d['userPlayer'] as $playerId => $userId) {
                if ((int) $userId === (int) $m['user_id']) {
                    $add((int) $playerId, (int) $m['party_id']);
                }
            }
        }
        $gamePairs = $this->gamePairs($d);
        foreach ($d['players'] as $p) {
            if ('game' === $p['type'] && null !== $p['game_id']) {
                foreach ($gamePairs as [$gameId, $partyId]) {
                    if ($gameId === (int) $p['game_id']) {
                        $add((int) $p['id'], $partyId);
                    }
                }
            }
        }
        $pairs = array_values($pairs);
        usort($pairs, static fn (array $a, array $b): int => [$a[1], $a[0]] <=> [$b[1], $b[0]]);

        $members = [];
        foreach ($d['partyUsers'] as $m) {
            $members[(int) $m['party_id']][(int) $m['user_id']] = true;
        }
        $used = []; // nová parta => malé jméno => true (v tomto běhu)
        foreach ($pairs as [$legacyId, $legacyPartyId]) {
            $partyId = $this->map('party', (string) $legacyPartyId);
            if (null === $partyId || !isset($d['players'][$legacyId])) {
                continue;
            }
            $key = "$legacyId:$legacyPartyId";
            $base = trim((string) $d['players'][$legacyId]['name']);
            $mappedId = $this->map('player', $key);
            $linkedUser = null === $mappedId ? false : $this->target->fetchOne('SELECT user_id FROM player WHERE id = ?', [$mappedId]);
            $linkedToAccount = false !== $linkedUser && null !== $linkedUser;

            // jméno: u hráče s účtem ho nechává nová aplikace (přezdívka), jinak jméno ze staré + číslo při shodě
            $name = $base;
            if ($linkedToAccount) {
                $name = (string) $this->target->fetchOne('SELECT name FROM player WHERE id = ?', [$mappedId]);
            } else {
                for ($i = 2; isset($used[$partyId][mb_strtolower($name)]) || $this->nameTakenByOther($partyId, $name, $mappedId); ++$i) {
                    $name = "$base $i";
                }
            }
            $used[$partyId][mb_strtolower($name)] = true;

            $legacyUserId = $d['userPlayer'][$legacyId] ?? null;
            $userId = null !== $legacyUserId && isset($members[$legacyPartyId][$legacyUserId]) ? $this->map('user', (string) $legacyUserId) : null;
            $this->upsert(
                'player',
                $key,
                'player',
                fn () => $this->findId('SELECT id FROM player WHERE party_id = ? AND LOWER(name) = LOWER(?)', [$partyId, $name]),
                ['party_id' => $partyId, 'name' => $name, 'user_id' => $userId],
            );
        }
    }

    /** Je jméno v partě obsazené jiným (ještě nepřevzatým nebo cizím) hráčem? Převzetí podle jména řeší adopce. */
    private function nameTakenByOther(int $partyId, string $name, ?int $ownId): bool
    {
        $row = $this->target->fetchAssociative('SELECT id FROM player WHERE party_id = ? AND LOWER(name) = LOWER(?)', [$partyId, $name]);
        if (!$row || (int) $row['id'] === $ownId) {
            return false;
        }

        // obsazené hráčem, který patří jiné staré dvojici (hráč, parta) → musíme vybrat jiné jméno
        return isset($this->mapped['player'][(int) $row['id']]);
    }

    // ------------------------------------------------------------------ hry, rozšíření, postavy, tagy

    /**
     * @param array<string, mixed> $d
     *
     * @return list<array{0: int, 1: int}> dvojice (stará hra, stará parta), kde se hrálo
     */
    private function gamePairs(array $d): array
    {
        $pairs = [];
        foreach ($d['rounds'] as $r) {
            $pairs[$r['game_id'].':'.$r['party_id']] = [(int) $r['game_id'], (int) $r['party_id']];
        }
        ksort($pairs, \SORT_NATURAL);

        return array_values($pairs);
    }

    /** @param array<string, mixed> $d */
    private function importCatalog(array $d): void
    {
        $gamePairs = $this->gamePairs($d);
        foreach ($gamePairs as [$gameId, $legacyPartyId]) {
            $partyId = $this->map('party', (string) $legacyPartyId);
            $g = $d['games'][$gameId] ?? null;
            if (null === $partyId || null === $g) {
                continue;
            }
            $name = (string) $g['name'];
            $short = trim((string) $g['short_name']);
            $values = [
                'party_id' => $partyId,
                'name' => $name,
                'short_name' => '' !== $short && $short !== $name ? $short : null,
                'score_type' => self::SCORE_TYPES[$g['scoring_type']] ?? 'most_points',
                'cheat_sheet' => '' !== trim((string) $g['cheat_sheet']) ? $g['cheat_sheet'] : null,
            ];
            if (null !== ($cover = $this->coverUrl($g['cover_image']))) {
                $values['image_url'] = $cover;
            }
            // vlastní hráč hry (typ „game“ ve staré aplikaci)
            $ownPlayer = null;
            foreach ($d['players'] as $p) {
                if ('game' === $p['type'] && (int) $p['game_id'] === $gameId) {
                    $ownPlayer = $this->map('player', $p['id'].':'.$legacyPartyId);
                    break;
                }
            }
            $values['player_id'] = $ownPlayer;
            $newGameId = $this->upsert('game', "$gameId:$legacyPartyId", 'game', fn () => $this->findId('SELECT id FROM game WHERE party_id = ? AND LOWER(name) = LOWER(?)', [$partyId, $name]), $values);

            // rozšíření = verze hry kromě „Základní“ (ta je hra sama)
            foreach ($d['versions'] as $v) {
                if ((int) $v['game_id'] !== $gameId || self::BASE_VERSION === trim((string) $v['name'])) {
                    continue;
                }
                $vName = trim((string) $v['name']);
                $this->upsert('addon', $v['id'].":$legacyPartyId", 'game_addon', fn () => $this->findId('SELECT id FROM game_addon WHERE game_id = ? AND LOWER(name) = LOWER(?)', [$newGameId, $vName]), ['game_id' => $newGameId, 'name' => $vName]);
            }
            foreach ($d['characters'] as $c) {
                if ((int) $c['game_id'] !== $gameId) {
                    continue;
                }
                $cName = trim((string) $c['name']);
                $this->upsert('character', $c['id'].":$legacyPartyId", 'game_character', fn () => $this->findId('SELECT id FROM game_character WHERE game_id = ? AND LOWER(name) = LOWER(?)', [$newGameId, $cName]), ['game_id' => $newGameId, 'name' => $cName]);
            }
        }

        // tagy zápisů (patří partě)
        $tagPairs = [];
        $partyByRound = array_column($d['rounds'], 'party_id', 'id');
        foreach ($d['tagRounds'] as $tr) {
            if (isset($partyByRound[$tr['round_id']])) {
                $tagPairs[$tr['tag_id'].':'.$partyByRound[$tr['round_id']]] = [(int) $tr['tag_id'], (int) $partyByRound[$tr['round_id']]];
            }
        }
        ksort($tagPairs, \SORT_NATURAL);
        foreach ($tagPairs as [$tagId, $legacyPartyId]) {
            $partyId = $this->map('party', (string) $legacyPartyId);
            $tName = trim((string) ($d['tags'][$tagId] ?? ''));
            if (null === $partyId || '' === $tName) {
                continue;
            }
            $this->upsert('tag', "$tagId:$legacyPartyId", 'tag', fn () => $this->findId('SELECT id FROM tag WHERE party_id = ? AND LOWER(name) = LOWER(?)', [$partyId, $tName]), ['party_id' => $partyId, 'name' => $tName]);
        }
    }

    private function coverUrl(?string $file): ?string
    {
        $file = trim((string) $file);
        if ('' === $file || basename($file) !== $file || null === $this->coversTarget) {
            return null;
        }
        $to = rtrim($this->coversTarget, '/').'/'.$file;
        if (!is_file($to) && null !== $this->coversSource && is_file($from = rtrim($this->coversSource, '/').'/'.$file) && !$this->dryRun) {
            if (!is_dir($this->coversTarget)) {
                mkdir($this->coversTarget, 0775, true);
            }
            copy($from, $to);
            ++$this->report->coversCopied;
        }

        return is_file($to) || (null !== $this->coversSource && $this->dryRun && is_file(rtrim($this->coversSource, '/').'/'.$file)) ? $this->coverUrlPrefix.$file : null;
    }

    // ------------------------------------------------------------------ seance, zápisy, výsledky

    /** @param array<string, mixed> $d */
    private function importSessions(array $d): void
    {
        $bounds = [];
        foreach ($d['rounds'] as $r) {
            $k = $r['party_id'].':'.$r['session_number'];
            $at = $this->toUtc((string) $r['created_at']);
            $bounds[$k] = [min($bounds[$k][0] ?? $at, $at), max($bounds[$k][1] ?? $at, $at), (int) $r['party_id'], (int) $r['session_number']];
        }
        ksort($bounds, \SORT_NATURAL);
        foreach ($bounds as $key => [$start, $end, $legacyPartyId, $number]) {
            $partyId = $this->map('party', (string) $legacyPartyId);
            if (null === $partyId) {
                continue;
            }
            $this->upsert(
                'session',
                $key,
                'game_session',
                fn () => $this->findId('SELECT id FROM game_session WHERE party_id = ? AND number = ?', [$partyId, $number]),
                ['party_id' => $partyId, 'number' => $number, 'started_at' => $start, 'ended_at' => $end],
            );
        }
    }

    /** @param array<string, mixed> $d */
    private function importPlays(array $d): void
    {
        $tagsByRound = [];
        foreach ($d['tagRounds'] as $tr) {
            $tagsByRound[(int) $tr['round_id']][] = (int) $tr['tag_id'];
        }

        foreach ($d['rounds'] as $r) {
            $roundId = (int) $r['id'];
            $legacyParty = (int) $r['party_id'];
            $partyId = $this->map('party', (string) $legacyParty);
            $gameId = $this->map('game', $r['game_id'].":$legacyParty");
            $sessionId = $this->map('session', $legacyParty.':'.$r['session_number']);
            if (null === $partyId || null === $gameId || null === $sessionId) {
                continue;
            }
            $when = $this->toUtc((string) $r['created_at']);
            $playId = $this->upsert(
                'play',
                (string) $roundId,
                'play',
                fn () => $this->findId('SELECT id FROM play WHERE id = ? AND party_id = ?', [$roundId, $partyId]),
                ['party_id' => $partyId, 'game_id' => $gameId, 'session_id' => $sessionId, 'started_at' => $when, 'ended_at' => $when, 'notes' => '' !== trim((string) $r['note']) ? $r['note'] : null],
                $roundId,
            );

            // rozšíření (verze hry kromě základní) a tagy zápisu
            $addon = null;
            $version = $d['versionsById'][(int) $r['game_version_id']] ?? null;
            if ($version && self::BASE_VERSION !== trim((string) $version['name'])) {
                $addon = $this->map('addon', $version['id'].":$legacyParty");
            }
            $this->syncLinks('play_addon', 'game_addon_id', $playId, null === $addon ? [] : [$addon]);
            $tagIds = array_values(array_filter(array_map(fn (int $t) => $this->map('tag', "$t:$legacyParty"), $tagsByRound[$roundId] ?? [])));
            $this->syncLinks('play_tag', 'tag_id', $playId, $tagIds);

            $this->importResults($d, $r, $playId, $partyId, $gameId);
        }
    }

    /**
     * @param array<string, mixed> $d
     * @param array<string, mixed> $round
     */
    private function importResults(array $d, array $round, int $playId, int $partyId, int $gameId): void
    {
        $legacyParty = (int) $round['party_id'];
        $type = self::SCORE_TYPES[$d['games'][(int) $round['game_id']]['scoring_type']] ?? 'most_points';
        $players = $d['playersByRound'][(int) $round['id']] ?? [];

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

            $playerId = $this->map('player', $row['player_id'].":$legacyParty");
            if (null === $playerId) {
                continue;
            }
            $characterId = null === $row['game_character_id'] ? null : $this->map('character', $row['game_character_id'].":$legacyParty");
            $this->upsert(
                'result',
                (string) $row['id'],
                'play_result',
                fn () => $this->findId('SELECT id FROM play_result WHERE play_id = ? AND player_id = ?', [$playId, $playerId]),
                ['play_id' => $playId, 'player_id' => $playerId, 'score' => $score, 'placement' => $rank, 'winner' => (int) $winner, 'is_starting' => (int) (bool) $row['is_starting'], 'character_id' => $characterId],
            );
        }
        unset($partyId, $gameId);
    }

    /** @param list<int> $wanted */
    private function syncLinks(string $table, string $column, int $playId, array $wanted): void
    {
        $have = array_map('intval', $this->target->fetchFirstColumn("SELECT $column FROM $table WHERE play_id = ?", [$playId]));
        foreach (array_diff($have, $wanted) as $id) {
            $this->target->delete($table, ['play_id' => $playId, $column => $id]);
        }
        foreach (array_diff(array_unique($wanted), $have) as $id) {
            $this->target->insert($table, ['play_id' => $playId, $column => $id]);
        }
    }

    /** Seance, které ze staré aplikace zmizely a nemají žádný zápis, se smažou (jen ty, které import založil). */
    private function deleteEmptySessions(array $d): void
    {
        $alive = [];
        foreach ($d['rounds'] as $r) {
            $alive[$r['party_id'].':'.$r['session_number']] = true;
        }
        foreach ($this->map['session'] ?? [] as $key => $id) {
            if (!isset($alive[$key]) && 0 === (int) $this->target->fetchOne('SELECT COUNT(*) FROM play WHERE session_id = ?', [$id])) {
                $this->target->delete('game_session', ['id' => $id]);
                $this->forget('session', (string) $key);
                $this->report->count('session', 'deleted');
            }
        }
    }

    // ------------------------------------------------------------------ pomocné funkce

    /**
     * Datum vzniku pro nově vložený řádek (nikdy se nepřepisuje existující: ve staré DB bývá po obnově „dnes“).
     * Chybí-li ve zdroji (NULL, prázdné, 0000-00-00), použije se aktuální čas.
     *
     * @return array{created_at: string}
     */
    private function createdAt(mixed $legacy): array
    {
        $value = trim((string) $legacy);
        if ('' === $value || str_starts_with($value, '0000')) {
            return ['created_at' => (new \DateTimeImmutable('now', $this->utc))->format('Y-m-d H:i:s')];
        }

        return ['created_at' => $this->toUtc($value)];
    }

    private function toUtc(string $local): string
    {
        return (new \DateTimeImmutable($local, $this->prague))->setTimezone($this->utc)->format('Y-m-d H:i:s');
    }

    private function map(string $entity, string $key): ?int
    {
        return $this->map[$entity][$key] ?? null;
    }

    private function loadMap(): void
    {
        foreach ($this->target->fetchAllAssociative('SELECT entity, legacy_key, new_id FROM legacy_map') as $row) {
            $this->map[$row['entity']][$row['legacy_key']] = (int) $row['new_id'];
            $this->mapped[$row['entity']][(int) $row['new_id']] = true;
        }
    }

    private function remember(string $entity, string $key, int $id): void
    {
        $this->target->delete('legacy_map', ['entity' => $entity, 'legacy_key' => $key]);
        $this->target->insert('legacy_map', ['entity' => $entity, 'legacy_key' => $key, 'new_id' => $id]);
        $this->map[$entity][$key] = $id;
        $this->mapped[$entity][$id] = true;
    }

    private function forget(string $entity, string $key): void
    {
        $id = $this->map[$entity][$key] ?? null;
        $this->target->delete('legacy_map', ['entity' => $entity, 'legacy_key' => $key]);
        unset($this->map[$entity][$key]);
        if (null !== $id) {
            unset($this->mapped[$entity][$id]);
        }
    }

    private function exists(string $table, int $id): bool
    {
        return false !== $this->target->fetchOne("SELECT 1 FROM $table WHERE id = ?", [$id]);
    }

    /** @param list<mixed> $params */
    private function findId(string $sql, array $params): ?int
    {
        $id = $this->target->fetchOne($sql, $params);

        return false === $id ? null : (int) $id;
    }

    /**
     * Vloží, aktualizuje nebo převezme řádek podle vazby `legacy_map`.
     *
     * @param array<string, mixed> $values   sloupce, které import vlastní (přepisuje je)
     * @param ?callable(): ?int    $adopt    hledá existující, dosud nepřevzatý řádek podle přirozeného klíče
     * @param ?int                 $preferredId staré id, pokud je v nové DB volné
     * @param array<string, mixed> $createOnly  výchozí hodnoty jen pro nově vložený řádek (existující se jimi nepřepíše)
     */
    private function upsert(string $entity, string $key, string $table, ?callable $adopt, array $values, ?int $preferredId = null, array $createOnly = []): int
    {
        $id = $this->map[$entity][$key] ?? null;
        if (null !== $id && !$this->exists($table, $id)) {
            $this->forget($entity, $key);
            $id = null;
        }
        if (null === $id && null !== $adopt) {
            $candidate = $adopt();
            if (null !== $candidate && !isset($this->mapped[$entity][$candidate])) {
                $id = $candidate;
                $this->remember($entity, $key, $id);
            }
        }
        if (null !== $id) {
            $this->report->count($entity, $this->update($table, $id, $values) ? 'updated' : 'unchanged');

            return $id;
        }

        $values = $values + $createOnly;
        if (null !== $preferredId && !$this->exists($table, $preferredId)) {
            $this->target->insert($table, ['id' => $preferredId] + $values);
            $id = $preferredId;
        } else {
            $this->target->insert($table, $values);
            $id = (int) $this->target->lastInsertId();
        }
        $this->remember($entity, $key, $id);
        $this->report->count($entity, 'created');

        return $id;
    }

    /** @param array<string, mixed> $values */
    private function update(string $table, int $id, array $values): bool
    {
        $row = $this->target->fetchAssociative("SELECT * FROM $table WHERE id = ?", [$id]) ?: [];
        $changes = [];
        foreach ($values as $column => $value) {
            if (!$this->same($row[$column] ?? null, $value)) {
                $changes[$column] = $value;
            }
        }
        if ($changes) {
            foreach ($changes as $column => $value) {
                $this->report->changes[] = \sprintf('%s #%d: %s: %s → %s', $table, $id, $column, $this->short($row[$column] ?? null), $this->short($value));
            }
            $this->target->update($table, $changes, ['id' => $id]);
        }

        return [] !== $changes;
    }

    private function short(mixed $value): string
    {
        return null === $value ? 'NULL' : mb_strimwidth((string) $value, 0, 40, '…');
    }

    private function same(mixed $a, mixed $b): bool
    {
        if (null === $a || null === $b) {
            return null === $a && null === $b;
        }
        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a === (float) $b;
        }

        return (string) $a === (string) $b;
    }
}
