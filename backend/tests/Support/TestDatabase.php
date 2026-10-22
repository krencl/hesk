<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Psr\Container\ContainerInterface;

/**
 * Testovací SQLite databáze pro `LegacyImportTest` (jediné testy s databází): schéma se vytvoří jednou za třídu,
 * mezi testy se jen vymažou data (jedna transakce místo desítek DDL příkazů).
 */
final class TestDatabase
{
    public static function createSchema(): void
    {
        $kernel = new Kernel('test', (bool) $_SERVER['APP_DEBUG']);
        $kernel->boot();
        $em = $kernel->getContainer()->get('doctrine')->getManager();
        $tool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
        $em->getConnection()->executeStatement('DROP TABLE IF EXISTS legacy_map');
        $kernel->shutdown();
    }

    /** Smaže data ze všech tabulek (a vynuluje autoincrement), schéma nechá být. */
    public static function clear(ContainerInterface $container): void
    {
        $em = $container->get(EntityManagerInterface::class);
        $em->clear();
        $connection = $em->getConnection();

        // PRAGMA foreign_keys nejde měnit uvnitř transakce
        $connection->executeStatement('PRAGMA foreign_keys = OFF');
        $connection->executeStatement('DROP TABLE IF EXISTS legacy_map');
        // všechny tabulky ze schématu, tedy včetně spojovacích tabulek M:N, které nejsou entity
        $tables = $connection->createSchemaManager()->listTableNames();
        $connection->transactional(static function () use ($connection, $tables): void {
            foreach ($tables as $table) {
                if ('sqlite_sequence' !== $table) {
                    $connection->executeStatement('DELETE FROM '.$connection->quoteSingleIdentifier($table));
                }
            }
            $connection->executeStatement('DELETE FROM sqlite_sequence');
        });
        $connection->executeStatement('PRAGMA foreign_keys = ON');
    }
}
