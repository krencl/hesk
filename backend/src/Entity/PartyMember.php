<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PartyRole;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'party_member')]
#[ORM\UniqueConstraint(columns: ['party_id', 'user_id'])]
class PartyMember
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'members')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Party $party,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,
        #[ORM\Column(enumType: PartyRole::class)]
        private PartyRole $role,
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

    public function getUser(): User
    {
        return $this->user;
    }

    public function getRole(): PartyRole
    {
        return $this->role;
    }

    public function setRole(PartyRole $role): void
    {
        $this->role = $role;
    }

    public function isAdmin(): bool
    {
        return PartyRole::Admin === $this->role;
    }

    /** Zapisovat zápisy smí admin a zapisovač. */
    public function canWrite(): bool
    {
        return PartyRole::Admin === $this->role || PartyRole::Writer === $this->role;
    }
}
