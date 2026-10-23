<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:legacy:reset', description: 'Dev: smaže celou databázi, znovu ji vytvoří migracemi a naimportuje data ze staré aplikace (role a obálky her zachová)')]
final class ResetLegacyCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
        #[Autowire('%kernel.project_dir%/var/legacy-reset-backup.json')]
        private readonly string $backupFile,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp(<<<'HELP'
                Postup: zkouška importu (ověří přístup ke staré DB dřív, než se cokoli smaže) → záloha rolí uživatelů a obálek her
                (`image_url`; stará DB je nemá) do var/legacy-reset-backup.json → smazání celé databáze → `doctrine:migrations:migrate`
                → `app:legacy:import` → obnova rolí a obálek (hry se hledají podle `bgg_id`, jinak podle názvu).
                Smaže i vše, co vzniklo jen v nové aplikaci. Jen v prostředí dev. Doporučení: spouštět s `--no-debug`.
                HELP)
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Nevyžadovat potvrzení');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ('dev' !== $this->environment) {
            $io->error(sprintf('Příkaz maže celou databázi, smí běžet jen v prostředí dev (aktuální: %s).', $this->environment));

            return Command::FAILURE;
        }
        if (!$input->getOption('yes') && (!$input->isInteractive() || !$io->confirm(sprintf('Smazat celou databázi „%s“ a naimportovat ji znovu ze staré aplikace?', $this->connection->getDatabase()), false))) {
            $io->warning('Zrušeno (pro neinteraktivní spuštění použij --yes).');

            return Command::FAILURE;
        }

        $io->section('Zkouška importu (ověření přístupu ke staré DB)');
        if (Command::SUCCESS !== $this->runCommand('app:legacy:import', ['--dry-run' => true], $output)) {
            $io->error('Zkouška importu selhala, nic se nesmazalo.');

            return Command::FAILURE;
        }

        $backup = $this->backup();
        if ([] !== $backup['roles'] || [] !== $backup['covers']) {
            file_put_contents($this->backupFile, json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } elseif (is_file($this->backupFile)) { // prázdná záloha (např. po neúspěšném běhu) nepřepíše předchozí, ta se použije při obnově
            $backup = json_decode((string) file_get_contents($this->backupFile), true, 512, JSON_THROW_ON_ERROR);
            $io->note('Databáze neobsahuje role ani obálky, použiji dřívější zálohu.');
        }
        $io->writeln(sprintf('Záloha: %d uživatelů s rolí, %d obálek → %s', count($backup['roles']), count($backup['covers']), $this->backupFile));

        $steps = [
            'Mazání databáze' => ['doctrine:schema:drop', ['--full-database' => true, '--force' => true]],
            'Migrace' => ['doctrine:migrations:migrate', []],
            'Import ze staré aplikace' => ['app:legacy:import', []],
        ];
        foreach ($steps as $title => [$name, $options]) {
            $io->section($title);
            if (Command::SUCCESS !== $this->runCommand($name, $options, $output)) {
                $io->error(sprintf('Krok „%s“ selhal. Záloha rolí a obálek zůstává v %s.', $title, $this->backupFile));

                return Command::FAILURE;
            }
        }

        $io->section('Obnova rolí a obálek');
        [$roles, $covers] = $this->restore($backup);
        $io->success(sprintf('Hotovo. Obnoveno rolí: %d, obálek: %d z %d.', $roles, $covers, count($backup['covers'])));

        return Command::SUCCESS;
    }

    /** @return array{roles: list<array<string, mixed>>, covers: list<array<string, mixed>>} */
    private function backup(): array
    {
        $schema = $this->connection->createSchemaManager();
        $tables = $schema->listTableNames();

        return [
            'roles' => in_array('user', $tables, true) ? $this->connection->fetchAllAssociative("SELECT email, roles FROM user WHERE roles <> '[]'") : [],
            'covers' => in_array('game', $tables, true) ? $this->connection->fetchAllAssociative('SELECT bgg_id, name, image_url FROM game WHERE image_url IS NOT NULL') : [],
        ];
    }

    /**
     * @param array{roles: list<array<string, mixed>>, covers: list<array<string, mixed>>} $backup
     *
     * @return array{int, int} počet obnovených rolí a obálek
     */
    private function restore(array $backup): array
    {
        $roles = 0;
        foreach ($backup['roles'] as $row) {
            $roles += (int) $this->connection->executeStatement('UPDATE user SET roles = ? WHERE email = ?', [$row['roles'], $row['email']]);
        }
        $covers = 0;
        foreach ($backup['covers'] as $row) {
            $byBgg = null !== $row['bgg_id'];
            $covers += (int) $this->connection->executeStatement(
                'UPDATE game SET image_url = ? WHERE image_url IS NULL AND '.($byBgg ? 'bgg_id = ?' : 'name = ?'),
                [$row['image_url'], $byBgg ? $row['bgg_id'] : $row['name']],
            );
        }

        return [$roles, $covers];
    }

    /** @param array<string, mixed> $options */
    private function runCommand(string $name, array $options, OutputInterface $output): int
    {
        // DDL (drop, migrace) v MariaDB potvrzuje transakce implicitně; čisté spojení pro každý krok předejde
        // chybě „There is no active transaction“ u transakce importu.
        $this->connection->close();
        $input = new ArrayInput($options + ['command' => $name]);
        $input->setInteractive(false);

        return $this->getApplication()?->find($name)->run($input, $output) ?? Command::FAILURE;
    }
}
