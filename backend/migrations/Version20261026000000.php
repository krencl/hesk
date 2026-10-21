<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** BoardGameGeek (ADR 0021): `game.bgg_id` a lokální cache her z BGG `bgg_game`. */
final class Version20261026000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'BGG: game.bgg_id and the bgg_game cache table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game ADD bgg_id INT DEFAULT NULL');
        $this->addSql('CREATE TABLE bgg_game (id INT AUTO_INCREMENT NOT NULL, image_url VARCHAR(255) DEFAULT NULL, fetched_at DATETIME NOT NULL, bgg_id INT NOT NULL, name VARCHAR(255) NOT NULL, published_year INT DEFAULT NULL, UNIQUE INDEX UNIQ_142083ACE01D7737 (bgg_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE bgg_game');
        $this->addSql('ALTER TABLE game DROP bgg_id');
    }
}
