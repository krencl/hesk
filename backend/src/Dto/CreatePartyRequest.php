<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreatePartyRequest
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim'), Assert\Length(max: 100)]
        public string $name = '',
    ) {
    }
}
