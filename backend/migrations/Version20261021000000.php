<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Domain\PartyShortName;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Zkratka party je povinná: partám bez ní se vygeneruje z názvu (`PartyShortName`) a sloupec se nastaví na NOT NULL. */
final class Version20261021000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Party short name is required; generate it for existing parties';
    }

    public function up(Schema $schema): void
    {
        // sloupec ze Version20261020000000 (nullable) zůstává do postUp(), kde se doplní hodnoty
    }

    public function postUp(Schema $schema): void
    {
        foreach ($this->connection->fetchAllAssociative("SELECT id, name FROM party WHERE short_name IS NULL OR TRIM(short_name) = ''") as $party) {
            $this->connection->update('party', ['short_name' => PartyShortName::generate((string) $party['name'])], ['id' => $party['id']]);
        }
        $this->connection->executeStatement('ALTER TABLE party CHANGE short_name short_name VARCHAR(3) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE party CHANGE short_name short_name VARCHAR(3) DEFAULT NULL');
    }
}
