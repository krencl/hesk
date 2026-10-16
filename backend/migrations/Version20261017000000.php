<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Zkrácený název hry, postavy her (včetně postavy ve výsledku zápisu) a vlastní hráč hry.
 * Dev data ze staré aplikace (`legacy_*`) se přebírají v `postUp()`; hry se tam spárují podle názvu
 * (nová hra vznikla v každé partě, kde se hrála), výsledky podle pozice v rámci zápisu (jako u `is_starting`).
 */
final class Version20261017000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Game short name, characters and own player; carried over from the legacy app';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE game_character (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(150) NOT NULL, game_id INT NOT NULL, INDEX IDX_41DC7136E48FD905 (game_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE game_character ADD CONSTRAINT FK_41DC7136E48FD905 FOREIGN KEY (game_id) REFERENCES game (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE game ADD short_name VARCHAR(50) DEFAULT NULL, ADD player_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE game ADD CONSTRAINT FK_232B318C99E6F5DF FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_232B318C99E6F5DF ON game (player_id)');
        $this->addSql('ALTER TABLE play_result ADD character_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE play_result ADD CONSTRAINT FK_D14CE1531136BE75 FOREIGN KEY (character_id) REFERENCES game_character (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_D14CE1531136BE75 ON play_result (character_id)');
    }

    public function postUp(Schema $schema): void
    {
        $db = $this->connection;
        // legacy_* tabulky schema manager skrývá (schema_filter), proto se existence zjišťuje dotazem
        try {
            $db->fetchOne('SELECT 1 FROM legacy_game LIMIT 1');
        } catch (\Doctrine\DBAL\Exception) {
            return;
        }

        // zkrácený název jen tam, kde se liší od názvu
        $db->executeStatement("UPDATE game g JOIN legacy_game lg ON lg.name = g.name SET g.short_name = lg.short_name WHERE lg.short_name <> '' AND lg.short_name <> lg.name");

        $newGames = []; // legacy game id => [['id' => nová hra, 'party_id' => …], …]
        foreach ($db->fetchAllAssociative('SELECT g.id, g.party_id, lg.id AS legacy_id FROM game g JOIN legacy_game lg ON lg.name = g.name') as $g) {
            $newGames[(int) $g['legacy_id']][] = ['id' => (int) $g['id'], 'party_id' => (int) $g['party_id']];
        }

        // postavy: jedna sada pro každou novou hru
        $characterMap = []; // "legacy postava:nová hra" => nová postava
        foreach ($db->fetchAllAssociative('SELECT id, game_id, name FROM legacy_game_character ORDER BY id') as $c) {
            foreach ($newGames[(int) $c['game_id']] ?? [] as $g) {
                $db->insert('game_character', ['game_id' => $g['id'], 'name' => $c['name']]);
                $characterMap[$c['id'].':'.$g['id']] = (int) $db->lastInsertId();
            }
        }

        // postava ve výsledku: výsledky vznikly po zápisech v pořadí `rp.id`, spárujeme je podle pozice
        $playGame = array_column($db->fetchAllAssociative('SELECT id, game_id FROM play'), 'game_id', 'id');
        $legacy = [];
        foreach ($db->fetchAllAssociative('SELECT round_id, game_character_id, is_winner FROM legacy_round_player ORDER BY round_id, id') as $row) {
            $legacy[(int) $row['round_id']][] = $row;
        }
        $results = [];
        foreach ($db->fetchAllAssociative('SELECT id, play_id, winner FROM play_result ORDER BY play_id, id') as $row) {
            $results[(int) $row['play_id']][] = $row;
        }
        foreach ($legacy as $roundId => $rows) {
            $new = $results[$roundId] ?? [];
            if (\count($new) !== \count($rows)) {
                continue; // nesedí počet výsledků – raději nic nepřenášet než přiřadit špatnému hráči
            }
            foreach ($rows as $i => $row) {
                $characterId = null === $row['game_character_id'] ? null : ($characterMap[$row['game_character_id'].':'.($playGame[$roundId] ?? 0)] ?? null);
                if (null !== $characterId && (bool) $row['is_winner'] === (bool) $new[$i]['winner']) {
                    $db->executeStatement('UPDATE play_result SET character_id = ? WHERE id = ?', [$characterId, $new[$i]['id']]);
                }
            }
        }

        // vlastní hráči her (typ „game“ ve staré aplikaci): hráč stejného jména ve stejné partě
        foreach ($db->fetchAllAssociative("SELECT name, game_id FROM legacy_player WHERE type = 'game' AND game_id IS NOT NULL") as $p) {
            foreach ($newGames[(int) $p['game_id']] ?? [] as $g) {
                $playerId = $db->fetchOne('SELECT id FROM player WHERE party_id = ? AND name = ?', [$g['party_id'], $p['name']]);
                if (false !== $playerId) {
                    $db->executeStatement('UPDATE game SET player_id = ? WHERE id = ?', [$playerId, $g['id']]);
                }
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game_character DROP FOREIGN KEY FK_41DC7136E48FD905');
        $this->addSql('DROP TABLE game_character');
        $this->addSql('ALTER TABLE game DROP FOREIGN KEY FK_232B318C99E6F5DF');
        $this->addSql('DROP INDEX IDX_232B318C99E6F5DF ON game');
        $this->addSql('ALTER TABLE game DROP short_name, DROP player_id');
        $this->addSql('ALTER TABLE play_result DROP FOREIGN KEY FK_D14CE1531136BE75');
        $this->addSql('DROP INDEX IDX_D14CE1531136BE75 ON play_result');
        $this->addSql('ALTER TABLE play_result DROP character_id');
    }
}
