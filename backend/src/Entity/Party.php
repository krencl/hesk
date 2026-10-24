<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Exception\DomainException;
use App\Domain\NameKey;
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

    /** @var Collection<int, PartyGame> hry zařazené do party (ADR 0023) */
    #[ORM\OneToMany(targetEntity: PartyGame::class, mappedBy: 'party', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $gameLinks;

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
        $this->gameLinks = new ArrayCollection();
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
            if (self::sameUser($member->getUser(), $user)) {
                return $member;
            }
        }

        return null;
    }

    public function findMemberByUserId(int $userId): ?PartyMember
    {
        foreach ($this->members as $member) {
            if ($member->getUser()->getId() === $userId) {
                return $member;
            }
        }

        return null;
    }

    /** Stejný účet: tatáž instance, nebo (u načtených z DB) stejné id. */
    private static function sameUser(User $a, User $b): bool
    {
        return $a === $b || (null !== $b->getId() && $a->getId() === $b->getId());
    }

    /** @return Collection<int, Player> */
    public function getPlayers(): Collection
    {
        return $this->players;
    }

    /** @return Collection<int, PartyGame> */
    public function getGameLinks(): Collection
    {
        return $this->gameLinks;
    }

    public function findGameLink(Game $game): ?PartyGame
    {
        foreach ($this->gameLinks as $link) {
            if ($link->getGame() === $game) {
                return $link;
            }
        }

        return null;
    }

    public function hasGame(Game $game): bool
    {
        return null !== $this->findGameLink($game);
    }

    /** Zařadí hru do party; je-li už zařazená, vrátí stávající vazbu. */
    public function addGame(Game $game): PartyGame
    {
        $link = $this->findGameLink($game);
        if (null === $link) {
            $this->gameLinks->add($link = new PartyGame($this, $game));
        }

        return $link;
    }

    public function removeGame(Game $game): void
    {
        $link = $this->findGameLink($game);
        if (null !== $link) {
            $this->gameLinks->removeElement($link);
        }
    }

    public function findPlayerFor(User $user): ?Player
    {
        foreach ($this->players as $player) {
            $linked = $player->getUser();
            if ($linked && self::sameUser($linked, $user)) {
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
        if ($this->isPlayerNameTaken($name)) {
            throw new DomainException('Hráč s tímto jménem už v partě existuje.');
        }
        $this->players->add($player = new Player($this, $name));

        return $player;
    }

    /** Je jméno mezi hráči party už obsazené? Porovnává se podle {@see NameKey} (bez ohledu na velikost písmen a diakritiku, jako DB); `$except` je hráč, který se přejmenovává. */
    public function isPlayerNameTaken(string $name, ?Player $except = null): bool
    {
        $key = NameKey::of($name);
        foreach ($this->players as $player) {
            if ($player !== $except && NameKey::of($player->getName()) === $key) {
                return true;
            }
        }

        return false;
    }

    /** Přejmenuje hosta; jméno musí být v partě volné (velikost písmen ani diakritika se nerozlišují) a člena s účtem přejmenovat nejde. */
    public function renamePlayer(Player $player, string $name): void
    {
        $name = trim($name);
        if (null !== $player->getUser()) {
            throw new DomainException('Člena s účtem tu upravit nejde, jeho jméno se mění přezdívkou.');
        }
        if ('' === $name) {
            throw new DomainException('Jméno nesmí být prázdné.');
        }
        if ($this->isPlayerNameTaken($name, $player)) {
            throw new DomainException('Hráč s tímto jménem už v partě existuje.');
        }
        $player->rename($name);
    }

    /**
     * Úprava hosta: jméno a/nebo viditelnost ve výběru hráčů (zadané se změní, `null` zůstane). Člena s účtem upravit nejde.
     */
    public function updateGuest(Player $player, ?string $name, ?bool $visible): void
    {
        if (null !== $player->getUser()) {
            throw new DomainException('Člena s účtem tu upravit nejde, jeho jméno se mění přezdívkou.');
        }
        if (null === $name && null === $visible) {
            throw new DomainException('Není co měnit.');
        }
        if (null !== $name) {
            $this->renamePlayer($player, $name);
        }
        if (null !== $visible) {
            $player->setVisible($visible);
        }
    }

    /** Přejmenuje hráče navázaného na účet podle nové přezdívky, pokud je jméno v partě volné. */
    public function syncPlayerName(User $user): void
    {
        $player = $this->findPlayerFor($user);
        $nickname = $user->getNickname();
        if (!$player || !$nickname || $player->getName() === $nickname) {
            return;
        }
        if ($this->isPlayerNameTaken($nickname, $player)) {
            return;
        }
        $player->rename($nickname);
    }

    /** Jméno hráče, které je v partě volné: `$base`, jinak `$base 2`, `$base 3`… */
    public function uniquePlayerName(string $base): string
    {
        $name = $base;
        for ($i = 2; $this->isPlayerNameTaken($name); ++$i) {
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

    public function canWrite(User $user): bool
    {
        return $this->findMember($user)?->canWrite() ?? false;
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
        if (PartyRole::Admin !== $role) {
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
        throw new DomainException('Parta musí mít alespoň jednoho správce.');
    }
}
