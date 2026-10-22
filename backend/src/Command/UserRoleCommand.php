<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:role', description: 'Přidá (nebo s --remove odebere) roli uživateli, např. ROLE_GAMES_ADMIN (správce her)')]
final class UserRoleCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'E-mail uživatele')
            ->addArgument('role', InputArgument::OPTIONAL, 'Role', User::ROLE_GAMES_ADMIN)
            ->addOption('remove', null, InputOption::VALUE_NONE, 'Roli odebrat');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $input->getArgument('email')]);
        if (!$user instanceof User) {
            $io->error('Uživatel s tímto e-mailem neexistuje.');

            return Command::FAILURE;
        }
        $role = (string) $input->getArgument('role');
        $input->getOption('remove') ? $user->removeRole($role) : $user->addRole($role);
        $this->em->flush();
        $io->success(sprintf('Role uživatele %s: %s', $user->getNickname() ?? $user->getEmail(), implode(', ', $user->getRoles())));

        return Command::SUCCESS;
    }
}
