<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Příznak „zobrazovat“ u hráčů (hostů): skrytý host se nenabízí při zápisu hry. */
final class Version20261022000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Player visibility flag';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE player ADD visible TINYINT(1) DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE player DROP visible');
    }
}
