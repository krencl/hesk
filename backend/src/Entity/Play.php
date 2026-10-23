<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PlayRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Zápis jedné odehrané hry. */
#[ORM\Entity(repositoryClass: PlayRepository::class)]
#[ORM\Index(columns: ['party_id', 'started_at'])]
class Play
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private GameSession $session;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    /** @var Collection<int, GameAddon> */
    #[ORM\ManyToMany(targetEntity: GameAddon::class)]
    #[ORM\JoinTable(name: 'play_addon')]
    private Collection $addons;

    /** @var Collection<int, Tag> */
    #[ORM\ManyToMany(targetEntity: Tag::class)]
    #[ORM\JoinTable(name: 'play_play_tag')]
    private Collection $tags;

    /** @var Collection<int, PlayResult> */
    #[ORM\OneToMany(targetEntity: PlayResult::class, mappedBy: 'play', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $results;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Party $party,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        private Game $game,
        #[ORM\Column]
        private \DateTimeImmutable $startedAt,
        #[ORM\Column]
        private \DateTimeImmutable $endedAt,
        GameSession $session,
    ) {
        $this->session = $session;
        $this->addons = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->results = new ArrayCollection();
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

    public function setGame(Game $game): void
    {
        $this->game = $game;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getEndedAt(): \DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function setTimes(\DateTimeImmutable $startedAt, \DateTimeImmutable $endedAt): void
    {
        $this->startedAt = $startedAt;
        $this->endedAt = $endedAt;
    }

    public function getSession(): GameSession
    {
        return $this->session;
    }

    public function setSession(GameSession $session): void
    {
        $this->session = $session;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes ?: null;
    }

    /** @return Collection<int, GameAddon> */
    public function getAddons(): Collection
    {
        return $this->addons;
    }

    /** @param list<GameAddon> $addons */
    public function setAddons(array $addons): void
    {
        $this->addons = new ArrayCollection($addons);
    }

    /** @return Collection<int, Tag> */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    /** @param list<Tag> $tags */
    public function setTags(array $tags): void
    {
        $this->tags = new ArrayCollection($tags);
    }

    /** @return Collection<int, PlayResult> */
    public function getResults(): Collection
    {
        return $this->results;
    }

    /** @param list<PlayResult> $results */
    public function replaceResults(array $results): void
    {
        $this->results->clear();
        foreach ($results as $result) {
            $this->results->add($result);
        }
    }
}
