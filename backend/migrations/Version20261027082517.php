<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Nick člena jen v jedné partě. */
final class Version20261027082517 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Party member nickname';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE party_member ADD nickname VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE party_member DROP nickname');
    }
}
