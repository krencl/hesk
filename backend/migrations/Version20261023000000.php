<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Čisté schéma aplikace (MariaDB 11); nahrazuje dřívější průběžné migrace z doby, kdy ještě neexistovala produkční data. */
final class Version20261023000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE game_session (id INT AUTO_INCREMENT NOT NULL, number INT NOT NULL, started_at DATETIME NOT NULL, ended_at DATETIME NOT NULL, party_id INT NOT NULL, UNIQUE INDEX uniq_session_party_number (party_id, number), INDEX IDX_4586AAFB213C1059 (party_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE party_invitation (id INT AUTO_INCREMENT NOT NULL, token VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, party_id INT NOT NULL, invited_by_id INT NOT NULL, guest_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_F9669CEE5F37A13B (token), INDEX IDX_F9669CEE213C1059 (party_id), INDEX IDX_F9669CEEA7B4A7E3 (invited_by_id), INDEX IDX_F9669CEE9A4AA658 (guest_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE party_game (id INT AUTO_INCREMENT NOT NULL, party_id INT NOT NULL, game_id INT NOT NULL, UNIQUE INDEX uniq_party_game (party_id, game_id), INDEX IDX_3C88F05F213C1059 (party_id), INDEX IDX_3C88F05FE48FD905 (game_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE party_game_player (party_game_id INT NOT NULL, player_id INT NOT NULL, INDEX IDX_9A4EA1EBF67E462E (party_game_id), INDEX IDX_9A4EA1EB99E6F5DF (player_id), PRIMARY KEY (party_game_id, player_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE party_member (id INT AUTO_INCREMENT NOT NULL, role VARCHAR(255) NOT NULL, party_id INT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_36928121213C1059A76ED395 (party_id, user_id), INDEX IDX_36928121213C1059 (party_id), INDEX IDX_36928121A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE party (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, name VARCHAR(100) NOT NULL, short_name VARCHAR(3) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE play_result (id INT AUTO_INCREMENT NOT NULL, score DOUBLE PRECISION DEFAULT NULL, placement INT NOT NULL, winner TINYINT NOT NULL, is_starting TINYINT NOT NULL, play_id INT NOT NULL, player_id INT NOT NULL, character_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_D14CE15325576DBD99E6F5DF (play_id, player_id), INDEX IDX_D14CE15325576DBD (play_id), INDEX IDX_D14CE15399E6F5DF (player_id), INDEX IDX_D14CE1531136BE75 (character_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE play_tag (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, party_id INT NOT NULL, UNIQUE INDEX UNIQ_DDA8474C213C10595E237E06 (party_id, name), INDEX IDX_DDA8474C213C1059 (party_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE player (id INT AUTO_INCREMENT NOT NULL, visible TINYINT DEFAULT 1 NOT NULL, name VARCHAR(100) NOT NULL, party_id INT NOT NULL, user_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_98197A65213C10595E237E06 (party_id, name), INDEX IDX_98197A65213C1059 (party_id), INDEX IDX_98197A65A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE game_addon (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(150) NOT NULL, game_id INT NOT NULL, INDEX IDX_E3B6E8F0E48FD905 (game_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE play (id INT AUTO_INCREMENT NOT NULL, notes LONGTEXT DEFAULT NULL, started_at DATETIME NOT NULL, ended_at DATETIME NOT NULL, session_id INT NOT NULL, party_id INT NOT NULL, game_id INT NOT NULL, INDEX IDX_5E89DEBA213C1059D46F4E3 (party_id, started_at), INDEX IDX_5E89DEBA613FECDF (session_id), INDEX IDX_5E89DEBA213C1059 (party_id), INDEX IDX_5E89DEBAE48FD905 (game_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE play_addon (play_id INT NOT NULL, game_addon_id INT NOT NULL, INDEX IDX_205A76C325576DBD (play_id), INDEX IDX_205A76C36720D95E (game_addon_id), PRIMARY KEY (play_id, game_addon_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE play_play_tag (play_id INT NOT NULL, play_tag_id INT NOT NULL, INDEX IDX_16BFEAA725576DBD (play_id), INDEX IDX_16BFEAA778F44351 (play_tag_id), PRIMARY KEY (play_id, play_tag_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE game_tag (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, UNIQUE INDEX UNIQ_18D3A4465E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, nickname VARCHAR(100) DEFAULT NULL, created_at DATETIME NOT NULL, roles JSON DEFAULT \'[]\' NOT NULL, google_id VARCHAR(180) DEFAULT NULL, email VARCHAR(180) NOT NULL, UNIQUE INDEX UNIQ_8D93D64976F5C865 (google_id), UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE bgg_game (id INT AUTO_INCREMENT NOT NULL, image_url VARCHAR(255) DEFAULT NULL, fetched_at DATETIME NOT NULL, bgg_id INT NOT NULL, name VARCHAR(255) NOT NULL, published_year INT DEFAULT NULL, UNIQUE INDEX UNIQ_142083ACE01D7737 (bgg_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE game_character (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(150) NOT NULL, game_id INT NOT NULL, INDEX IDX_41DC7136E48FD905 (game_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE game (id INT AUTO_INCREMENT NOT NULL, image_url VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, cheat_sheet LONGTEXT DEFAULT NULL, bgg_id INT DEFAULT NULL, short_name VARCHAR(50) DEFAULT NULL, name VARCHAR(150) NOT NULL, score_type VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_232B318CE01D7737 (bgg_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE game_game_tag (game_id INT NOT NULL, game_tag_id INT NOT NULL, INDEX IDX_B3975B60E48FD905 (game_id), INDEX IDX_B3975B60C7E7CED9 (game_tag_id), PRIMARY KEY (game_id, game_tag_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE game_session ADD CONSTRAINT FK_4586AAFB213C1059 FOREIGN KEY (party_id) REFERENCES party (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE party_invitation ADD CONSTRAINT FK_F9669CEE213C1059 FOREIGN KEY (party_id) REFERENCES party (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE party_invitation ADD CONSTRAINT FK_F9669CEEA7B4A7E3 FOREIGN KEY (invited_by_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE party_invitation ADD CONSTRAINT FK_F9669CEE9A4AA658 FOREIGN KEY (guest_id) REFERENCES player (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE party_game ADD CONSTRAINT FK_3C88F05F213C1059 FOREIGN KEY (party_id) REFERENCES party (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE party_game ADD CONSTRAINT FK_3C88F05FE48FD905 FOREIGN KEY (game_id) REFERENCES game (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE party_game_player ADD CONSTRAINT FK_9A4EA1EBF67E462E FOREIGN KEY (party_game_id) REFERENCES party_game (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE party_game_player ADD CONSTRAINT FK_9A4EA1EB99E6F5DF FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE party_member ADD CONSTRAINT FK_36928121213C1059 FOREIGN KEY (party_id) REFERENCES party (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE party_member ADD CONSTRAINT FK_36928121A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE play_result ADD CONSTRAINT FK_D14CE15325576DBD FOREIGN KEY (play_id) REFERENCES play (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE play_result ADD CONSTRAINT FK_D14CE15399E6F5DF FOREIGN KEY (player_id) REFERENCES player (id)');
        $this->addSql('ALTER TABLE play_result ADD CONSTRAINT FK_D14CE1531136BE75 FOREIGN KEY (character_id) REFERENCES game_character (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE play_tag ADD CONSTRAINT FK_DDA8474C213C1059 FOREIGN KEY (party_id) REFERENCES party (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE player ADD CONSTRAINT FK_98197A65213C1059 FOREIGN KEY (party_id) REFERENCES party (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE player ADD CONSTRAINT FK_98197A65A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE game_addon ADD CONSTRAINT FK_E3B6E8F0E48FD905 FOREIGN KEY (game_id) REFERENCES game (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE play ADD CONSTRAINT FK_5E89DEBA613FECDF FOREIGN KEY (session_id) REFERENCES game_session (id)');
        $this->addSql('ALTER TABLE play ADD CONSTRAINT FK_5E89DEBA213C1059 FOREIGN KEY (party_id) REFERENCES party (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE play ADD CONSTRAINT FK_5E89DEBAE48FD905 FOREIGN KEY (game_id) REFERENCES game (id)');
        $this->addSql('ALTER TABLE play_addon ADD CONSTRAINT FK_205A76C325576DBD FOREIGN KEY (play_id) REFERENCES play (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE play_addon ADD CONSTRAINT FK_205A76C36720D95E FOREIGN KEY (game_addon_id) REFERENCES game_addon (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE play_play_tag ADD CONSTRAINT FK_16BFEAA725576DBD FOREIGN KEY (play_id) REFERENCES play (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE play_play_tag ADD CONSTRAINT FK_16BFEAA778F44351 FOREIGN KEY (play_tag_id) REFERENCES play_tag (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE game_character ADD CONSTRAINT FK_41DC7136E48FD905 FOREIGN KEY (game_id) REFERENCES game (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE game_game_tag ADD CONSTRAINT FK_B3975B60E48FD905 FOREIGN KEY (game_id) REFERENCES game (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE game_game_tag ADD CONSTRAINT FK_B3975B60C7E7CED9 FOREIGN KEY (game_tag_id) REFERENCES game_tag (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('SET FOREIGN_KEY_CHECKS = 0');
        $this->addSql('DROP TABLE game_session');
        $this->addSql('DROP TABLE party_invitation');
        $this->addSql('DROP TABLE party_game');
        $this->addSql('DROP TABLE party_game_player');
        $this->addSql('DROP TABLE party_member');
        $this->addSql('DROP TABLE party');
        $this->addSql('DROP TABLE play_result');
        $this->addSql('DROP TABLE play_tag');
        $this->addSql('DROP TABLE player');
        $this->addSql('DROP TABLE game_addon');
        $this->addSql('DROP TABLE play');
        $this->addSql('DROP TABLE play_addon');
        $this->addSql('DROP TABLE play_play_tag');
        $this->addSql('DROP TABLE game_tag');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE bgg_game');
        $this->addSql('DROP TABLE game_character');
        $this->addSql('DROP TABLE game');
        $this->addSql('DROP TABLE game_game_tag');
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1');
    }
}
