<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Hráč v rámci party. Může být navázaný na uživatelský účet, nebo jde o „hosta“
 * bez účtu (kamarád, kterého zapisujeme do her, ale který se do aplikace nepřihlašuje).
 */
#[ORM\Entity]
#[ORM\Table(name: 'player')]
#[ORM\UniqueConstraint(columns: ['party_id', 'name'])]
class Player
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'players')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Party $party,
        #[ORM\Column(length: 100)]
        private string $name,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        private ?User $user = null,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParty(): Party
    {
        return $this->party;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function rename(string $name): void
    {
        $this->name = $name;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function detachUser(): void
    {
        $this->user = null;
    }
}
