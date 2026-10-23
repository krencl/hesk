<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\GameSessionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Herní seance: sled her odehraných partou, kde mezi sousedními zápisy
 * není větší mezera než {@see self::MAX_GAP}.
 */
#[ORM\Entity(repositoryClass: GameSessionRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_session_party_number', columns: ['party_id', 'number'])]
class GameSession
{
    public const MAX_GAP = '24 hours';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Party $party,
        /** Pořadové číslo seance v partě (ze staré aplikace ručně udržované, může mít mezery). */
        #[ORM\Column]
        private int $number,
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

    public function getNumber(): int
    {
        return $this->number;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getEndedAt(): \DateTimeImmutable
    {
        return $this->endedAt;
    }

    /**
     * Meze, mezi kterými musí seance být, aby do ní zápis (interval) patřil: seance musí končit nejdřív v `$from`
     * a začínat nejpozději v `$to` (tj. od zápisu je nejvýš {@see self::MAX_GAP}). Stejná pravidla používá
     * {@see self::accepts()} i dotaz `GameSessionRepository::findAccepting()`.
     *
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable} [from, to]
     */
    public static function acceptanceWindow(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $gap = \DateInterval::createFromDateString(self::MAX_GAP);

        return [$start->sub($gap), $end->add($gap)];
    }

    /** Patří zápis (interval) do této seance? */
    public function accepts(\DateTimeImmutable $start, \DateTimeImmutable $end): bool
    {
        [$from, $to] = self::acceptanceWindow($start, $end);

        return $this->endedAt >= $from && $this->startedAt <= $to;
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
