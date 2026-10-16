<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Začínající hráč (`play_result.is_starting`). Dev data ze staré aplikace ho mají v `legacy_round_player`;
 * výsledky se při importu zakládaly po kolech v pořadí `rp.id`, takže se spárují podle pozice v rámci zápisu.
 */
final class Version20261016000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add play_result.is_starting (starting player), carried over from the legacy app';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE play_result ADD is_starting TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function postUp(Schema $schema): void
    {
        // legacy_* tabulky schema manager skrývá (schema_filter), proto se existence zjišťuje dotazem
        try {
            $this->connection->fetchOne('SELECT 1 FROM legacy_round_player LIMIT 1');
        } catch (\Doctrine\DBAL\Exception) {
            $this->connection->executeStatement('ALTER TABLE play_result ALTER is_starting DROP DEFAULT');

            return;
        }

        $legacy = [];
        foreach ($this->connection->fetchAllAssociative('SELECT round_id, is_starting, is_winner FROM legacy_round_player ORDER BY round_id, id') as $row) {
            $legacy[(int) $row['round_id']][] = $row;
        }
        $results = [];
        foreach ($this->connection->fetchAllAssociative('SELECT id, play_id, winner FROM play_result ORDER BY play_id, id') as $row) {
            $results[(int) $row['play_id']][] = $row;
        }
        foreach ($legacy as $roundId => $rows) {
            $new = $results[$roundId] ?? [];
            if (\count($new) !== \count($rows)) {
                continue; // nesedí počet výsledků – raději nic nepřenášet než přiřadit špatnému hráči
            }
            foreach ($rows as $i => $row) {
                if ((int) $row['is_starting'] === 1 && (bool) $row['is_winner'] === (bool) $new[$i]['winner']) {
                    $this->connection->executeStatement('UPDATE play_result SET is_starting = 1 WHERE id = ?', [$new[$i]['id']]);
                }
            }
        }

        $this->connection->executeStatement('ALTER TABLE play_result ALTER is_starting DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE play_result DROP is_starting');
    }
}
