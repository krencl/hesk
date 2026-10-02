<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Exception\DomainException;
use App\Enum\PartyRole;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Party
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, PartyMember> */
    #[ORM\OneToMany(targetEntity: PartyMember::class, mappedBy: 'party', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $members;

    private function __construct(
        #[ORM\Column(length: 100)]
        private string $name,
    ) {
        $this->createdAt = new \DateTimeImmutable();
        $this->members = new ArrayCollection();
    }

    /** Zakladatel se automaticky stává adminem. */
    public static function create(string $name, User $creator): self
    {
        $party = new self($name);
        $party->members->add(new PartyMember($party, $creator, PartyRole::Admin));

        return $party;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function rename(string $name): void
    {
        $this->name = $name;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, PartyMember> */
    public function getMembers(): Collection
    {
        return $this->members;
    }

    public function findMember(User $user): ?PartyMember
    {
        foreach ($this->members as $member) {
            if ($member->getUser() === $user || (null !== $user->getId() && $member->getUser()->getId() === $user->getId())) {
                return $member;
            }
        }

        return null;
    }

    public function isMember(User $user): bool
    {
        return null !== $this->findMember($user);
    }

    public function isAdmin(User $user): bool
    {
        return $this->findMember($user)?->isAdmin() ?? false;
    }

    public function addMember(User $user, PartyRole $role = PartyRole::Member): PartyMember
    {
        if ($member = $this->findMember($user)) {
            return $member;
        }
        $this->members->add($member = new PartyMember($this, $user, $role));

        return $member;
    }

    public function removeMember(User $user): void
    {
        $member = $this->findMember($user) ?? throw new DomainException('Uživatel není členem skupiny.');
        $this->assertStillHasAdminWithout($member);
        $this->members->removeElement($member);
    }

    public function changeRole(User $user, PartyRole $role): void
    {
        $member = $this->findMember($user) ?? throw new DomainException('Uživatel není členem skupiny.');
        if (PartyRole::Member === $role) {
            $this->assertStillHasAdminWithout($member);
        }
        $member->setRole($role);
    }

    /** Skupina musí mít vždy alespoň jednoho admina. */
    private function assertStillHasAdminWithout(PartyMember $leaving): void
    {
        if (!$leaving->isAdmin()) {
            return;
        }
        foreach ($this->members as $member) {
            if ($member !== $leaving && $member->isAdmin()) {
                return;
            }
        }
        throw new DomainException('Skupina musí mít alespoň jednoho admina.');
    }
}
