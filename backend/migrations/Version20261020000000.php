<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Volitelná zkratka party (kulaté tlačítko v hlavičce). */
final class Version20261020000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Party short name';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE party ADD short_name VARCHAR(3) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE party DROP short_name');
    }
}
