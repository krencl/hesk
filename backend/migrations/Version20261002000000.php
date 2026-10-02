<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Počáteční schéma (MySQL 8). */
final class Version20261002000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE game_session (id INT AUTO_INCREMENT NOT NULL, started_at DATETIME NOT NULL, ended_at DATETIME NOT NULL, party_id INT NOT NULL, INDEX IDX_4586AAFB213C1059 (party_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE party_invitation (id INT AUTO_INCREMENT NOT NULL, token VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, party_id INT NOT NULL, invited_by_id INT NOT NULL, UNIQUE INDEX UNIQ_F9669CEE5F37A13B (token), INDEX IDX_F9669CEE213C1059 (party_id), INDEX IDX_F9669CEEA7B4A7E3 (invited_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE party_member (id INT AUTO_INCREMENT NOT NULL, role VARCHAR(255) NOT NULL, party_id INT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_36928121213C1059A76ED395 (party_id, user_id), INDEX IDX_36928121213C1059 (party_id), INDEX IDX_36928121A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE party (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, name VARCHAR(100) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tag (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, party_id INT NOT NULL, UNIQUE INDEX UNIQ_389B783213C10595E237E06 (party_id, name), INDEX IDX_389B783213C1059 (party_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE play_result (id INT AUTO_INCREMENT NOT NULL, score DOUBLE PRECISION DEFAULT NULL, placement INT NOT NULL, winner TINYINT NOT NULL, play_id INT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_D14CE15325576DBDA76ED395 (play_id, user_id), INDEX IDX_D14CE15325576DBD (play_id), INDEX IDX_D14CE153A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE game_addon (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(150) NOT NULL, game_id INT NOT NULL, INDEX IDX_E3B6E8F0E48FD905 (game_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE play (id INT AUTO_INCREMENT NOT NULL, notes LONGTEXT DEFAULT NULL, started_at DATETIME NOT NULL, ended_at DATETIME NOT NULL, session_id INT NOT NULL, place_id INT DEFAULT NULL, party_id INT NOT NULL, game_id INT NOT NULL, INDEX IDX_5E89DEBA213C1059D46F4E3 (party_id, started_at), INDEX IDX_5E89DEBA613FECDF (session_id), INDEX IDX_5E89DEBADA6A219 (place_id), INDEX IDX_5E89DEBA213C1059 (party_id), INDEX IDX_5E89DEBAE48FD905 (game_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE play_addon (play_id INT NOT NULL, game_addon_id INT NOT NULL, INDEX IDX_205A76C325576DBD (play_id), INDEX IDX_205A76C36720D95E (game_addon_id), PRIMARY KEY (play_id, game_addon_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE play_tag (play_id INT NOT NULL, tag_id INT NOT NULL, INDEX IDX_DDA8474C25576DBD (play_id), INDEX IDX_DDA8474CBAD26311 (tag_id), PRIMARY KEY (play_id, tag_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE place (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, party_id INT NOT NULL, UNIQUE INDEX UNIQ_741D53CD213C10595E237E06 (party_id, name), INDEX IDX_741D53CD213C1059 (party_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE app_user (id INT AUTO_INCREMENT NOT NULL, nickname VARCHAR(100) DEFAULT NULL, created_at DATETIME NOT NULL, google_id VARCHAR(64) NOT NULL, email VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_88BDF3E976F5C865 (google_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE game (id INT AUTO_INCREMENT NOT NULL, image_url VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, cheat_sheet LONGTEXT DEFAULT NULL, name VARCHAR(150) NOT NULL, score_type VARCHAR(255) NOT NULL, party_id INT NOT NULL, INDEX IDX_232B318C213C1059 (party_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE game_session ADD CONSTRAINT FK_4586AAFB213C1059 FOREIGN KEY (party_id) REFERENCES party (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE party_invitation ADD CONSTRAINT FK_F9669CEE213C1059 FOREIGN KEY (party_id) REFERENCES party (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE party_invitation ADD CONSTRAINT FK_F9669CEEA7B4A7E3 FOREIGN KEY (invited_by_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE party_member ADD CONSTRAINT FK_36928121213C1059 FOREIGN KEY (party_id) REFERENCES party (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE party_member ADD CONSTRAINT FK_36928121A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tag ADD CONSTRAINT FK_389B783213C1059 FOREIGN KEY (party_id) REFERENCES party (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE play_result ADD CONSTRAINT FK_D14CE15325576DBD FOREIGN KEY (play_id) REFERENCES play (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE play_result ADD CONSTRAINT FK_D14CE153A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE game_addon ADD CONSTRAINT FK_E3B6E8F0E48FD905 FOREIGN KEY (game_id) REFERENCES game (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE play ADD CONSTRAINT FK_5E89DEBA613FECDF FOREIGN KEY (session_id) REFERENCES game_session (id)');
        $this->addSql('ALTER TABLE play ADD CONSTRAINT FK_5E89DEBADA6A219 FOREIGN KEY (place_id) REFERENCES place (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE play ADD CONSTRAINT FK_5E89DEBA213C1059 FOREIGN KEY (party_id) REFERENCES party (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE play ADD CONSTRAINT FK_5E89DEBAE48FD905 FOREIGN KEY (game_id) REFERENCES game (id)');
        $this->addSql('ALTER TABLE play_addon ADD CONSTRAINT FK_205A76C325576DBD FOREIGN KEY (play_id) REFERENCES play (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE play_addon ADD CONSTRAINT FK_205A76C36720D95E FOREIGN KEY (game_addon_id) REFERENCES game_addon (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE play_tag ADD CONSTRAINT FK_DDA8474C25576DBD FOREIGN KEY (play_id) REFERENCES play (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE play_tag ADD CONSTRAINT FK_DDA8474CBAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE place ADD CONSTRAINT FK_741D53CD213C1059 FOREIGN KEY (party_id) REFERENCES party (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE game ADD CONSTRAINT FK_232B318C213C1059 FOREIGN KEY (party_id) REFERENCES party (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('SET FOREIGN_KEY_CHECKS = 0');
        $this->addSql('DROP TABLE play_tag');
        $this->addSql('DROP TABLE play_addon');
        $this->addSql('DROP TABLE play_result');
        $this->addSql('DROP TABLE play');
        $this->addSql('DROP TABLE place');
        $this->addSql('DROP TABLE tag');
        $this->addSql('DROP TABLE game_addon');
        $this->addSql('DROP TABLE game');
        $this->addSql('DROP TABLE game_session');
        $this->addSql('DROP TABLE party_invitation');
        $this->addSql('DROP TABLE party_member');
        $this->addSql('DROP TABLE party');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1');
    }
}
