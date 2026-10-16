<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Pořadové číslo seance v partě (`game_session.number`). Dev data ze staré aplikace mají ručně
 * udržovaná čísla (s mezerami), proto se přebírají z `legacy_round`; jinak se číslují chronologicky.
 */
final class Version20261014000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add game_session.number (kept from the legacy app, otherwise chronological)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game_session ADD number INT NOT NULL DEFAULT 0');
    }

    public function postUp(Schema $schema): void
    {
        // Schema manager legacy_* tabulky skrývá (schema_filter), proto se jejich existence zjišťuje dotazem.
        try {
            $this->connection->fetchOne('SELECT 1 FROM legacy_round LIMIT 1');
            $hasLegacy = true;
        } catch (\Doctrine\DBAL\Exception) {
            $hasLegacy = false;
        }
        if ($hasLegacy) {
            $this->connection->executeStatement(
                'UPDATE game_session s SET s.number = COALESCE((SELECT MIN(lr.session_number) FROM legacy_round lr JOIN play p ON p.id = lr.id WHERE p.session_id = s.id), 0)',
            );
        }

        // Seance bez převzatého čísla (nová DB, nebo chybějící legacy data) dostanou číslo podle času.
        $counters = [];
        $rows = $this->connection->fetchAllAssociative('SELECT id, party_id, number FROM game_session ORDER BY party_id, started_at, id');
        $taken = [];
        foreach ($rows as $row) {
            if ((int) $row['number'] > 0) {
                $taken[(int) $row['party_id']][(int) $row['number']] = true;
            }
        }
        foreach ($rows as $row) {
            if ((int) $row['number'] > 0) {
                continue;
            }
            $party = (int) $row['party_id'];
            $n = $counters[$party] ?? 0;
            do {
                ++$n;
            } while (isset($taken[$party][$n]));
            $counters[$party] = $n;
            $this->connection->executeStatement('UPDATE game_session SET number = ? WHERE id = ?', [$n, $row['id']]);
        }

        $this->connection->executeStatement('ALTER TABLE game_session ALTER number DROP DEFAULT');
        $this->connection->executeStatement('CREATE UNIQUE INDEX uniq_session_party_number ON game_session (party_id, number)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_session_party_number ON game_session');
        $this->addSql('ALTER TABLE game_session DROP number');
    }
}
