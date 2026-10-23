<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** `app_user` → `user`; `play_play_tag.tag_id` → `play_tag_id` (entita `PlayTag`). */
final class Version20261029000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename app_user to user, play_play_tag.tag_id to play_tag_id';
    }

    public function up(Schema $schema): void
    {
        // cizí klíče na app_user se při přejmenování tabulky přepíšou samy
        $this->addSql('RENAME TABLE app_user TO user');
        $this->addSql('ALTER TABLE user RENAME INDEX UNIQ_88BDF3E976F5C865 TO UNIQ_8D93D64976F5C865, RENAME INDEX UNIQ_88BDF3E9E7927C74 TO UNIQ_8D93D649E7927C74');
        $this->addSql('ALTER TABLE play_play_tag DROP FOREIGN KEY FK_16BFEAA7BAD26311');
        $this->addSql('ALTER TABLE play_play_tag CHANGE tag_id play_tag_id INT NOT NULL');
        $this->addSql('ALTER TABLE play_play_tag RENAME INDEX IDX_16BFEAA7BAD26311 TO IDX_16BFEAA778F44351');
        $this->addSql('ALTER TABLE play_play_tag ADD CONSTRAINT FK_16BFEAA778F44351 FOREIGN KEY (play_tag_id) REFERENCES play_tag (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE play_play_tag DROP FOREIGN KEY FK_16BFEAA778F44351');
        $this->addSql('ALTER TABLE play_play_tag CHANGE play_tag_id tag_id INT NOT NULL');
        $this->addSql('ALTER TABLE play_play_tag RENAME INDEX IDX_16BFEAA778F44351 TO IDX_16BFEAA7BAD26311');
        $this->addSql('ALTER TABLE play_play_tag ADD CONSTRAINT FK_16BFEAA7BAD26311 FOREIGN KEY (tag_id) REFERENCES play_tag (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user RENAME INDEX UNIQ_8D93D64976F5C865 TO UNIQ_88BDF3E976F5C865, RENAME INDEX UNIQ_8D93D649E7927C74 TO UNIQ_88BDF3E9E7927C74');
        $this->addSql('RENAME TABLE user TO app_user');
    }
}
