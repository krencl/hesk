<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Exception\DomainException;
use App\Domain\PartyShortName;
use App\Enum\PartyRole;
use App\Repository\PartyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PartyRepository::class)]
class Party
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, PartyMember> */
    #[ORM\OneToMany(targetEntity: PartyMember::class, mappedBy: 'party', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $members;

    /** @var Collection<int, Player> */
    #[ORM\OneToMany(targetEntity: Player::class, mappedBy: 'party', cascade: ['persist'])]
    private Collection $players;

    private function __construct(
        #[ORM\Column(length: 100)]
        private string $name,
        /** Zkratka do kulatého tlačítka v hlavičce (1–3 znaky). */
        #[ORM\Column(length: PartyShortName::MAX_LENGTH)]
        private string $shortName,
    ) {
        $this->createdAt = new \DateTimeImmutable();
        $this->members = new ArrayCollection();
        $this->players = new ArrayCollection();
    }

    /** Zakladatel se automaticky stává adminem. */
    public static function create(string $name, User $creator): self
    {
        $party = new self($name, PartyShortName::generate($name));
        $party->members->add(new PartyMember($party, $creator, PartyRole::Admin));
        $party->ensurePlayerFor($creator);

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

    public function getShortName(): string
    {
        return $this->shortName;
    }

    public function update(string $name, string $shortName): void
    {
        $this->name = trim($name);
        $this->shortName = trim($shortName);
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

    /** @return Collection<int, Player> */
    public function getPlayers(): Collection
    {
        return $this->players;
    }

    public function findPlayerFor(User $user): ?Player
    {
        foreach ($this->players as $player) {
            $linked = $player->getUser();
            if ($linked && ($linked === $user || (null !== $user->getId() && $linked->getId() === $user->getId()))) {
                return $player;
            }
        }

        return null;
    }

    /** Každý člen party má svého hráče (pod přezdívkou účtu). */
    public function ensurePlayerFor(User $user): Player
    {
        if ($player = $this->findPlayerFor($user)) {
            return $player;
        }
        $this->players->add($player = new Player($this, $this->uniquePlayerName($user->getNickname() ?? strstr($user->getEmail(), '@', true) ?: 'Hráč'), $user));

        return $player;
    }

    public function addGuest(string $name): Player
    {
        $name = trim($name);
        foreach ($this->players as $player) {
            if (0 === strcasecmp($player->getName(), $name)) {
                throw new DomainException('Hráč s tímto jménem už v partě existuje.');
            }
        }
        $this->players->add($player = new Player($this, $name));

        return $player;
    }

    /** Přejmenuje hráče navázaného na účet podle nové přezdívky, pokud je jméno v partě volné. */
    public function syncPlayerName(User $user): void
    {
        $player = $this->findPlayerFor($user);
        $nickname = $user->getNickname();
        if (!$player || !$nickname || $player->getName() === $nickname) {
            return;
        }
        foreach ($this->players as $other) {
            if ($other !== $player && 0 === strcasecmp($other->getName(), $nickname)) {
                return;
            }
        }
        $player->rename($nickname);
    }

    private function uniquePlayerName(string $base): string
    {
        $taken = array_map(static fn (Player $p): string => mb_strtolower($p->getName()), $this->players->toArray());
        $name = $base;
        for ($i = 2; \in_array(mb_strtolower($name), $taken, true); ++$i) {
            $name = $base.' '.$i;
        }

        return $name;
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
        $this->ensurePlayerFor($user);

        return $member;
    }

    public function removeMember(User $user): void
    {
        $member = $this->findMember($user) ?? throw new DomainException('Uživatel není členem party.');
        $this->assertStillHasAdminWithout($member);
        $this->members->removeElement($member);
        // Historie her zůstává: hráč jen přijde o vazbu na účet a stane se z něj host.
        $this->findPlayerFor($user)?->detachUser();
    }

    public function changeRole(User $user, PartyRole $role): void
    {
        $member = $this->findMember($user) ?? throw new DomainException('Uživatel není členem party.');
        if (PartyRole::Member === $role) {
            $this->assertStillHasAdminWithout($member);
        }
        $member->setRole($role);
    }

    /** Parta musí mít vždy alespoň jednoho admina. */
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
        throw new DomainException('Parta musí mít alespoň jednoho admina.');
    }
}
