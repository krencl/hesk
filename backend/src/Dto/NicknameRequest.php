<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class NicknameRequest
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim'), Assert\Length(min: 2, max: 100, normalizer: 'trim')]
        public string $nickname = '',
    ) {
    }
}
