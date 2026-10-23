<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Index pro zápisy hry v partě (seznam zápisů filtrovaný hrou, poslední zápis hry). */
final class Version20261029000200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add index play (party_id, game_id, started_at)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX IDX_5E89DEBA213C1059E48FD905D46F4E3 ON play (party_id, game_id, started_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_5E89DEBA213C1059E48FD905D46F4E3 ON play');
    }
}
