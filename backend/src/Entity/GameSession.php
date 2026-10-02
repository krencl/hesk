<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\GameSessionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Herní seance: sled her odehraných skupinou, kde mezi sousedními zápisy
 * není větší mezera než {@see self::MAX_GAP}.
 */
#[ORM\Entity(repositoryClass: GameSessionRepository::class)]
class GameSession
{
    public const MAX_GAP = '24 hours';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Party $party,
        #[ORM\Column]
        private \DateTimeImmutable $startedAt,
        #[ORM\Column]
        private \DateTimeImmutable $endedAt,
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

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getEndedAt(): \DateTimeImmutable
    {
        return $this->endedAt;
    }

    /** Patří zápis (interval) do této seance? */
    public function accepts(\DateTimeImmutable $start, \DateTimeImmutable $end): bool
    {
        $gap = \DateInterval::createFromDateString(self::MAX_GAP);

        return $start <= $this->endedAt->add($gap) && $end >= $this->startedAt->sub($gap);
    }

    public function extendTo(\DateTimeImmutable $start, \DateTimeImmutable $end): void
    {
        $this->startedAt = min($this->startedAt, $start);
        $this->endedAt = max($this->endedAt, $end);
    }

    public function resetBounds(\DateTimeImmutable $start, \DateTimeImmutable $end): void
    {
        $this->startedAt = $start;
        $this->endedAt = $end;
    }
}
