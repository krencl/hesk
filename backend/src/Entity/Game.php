<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Exception\DomainException;
use App\Enum\ScoreType;
use App\Repository\GameRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GameRepository::class)]
class Game
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** HTML z editoru; při výstupu je nutné ho sanitizovat na straně klienta. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $cheatSheet = null;

    /** Zkrácený název (statistiky, kde se celý název špatně vejde). */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $shortName = null;

    /**
     * Vlastní hráči hry (u kooperativních her „protivníci“ hry, např. Boss); patří do stejné party.
     *
     * @var Collection<int, Player>
     */
    #[ORM\ManyToMany(targetEntity: Player::class)]
    #[ORM\JoinTable(name: 'game_player')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(onDelete: 'CASCADE')]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $players;

    /** @var Collection<int, GameAddon> */
    #[ORM\OneToMany(targetEntity: GameAddon::class, mappedBy: 'game', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $addons;

    /** @var Collection<int, GameCharacter> */
    #[ORM\OneToMany(targetEntity: GameCharacter::class, mappedBy: 'game', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $characters;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Party $party,
        #[ORM\Column(length: 150)]
        private string $name,
        #[ORM\Column(enumType: ScoreType::class)]
        private ScoreType $scoreType,
    ) {
        $this->addons = new ArrayCollection();
        $this->characters = new ArrayCollection();
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

    public function getName(): string
    {
        return $this->name;
    }

    public function getScoreType(): ScoreType
    {
        return $this->scoreType;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getCheatSheet(): ?string
    {
        return $this->cheatSheet;
    }

    /** @return Collection<int, GameAddon> */
    public function getAddons(): Collection
    {
        return $this->addons;
    }

    public function getShortName(): ?string
    {
        return $this->shortName;
    }

    /** @return Collection<int, Player> */
    public function getPlayers(): Collection
    {
        return $this->players;
    }

    /** @return Collection<int, GameCharacter> */
    public function getCharacters(): Collection
    {
        return $this->characters;
    }

    /**
     * @param list<array{id: ?int, name: string}> $addons     rozšíření (s `id` se přejmenují, bez něj se spárují podle názvu nebo vytvoří)
     * @param list<array{id: ?int, name: string}> $characters postavy, stejná pravidla
     * @param list<array{id: ?int, name: string}> $players    hráči hry: `id` je id hráče party (přejmenuje se), bez `id` se spáruje podle jména s hráčem party nebo vznikne nový host
     */
    public function update(
        string $name,
        ?string $shortName,
        ScoreType $scoreType,
        ?string $imageUrl,
        ?string $description,
        ?string $cheatSheet,
        array $addons,
        array $characters = [],
        array $players = [],
    ): void {
        $this->name = $name;
        $this->shortName = null !== $shortName && '' !== trim($shortName) ? trim($shortName) : null;
        $this->scoreType = $scoreType;
        $this->imageUrl = $imageUrl ?: null;
        $this->description = $description ?: null;
        $this->cheatSheet = $cheatSheet ?: null;
        $this->sync($this->addons, $addons, fn (string $n): GameAddon => new GameAddon($this, $n));
        $this->sync($this->characters, $characters, fn (string $n): GameCharacter => new GameCharacter($this, $n));
        $this->syncPlayers($players);
    }

    /**
     * Hráči hry jako pojmenované položky (jako postavy): s `id` se hráč party přejmenuje, bez `id` se spáruje
     * podle jména s hráčem party, jinak vznikne nový host. Odebraný hráč zůstává v partě, jen přestane být hráčem hry.
     * Prázdná a opakovaná jména se zahodí.
     *
     * @param list<array{id: ?int, name: string}> $items
     */
    private function syncPlayers(array $items): void
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

    /**
     * Synchronizuje pojmenované položky: existující podle `id` přejmenuje, chybějící odebere, nové přidá.
     * Položky bez `id` se spáruje s existující stejného názvu (aby zůstaly vazby z odehraných her).
     * Prázdné a opakované názvy se zahodí.
     *
     * @template T of GameAddon|GameCharacter
     *
     * @param Collection<int, T>                  $collection
     * @param list<array{id: ?int, name: string}> $items
     * @param callable(string): T                 $create
     */
    private function sync(Collection $collection, array $items, callable $create): void
    {
        $seen = [];
        $wanted = [];
        foreach ($items as $item) {
            $name = trim($item['name']);
            $key = mb_strtolower($name);
            if ('' === $name || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $wanted[] = ['id' => $item['id'] ?? null, 'name' => $name];
        }

        $existing = $collection->toArray();
        $kept = [];
        $unmatched = [];
        foreach ($wanted as $i => $item) {
            $match = null;
            foreach ($existing as $e) {
                if (null !== $item['id'] && $e->getId() === $item['id']) {
                    $match = $e;
                    break;
                }
            }
            if ($match) {
                $kept[$i] = $match;
            } else {
                $unmatched[] = $i;
            }
        }
        foreach ($unmatched as $i) {
            foreach ($existing as $e) {
                if (!\in_array($e, $kept, true) && mb_strtolower($e->getName()) === mb_strtolower($wanted[$i]['name'])) {
                    $kept[$i] = $e;
                    break;
                }
            }
        }

        foreach ($existing as $e) {
            if (!\in_array($e, $kept, true)) {
                $collection->removeElement($e);
            }
        }
        foreach ($wanted as $i => $item) {
            if (isset($kept[$i])) {
                if ($kept[$i]->getName() !== $item['name']) {
                    $kept[$i]->rename($item['name']);
                }
            } else {
                $collection->add($create($item['name']));
            }
        }
    }
}
