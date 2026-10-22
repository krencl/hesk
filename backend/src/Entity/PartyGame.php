<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Exception\DomainException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/** Hra zařazená do party (ADR 0023): vazba na sdílenou hru z katalogu + údaje, které patří jen partě (vlastní hráči hry). */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_party_game', columns: ['party_id', 'game_id'])]
class PartyGame
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    /**
     * Vlastní hráči hry (u kooperativních her „protivníci“ hry, např. Boss); patří do této party.
     *
     * @var Collection<int, Player>
     */
    #[ORM\ManyToMany(targetEntity: Player::class)]
    #[ORM\JoinTable(name: 'party_game_player')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(onDelete: 'CASCADE')]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $players;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'gameLinks')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Party $party,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Game $game,
    ) {
        $this->players = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParty(): Party
    {
        return $this->party;
    }

    public function getGame(): Game
    {
        return $this->game;
    }

    /** @return Collection<int, Player> */
    public function getPlayers(): Collection
    {
        return $this->players;
    }

    /**
     * Hráči hry jako pojmenované položky (jako postavy): s `id` se hráč party přejmenuje, bez `id` se spáruje
     * podle jména s hráčem party, jinak vznikne nový host. Odebraný hráč zůstává v partě, jen přestane být hráčem hry.
     * Prázdná a opakovaná jména se zahodí.
     *
     * @param list<array{id: ?int, name: string}> $items
     */
    public function syncPlayers(array $items): void
    {
        $wanted = [];
        $seen = [];
        foreach ($items as $item) {
            $name = trim($item['name']);
            $key = mb_strtolower($name);
            if ('' === $name || isset($seen[$key])) {
                continue;
            }
            if (mb_strlen($name) > 100) {
                throw new DomainException('Jméno hráče hry může mít nejvýš 100 znaků.');
            }
            $seen[$key] = true;
            $wanted[] = ['id' => $item['id'] ?? null, 'name' => $name];
        }

        $resolved = [];
        foreach ($wanted as $item) {
            $player = $this->resolvePlayer($item['id'], $item['name']);
            if (!\in_array($player, $resolved, true)) {
                $resolved[] = $player;
            }
        }

        foreach ($this->players->toArray() as $current) {
            if (!\in_array($current, $resolved, true)) {
                $this->players->removeElement($current);
            }
        }
        foreach ($resolved as $player) {
            if (!$this->players->contains($player)) {
                $this->players->add($player);
            }
        }
    }

    private function resolvePlayer(?int $id, string $name): Player
    {
        if (null !== $id) {
            foreach ($this->party->getPlayers() as $player) {
                if ($player->getId() !== $id) {
                    continue;
                }
                if ($player->getName() !== $name) {
                    $this->party->renamePlayer($player, $name);
                }

                return $player;
            }

            throw new DomainException('Hráč nepatří do této party.');
        }
        foreach ($this->party->getPlayers() as $player) {
            if (0 === strcasecmp($player->getName(), $name)) {
                return $player;
            }
        }

        return $this->party->addGuest($name);
    }
}
