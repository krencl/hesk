<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sdílený katalog her (ADR 0023): `game` ztrácí `party_id` a bgg_id je unikátní, do party se hra zařazuje přes `party_game`
 * (+ vlastní hráči hry v `party_game_player`, dřív `game_player`); uživatel dostává `roles`.
 * Stávající hry se převedou 1:1 (každá zůstane ve své partě); sloučení stejných her řeší až nový import ze staré aplikace.
 */
final class Version20261027000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Shared game catalog: party_game, party_game_player, game without party_id, unique bgg_id, app_user.roles';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE party_game (id INT AUTO_INCREMENT NOT NULL, party_id INT NOT NULL, game_id INT NOT NULL, UNIQUE INDEX uniq_party_game (party_id, game_id), INDEX IDX_3C88F05F213C1059 (party_id), INDEX IDX_3C88F05FE48FD905 (game_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE party_game_player (party_game_id INT NOT NULL, player_id INT NOT NULL, INDEX IDX_9A4EA1EBF67E462E (party_game_id), INDEX IDX_9A4EA1EB99E6F5DF (player_id), PRIMARY KEY (party_game_id, player_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE party_game ADD CONSTRAINT FK_3C88F05F213C1059 FOREIGN KEY (party_id) REFERENCES party (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE party_game ADD CONSTRAINT FK_3C88F05FE48FD905 FOREIGN KEY (game_id) REFERENCES game (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE party_game_player ADD CONSTRAINT FK_9A4EA1EBF67E462E FOREIGN KEY (party_game_id) REFERENCES party_game (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE party_game_player ADD CONSTRAINT FK_9A4EA1EB99E6F5DF FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE CASCADE');

        $this->addSql('INSERT INTO party_game (party_id, game_id) SELECT party_id, id FROM game');
        $this->addSql('INSERT INTO party_game_player (party_game_id, player_id) SELECT pg.id, gp.player_id FROM game_player gp JOIN party_game pg ON pg.game_id = gp.game_id');

        $this->addSql('ALTER TABLE game_player DROP FOREIGN KEY `FK_E52CD7AD99E6F5DF`');
        $this->addSql('ALTER TABLE game_player DROP FOREIGN KEY `FK_E52CD7ADE48FD905`');
        $this->addSql('DROP TABLE game_player');
        $this->addSql('ALTER TABLE game DROP FOREIGN KEY `FK_232B318C213C1059`');
        $this->addSql('DROP INDEX IDX_232B318C213C1059 ON game');
        $this->addSql('ALTER TABLE game DROP party_id');
        // stejné bgg_id u dvou her by unikát neprošlo; ponecháme tu s nejnižším id
        $this->addSql('UPDATE game g JOIN game o ON o.bgg_id = g.bgg_id AND o.id < g.id SET g.bgg_id = NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_232B318CE01D7737 ON game (bgg_id)');

        $this->addSql("ALTER TABLE app_user ADD roles JSON DEFAULT '[]' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Sdílený katalog her nejde vrátit zpět na hry patřící jedné partě.');
    }
}
