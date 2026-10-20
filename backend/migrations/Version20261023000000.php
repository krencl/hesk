<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Nová role „zapisovač“ (`writer`). Dosavadní členové mohli zapisovat všichni, proto se z nich zapisovači stanou
 * (jinak by o možnost zapisovat přišli); nově přidaní členové jsou „člen“ (zápisy jen čtou).
 */
final class Version20261023000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Party role writer; existing members keep writing';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE party_member SET role = 'writer' WHERE role = 'member'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE party_member SET role = 'member' WHERE role = 'writer'");
    }
}
