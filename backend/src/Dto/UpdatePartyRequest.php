<?php

declare(strict_types=1);

namespace App\Dto;

use App\Domain\PartyShortName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdatePartyRequest
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim'), Assert\Length(max: 100)]
        public string $name = '',
        #[Assert\NotBlank(normalizer: 'trim'), Assert\Length(max: PartyShortName::MAX_LENGTH, normalizer: 'trim')]
        public string $shortName = '',
    ) {
    }
}
