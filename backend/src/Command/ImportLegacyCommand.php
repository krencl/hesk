<?php

declare(strict_types=1);

namespace App\Command;

use App\Legacy\ImportReport;
use App\Legacy\LegacyImporter;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:legacy:import', description: 'Synchronizuje data ze staré aplikace (databáze hesk-old, jen čtení) do aktuální databáze')]
final class ImportLegacyCommand extends Command
{
    private const LABELS = [
        'user' => 'Uživatelé', 'party' => 'Party', 'member' => 'Členové part', 'player' => 'Hráči', 'game' => 'Hry',
        'addon' => 'Rozšíření', 'character' => 'Postavy', 'tag' => 'Tagy', 'session' => 'Seance', 'play' => 'Zápisy', 'result' => 'Výsledky',
    ];

    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        #[Autowire('%kernel.debug%')]
        private readonly bool $debug,
        #[Autowire(env: 'LEGACY_DB_HOSTNAME')]
        private readonly string $legacyHost,
        #[Autowire(env: 'LEGACY_DB_PORT')]
        private readonly string $legacyPort,
        #[Autowire(env: 'LEGACY_DB_DATABASE')]
        private readonly string $legacyDatabase,
        #[Autowire(env: 'LEGACY_DB_USERNAME')]
        private readonly string $legacyUser,
        #[Autowire(env: 'LEGACY_DB_PASSWORD')]
        private readonly string $legacyPassword,
        #[Autowire(env: 'LEGACY_COVERS_DIR')]
        private readonly string $legacyCoversDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp(<<<'HELP'
                Čte starou databázi (LEGACY_DB_* v .env.dev.local, otevřená jen pro čtení) a synchronizuje ji do aktuální.
                Lze ho spouštět opakovaně: nové a změněné se přidá/aktualizuje, smazané zápisy se smažou, co vzniklo jen
                v nové aplikaci, se nemění. Doporučení: nejdřív `--dry-run`, spouštět s `--no-debug` (rychlejší a bez profileru).
                HELP)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Jen ukáže, co by se změnilo; nic nezapíše')
            ->addOption('covers-source', null, InputOption::VALUE_REQUIRED, 'Adresář s obálkami staré aplikace (výchozí: LEGACY_COVERS_DIR)')
            ->addOption('covers-target', null, InputOption::VALUE_REQUIRED, 'Adresář obálek nové aplikace', $this->projectDir.'/../frontend/public/covers')
            ->addOption('no-covers', null, InputOption::VALUE_NONE, 'Neřešit obálky her');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ('' === $this->legacyHost || '' === $this->legacyDatabase || '' === $this->legacyUser) {
            $io->error('Chybí přístupy ke staré databázi: nastav LEGACY_DB_HOSTNAME, LEGACY_DB_PORT, LEGACY_DB_DATABASE, LEGACY_DB_USERNAME a LEGACY_DB_PASSWORD (např. v backend/.env.dev.local).');

            return Command::FAILURE;
        }
        if ($this->debug) {
            $io->warning('Spuštěno v debug režimu: profiler si pamatuje všechny dotazy a import může dojít paměť. Použij `--no-debug`.');
            ini_set('memory_limit', '1G');
        }
        try {
            $legacy = $this->connectLegacy();
        } catch (\Throwable $e) {
            $io->error('Nepodařilo se připojit ke staré databázi: '.$e->getMessage());

            return Command::FAILURE;
        }
        if ($legacy->getDatabase() === $this->connection->getDatabase() && ($legacy->getParams()['host'] ?? null) === ($this->connection->getParams()['host'] ?? null)) {
            $io->error('Stará a nová databáze jsou stejné; import by přepsal vlastní data.');

            return Command::FAILURE;
        }

        $noCovers = (bool) $input->getOption('no-covers');
        $coversSource = $input->getOption('covers-source') ?: ('' !== $this->legacyCoversDir ? $this->legacyCoversDir : null);
        $importer = new LegacyImporter(
            $legacy,
            $this->connection,
            $noCovers ? null : $coversSource,
            $noCovers ? null : (string) $input->getOption('covers-target'),
        );
        $dryRun = (bool) $input->getOption('dry-run');
        $io->title($dryRun ? 'Import ze staré aplikace (zkouška, nic se nezapíše)' : 'Import ze staré aplikace');

        $report = $importer->import($dryRun);
        $this->render($io, $report);

        return Command::SUCCESS;
    }

    private function render(SymfonyStyle $io, ImportReport $report): void
    {
        $rows = [];
        foreach (self::LABELS as $entity => $label) {
            if (isset($report->entities[$entity])) {
                $c = $report->entities[$entity];
                $rows[] = [$label, $c['created'], $c['updated'], $c['unchanged'], $c['deleted']];
            }
        }
        $io->table(['Entita', 'Nové', 'Změněné', 'Beze změny', 'Smazané'], $rows);
        if ($io->isVerbose() && $report->changes) {
            $io->section('Změněné hodnoty');
            $io->listing(\array_slice($report->changes, 0, 200));
            if (\count($report->changes) > 200) {
                $io->writeln(\sprintf('… a dalších %d', \count($report->changes) - 200));
            }
        }
        if ($report->coversCopied > 0) {
            $io->writeln(\sprintf('Zkopírováno obálek: %d', $report->coversCopied));
        }
        if ($report->isNoop()) {
            $io->success('Nic nového – nová databáze odpovídá staré.');
        } elseif ($report->dryRun) {
            $io->note('Zkouška: nic se nezapsalo. Spusť znovu bez --dry-run.');
        } else {
            $io->success('Import hotový.');
        }
    }

    /** Připojení ke staré databázi jen pro čtení (přístupy z proměnných prostředí LEGACY_DB_*). */
    private function connectLegacy(): Connection
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_mysql',
            'host' => $this->legacyHost,
            'port' => '' !== $this->legacyPort ? (int) $this->legacyPort : 3306,
            'dbname' => $this->legacyDatabase,
            'user' => $this->legacyUser,
            'password' => $this->legacyPassword,
            'charset' => 'utf8mb4',
        ]);
        $connection->executeStatement('SET SESSION TRANSACTION READ ONLY');

        return $connection;
    }
}
