<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Party;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdatePartyRequest
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim'), Assert\Length(max: 100)]
        public string $name = '',
        #[Assert\Length(max: Party::SHORT_NAME_MAX)]
        public ?string $shortName = null,
    ) {
    }
}
