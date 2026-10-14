<?php

declare(strict_types=1);

namespace App\Entity;

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

    /** @var Collection<int, GameAddon> */
    #[ORM\OneToMany(targetEntity: GameAddon::class, mappedBy: 'game', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $addons;

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

    /** @param list<string> $addonNames */
    public function update(string $name, ScoreType $scoreType, ?string $imageUrl, ?string $description, ?string $cheatSheet, array $addonNames): void
    {
        $this->name = $name;
        $this->scoreType = $scoreType;
        $this->imageUrl = $imageUrl ?: null;
        $this->description = $description ?: null;
        $this->cheatSheet = $cheatSheet ?: null;
        $this->syncAddons($addonNames);
    }

    /**
     * Přidá nové a odebere chybějící přídavky; existující (podle názvu) ponechá,
     * aby zůstaly vazby z odehraných her.
     *
     * @param list<string> $names
     */
    private function syncAddons(array $names): void
    {
        $names = array_values(array_unique(array_filter(array_map('trim', $names), static fn (string $n): bool => '' !== $n)));
        foreach ($this->addons->toArray() as $addon) {
            if (!\in_array($addon->getName(), $names, true)) {
                $this->addons->removeElement($addon);
            }
        }
        $existing = array_map(static fn (GameAddon $a): string => $a->getName(), $this->addons->toArray());
        foreach ($names as $name) {
            if (!\in_array($name, $existing, true)) {
                $this->addons->add(new GameAddon($this, $name));
            }
        }
    }
}
