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
     * @param list<NamedItemRequest> $players    vlastní hráči hry (`id` = id hráče party)
     */
    public function __construct(
        #[Assert\NotBlank, Assert\Length(max: 150)]
        public string $name = '',
        public ScoreType $scoreType = ScoreType::MostPoints,
        #[Assert\Regex(pattern: '~^(https?://\S+|/(?!/)\S*)$~', message: 'Adresa obrázku musí být http(s) odkaz nebo cesta začínající /.'), Assert\Length(max: 255)]
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
    ) {
    }
}
