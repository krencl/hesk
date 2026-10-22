<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsMiddleware;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

/** Testovací SQLite je jednorázová, takže se nemusí čekat na fsync při každém commitu (na pomalém disku jde o minuty). */
#[AsMiddleware]
final class SqliteNoFsyncMiddleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return new class($driver) extends AbstractDriverMiddleware {
            public function connect(#[\SensitiveParameter] array $params): Connection
            {
                $connection = parent::connect($params);
                $connection->exec('PRAGMA synchronous = OFF');
                $connection->exec('PRAGMA journal_mode = MEMORY');

                return $connection;
            }
        };
    }
}
