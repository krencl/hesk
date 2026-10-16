<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Místa zápisů zrušena: existující místo se převede na tag zápisu (ADR 0013), tabulka `place` a `play.place_id` zmizí. */
final class Version20261018000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop places; existing places become tags of their plays';
    }

    public function up(Schema $schema): void
    {
        // názvy míst jako tagy party (unikát party_id + name; kolidující se prostě použije existující tag)
        $this->addSql('INSERT IGNORE INTO tag (party_id, name) SELECT DISTINCT party_id, name FROM place');
        $this->addSql('INSERT IGNORE INTO play_tag (play_id, tag_id) SELECT p.id, t.id FROM play p JOIN place pl ON pl.id = p.place_id JOIN tag t ON t.party_id = pl.party_id AND t.name = pl.name');

        $this->addSql('ALTER TABLE play DROP FOREIGN KEY FK_5E89DEBADA6A219');
        $this->addSql('DROP INDEX IDX_5E89DEBADA6A219 ON play');
        $this->addSql('ALTER TABLE play DROP place_id');
        $this->addSql('ALTER TABLE place DROP FOREIGN KEY FK_741D53CD213C1059');
        $this->addSql('DROP TABLE place');
    }

    public function down(Schema $schema): void
    {
        // data se nevrací: z míst se staly běžné tagy
        $this->addSql('CREATE TABLE place (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, party_id INT NOT NULL, UNIQUE INDEX UNIQ_741D53CD213C10595E237E06 (party_id, name), INDEX IDX_741D53CD213C1059 (party_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE place ADD CONSTRAINT FK_741D53CD213C1059 FOREIGN KEY (party_id) REFERENCES party (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE play ADD place_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE play ADD CONSTRAINT FK_5E89DEBADA6A219 FOREIGN KEY (place_id) REFERENCES place (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_5E89DEBADA6A219 ON play (place_id)');
    }
}
