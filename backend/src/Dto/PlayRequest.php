<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PlayRequest
{
    /**
     * @param list<int>           $addonIds
     * @param list<string>        $tags
     * @param list<PlayerRequest> $players
     */
    public function __construct(
        public int $gameId,
        public \DateTimeImmutable $startedAt,
        public \DateTimeImmutable $endedAt,
        #[Assert\Count(min: 1, minMessage: 'Zápis musí obsahovat alespoň jednoho hráče.')]
        #[Assert\Valid]
        public array $players = [],
        public array $addonIds = [],
        #[Assert\All([new Assert\NotBlank(), new Assert\Length(max: 100)])]
        public array $tags = [],
        public ?string $notes = null,
        /** Ručně zadané číslo seance; bez něj se seance určí podle 24h pravidla. */
        #[Assert\Positive]
        public ?int $sessionNumber = null,
    ) {
    }

    #[Assert\IsTrue(message: 'Konec hry nesmí být před jejím začátkem.')]
    public function isEndAfterStart(): bool
    {
        return $this->endedAt >= $this->startedAt;
    }
}
