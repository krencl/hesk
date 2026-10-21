<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Hra může mít víc vlastních hráčů: `game.player_id` se mění na vazební tabulku `game_player`. */
final class Version20261025000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Game players: many-to-many game_player instead of game.player_id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE game_player (game_id INT NOT NULL, player_id INT NOT NULL, INDEX IDX_E52CD7ADE48FD905 (game_id), INDEX IDX_E52CD7AD99E6F5DF (player_id), PRIMARY KEY (game_id, player_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE game_player ADD CONSTRAINT FK_E52CD7ADE48FD905 FOREIGN KEY (game_id) REFERENCES game (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE game_player ADD CONSTRAINT FK_E52CD7AD99E6F5DF FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE CASCADE');
        $this->addSql('INSERT INTO game_player (game_id, player_id) SELECT id, player_id FROM game WHERE player_id IS NOT NULL');
        $this->addSql('ALTER TABLE game DROP FOREIGN KEY FK_232B318C99E6F5DF');
        $this->addSql('DROP INDEX IDX_232B318C99E6F5DF ON game');
        $this->addSql('ALTER TABLE game DROP player_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game ADD player_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE game ADD CONSTRAINT FK_232B318C99E6F5DF FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_232B318C99E6F5DF ON game (player_id)');
        // zpět se vejde jen jeden hráč hry: ten s nejnižším id
        $this->addSql('UPDATE game g SET g.player_id = (SELECT MIN(gp.player_id) FROM game_player gp WHERE gp.game_id = g.id)');
        $this->addSql('DROP TABLE game_player');
    }
}
