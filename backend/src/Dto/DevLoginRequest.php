<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class DevLoginRequest
{
    public function __construct(
        #[Assert\NotBlank, Assert\Email]
        public string $email = '',
        public ?string $nickname = null,
    ) {
    }
}
