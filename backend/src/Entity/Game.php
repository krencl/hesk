<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\NameKey;
use App\Domain\NamedItems;
use App\Enum\ScoreType;
use App\Repository\GameRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Hra ve sdíleném katalogu (ADR 0023): údaje jsou společné všem partám, do party se hra zařazuje přes `PartyGame`. */
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

    /** Id hry na BoardGameGeek (ADR 0021); null = vlastní hra. Hra s `bgg_id` je v katalogu nejvýš jednou. */
    #[ORM\Column(nullable: true, unique: true)]
    private ?int $bggId = null;

    /** Zkrácený název (statistiky, kde se celý název špatně vejde). */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $shortName = null;

    /** @var Collection<int, GameAddon> */
    #[ORM\OneToMany(targetEntity: GameAddon::class, mappedBy: 'game', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $addons;

    /** @var Collection<int, GameCharacter> */
    #[ORM\OneToMany(targetEntity: GameCharacter::class, mappedBy: 'game', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $characters;

    /** @var Collection<int, GameTag> */
    #[ORM\ManyToMany(targetEntity: GameTag::class)]
    #[ORM\JoinTable(name: 'game_game_tag')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(onDelete: 'CASCADE')]
    private Collection $tags;

    public function __construct(
        #[ORM\Column(length: 150)]
        private string $name,
        #[ORM\Column(enumType: ScoreType::class)]
        private ScoreType $scoreType,
    ) {
        $this->addons = new ArrayCollection();
        $this->characters = new ArrayCollection();
        $this->tags = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getBggId(): ?int
    {
        return $this->bggId;
    }

    public function getShortName(): ?string
    {
        return $this->shortName;
    }

    /** @return Collection<int, GameCharacter> */
    public function getCharacters(): Collection
    {
        return $this->characters;
    }

    /** @return Collection<int, GameTag> */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    /** @param list<GameTag> $tags */
    public function setTags(array $tags): void
    {
        $this->tags = new ArrayCollection($tags);
    }

    /**
     * @param list<array{id: ?int, name: string}> $addons     rozšíření (s `id` se přejmenují, bez něj se spárují podle názvu nebo vytvoří)
     * @param list<array{id: ?int, name: string}> $characters postavy, stejná pravidla
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
        ?int $bggId = null,
    ): void {
        $this->name = $name;
        $this->bggId = $bggId;
        $this->shortName = null !== $shortName && '' !== trim($shortName) ? trim($shortName) : null;
        $this->scoreType = $scoreType;
        $this->imageUrl = $imageUrl ?: null;
        $this->description = $description ?: null;
        $this->cheatSheet = $cheatSheet ?: null;
        $this->sync($this->addons, $addons, fn (string $n): GameAddon => new GameAddon($this, $n));
        $this->sync($this->characters, $characters, fn (string $n): GameCharacter => new GameCharacter($this, $n));
    }

    /**
     * Synchronizuje pojmenované položky: existující podle `id` přejmenuje, chybějící odebere, nové přidá.
     * Položky bez `id` se spáruje s existující stejného názvu (aby zůstaly vazby z odehraných her).
     * Prázdné a opakované názvy (bez ohledu na velikost písmen a diakritiku, {@see NameKey}) se zahodí.
     *
     * @template T of GameAddon|GameCharacter
     *
     * @param Collection<int, T>                  $collection
     * @param list<array{id: ?int, name: string}> $items
     * @param callable(string): T                 $create
     */
    private function sync(Collection $collection, array $items, callable $create): void
    {
        $wanted = NamedItems::normalize($items);

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
                if (!\in_array($e, $kept, true) && NameKey::of($e->getName()) === NameKey::of($wanted[$i]['name'])) {
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
