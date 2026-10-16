<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\ScoreType;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class GameRequest
{
    /**
     * @param list<NamedItemRequest> $addons     rozšíření hry
     * @param list<NamedItemRequest> $characters postavy hry
     */
    public function __construct(
        #[Assert\NotBlank, Assert\Length(max: 150)]
        public string $name = '',
        public ScoreType $scoreType = ScoreType::MostPoints,
        #[Assert\Url(requireTld: false), Assert\Length(max: 255)]
        public ?string $imageUrl = null,
        public ?string $description = null,
        public ?string $cheatSheet = null,
        #[Assert\Valid]
        public array $addons = [],
        #[Assert\Length(max: 50)]
        public ?string $shortName = null,
        #[Assert\Valid]
        public array $characters = [],
        /** Vlastní hráč hry (hráč té samé party). */
        public ?int $playerId = null,
    ) {
    }
}
