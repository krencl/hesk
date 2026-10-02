<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Stará aplikace (hesk-tmp) používala stejné názvy tabulek (party, game, tag, user…).
 * Pokud je v databázi najdeme, přejmenujeme je na legacy_* – data zůstanou nedotčená
 * a převezme je Version20261003000200. Na prázdné databázi se nedělá nic.
 */
final class Version20261003000000 extends AbstractMigration
{
    private const LEGACY_TABLES = [
        'game', 'game_character', 'game_version', 'locale', 'party', 'party_player', 'party_tag', 'party_user',
        'player', 'round', 'round_player', 'tag', 'tag_game', 'tag_round', 'translation_game', 'user', 'user_game', 'user_player',
    ];

    public function getDescription(): string
    {
        return 'Rename legacy (hesk-tmp) tables to legacy_*';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(!$this->hasLegacySchema($schema), 'No legacy schema found.');
        $pairs = array_map(static fn (string $t): string => "`$t` TO `legacy_$t`", self::LEGACY_TABLES);
        $this->addSql('RENAME TABLE '.implode(', ', $pairs));
    }

    public function down(Schema $schema): void
    {
        $pairs = array_map(static fn (string $t): string => "`legacy_$t` TO `$t`", self::LEGACY_TABLES);
        $this->addSql('RENAME TABLE '.implode(', ', $pairs));
    }

    private function hasLegacySchema(Schema $schema): bool
    {
        // Rozpoznávací znak staré aplikace: tabulka round_player (nová aplikace ji nemá).
        return $schema->hasTable('round_player') && !$schema->hasTable('play_result');
    }
}
