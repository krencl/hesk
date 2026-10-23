<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Tagy her ve sdíleném katalogu (`game_tag`, `game_game_tag`); odlišné od tagů zápisů (`tag`). */
final class Version20261028000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Game tags: game_tag, game_game_tag';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE game_game_tag (game_id INT NOT NULL, game_tag_id INT NOT NULL, INDEX IDX_B3975B60E48FD905 (game_id), INDEX IDX_B3975B60C7E7CED9 (game_tag_id), PRIMARY KEY (game_id, game_tag_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE game_tag (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, UNIQUE INDEX UNIQ_18D3A4465E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE game_game_tag ADD CONSTRAINT FK_B3975B60E48FD905 FOREIGN KEY (game_id) REFERENCES game (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE game_game_tag ADD CONSTRAINT FK_B3975B60C7E7CED9 FOREIGN KEY (game_tag_id) REFERENCES game_tag (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game_game_tag DROP FOREIGN KEY FK_B3975B60E48FD905');
        $this->addSql('ALTER TABLE game_game_tag DROP FOREIGN KEY FK_B3975B60C7E7CED9');
        $this->addSql('DROP TABLE game_game_tag');
        $this->addSql('DROP TABLE game_tag');
    }
}
