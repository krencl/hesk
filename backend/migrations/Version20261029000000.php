<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Tagy zápisů: `tag` → `play_tag` (shodně s `game_tag`), spojovací `play_tag` → `play_play_tag` (shodně s `game_game_tag`). */
final class Version20261029000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename tag → play_tag and play_tag → play_play_tag';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE play_tag DROP FOREIGN KEY FK_DDA8474C25576DBD');
        $this->addSql('ALTER TABLE play_tag DROP FOREIGN KEY FK_DDA8474CBAD26311');
        $this->addSql('ALTER TABLE tag DROP FOREIGN KEY FK_389B783213C1059');
        $this->addSql('RENAME TABLE play_tag TO play_play_tag, tag TO play_tag');
        $this->addSql('ALTER TABLE play_play_tag RENAME INDEX IDX_DDA8474C25576DBD TO IDX_16BFEAA725576DBD, RENAME INDEX IDX_DDA8474CBAD26311 TO IDX_16BFEAA7BAD26311');
        $this->addSql('ALTER TABLE play_tag RENAME INDEX IDX_389B783213C1059 TO IDX_DDA8474C213C1059, RENAME INDEX UNIQ_389B783213C10595E237E06 TO UNIQ_DDA8474C213C10595E237E06');
        $this->addSql('ALTER TABLE play_play_tag ADD CONSTRAINT FK_16BFEAA725576DBD FOREIGN KEY (play_id) REFERENCES play (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE play_play_tag ADD CONSTRAINT FK_16BFEAA7BAD26311 FOREIGN KEY (tag_id) REFERENCES play_tag (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE play_tag ADD CONSTRAINT FK_DDA8474C213C1059 FOREIGN KEY (party_id) REFERENCES party (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE play_play_tag DROP FOREIGN KEY FK_16BFEAA725576DBD');
        $this->addSql('ALTER TABLE play_play_tag DROP FOREIGN KEY FK_16BFEAA7BAD26311');
        $this->addSql('ALTER TABLE play_tag DROP FOREIGN KEY FK_DDA8474C213C1059');
        $this->addSql('RENAME TABLE play_tag TO tag, play_play_tag TO play_tag');
        $this->addSql('ALTER TABLE play_tag RENAME INDEX IDX_16BFEAA725576DBD TO IDX_DDA8474C25576DBD, RENAME INDEX IDX_16BFEAA7BAD26311 TO IDX_DDA8474CBAD26311');
        $this->addSql('ALTER TABLE tag RENAME INDEX IDX_DDA8474C213C1059 TO IDX_389B783213C1059, RENAME INDEX UNIQ_DDA8474C213C10595E237E06 TO UNIQ_389B783213C10595E237E06');
        $this->addSql('ALTER TABLE play_tag ADD CONSTRAINT FK_DDA8474C25576DBD FOREIGN KEY (play_id) REFERENCES play (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE play_tag ADD CONSTRAINT FK_DDA8474CBAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tag ADD CONSTRAINT FK_389B783213C1059 FOREIGN KEY (party_id) REFERENCES party (id) ON DELETE CASCADE');
    }
}
