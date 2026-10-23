<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\ResetLegacyCommand;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ResetLegacyCommandTest extends TestCase
{
    public function testRefusesOutsideDevEnvironment(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::never())->method('executeStatement');
        $tester = new CommandTester(new ResetLegacyCommand($connection, 'prod', '/nonexistent/backup.json'));

        self::assertSame(1, $tester->execute(['--yes' => true]));
        self::assertStringContainsString('jen v prostředí dev', $tester->getDisplay());
    }

    public function testNonInteractiveRunWithoutYesDoesNothing(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabase')->willReturn('hesk');
        $connection->expects(self::never())->method('executeStatement');
        $tester = new CommandTester(new ResetLegacyCommand($connection, 'dev', '/nonexistent/backup.json'));

        self::assertSame(1, $tester->execute([], ['interactive' => false]));
        self::assertStringContainsString('--yes', $tester->getDisplay());
    }
}
