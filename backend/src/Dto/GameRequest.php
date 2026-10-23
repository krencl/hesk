<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\ScoreType;
use App\Service\CoverStorage;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class GameRequest
{
    /**
     * @param list<NamedItemRequest> $addons     rozšíření hry
     * @param list<NamedItemRequest> $characters postavy hry
     * @param list<NamedItemRequest> $players    vlastní hráči hry (`id` = id hráče party)
     * @param list<string>           $tags       tagy hry v partě
     */
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim'), Assert\Length(max: 150, normalizer: 'trim')]
        public string $name = '',
        public ScoreType $scoreType = ScoreType::MostPoints,
        #[Assert\Regex(pattern: CoverStorage::URL_PATTERN, message: 'Obrázek hry se musí nahrát (adresa nesedí).')]
        public ?string $imageUrl = null,
        public ?string $description = null,
        public ?string $cheatSheet = null,
        #[Assert\Valid]
        public array $addons = [],
        #[Assert\Length(max: 50)]
        public ?string $shortName = null,
        #[Assert\Valid]
        public array $characters = [],
        #[Assert\Valid]
        public array $players = [],
        #[Assert\Positive]
        public ?int $bggId = null,
        #[Assert\All([new Assert\NotBlank(), new Assert\Length(max: 100)])]
        public array $tags = [],
    ) {
    }
}
