<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Pozvánka může být určená konkrétnímu hostovi; po jejím přijetí se jeho zápisy převedou na účet pozvaného. */
final class Version20261024000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Invitation for a guest: accepting it links the guest to the new member';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE party_invitation ADD guest_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE party_invitation ADD CONSTRAINT FK_F9669CEE9A4AA658 FOREIGN KEY (guest_id) REFERENCES player (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_F9669CEE9A4AA658 ON party_invitation (guest_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE party_invitation DROP FOREIGN KEY FK_F9669CEE9A4AA658');
        $this->addSql('DROP INDEX IDX_F9669CEE9A4AA658 ON party_invitation');
        $this->addSql('ALTER TABLE party_invitation DROP guest_id');
    }
}
