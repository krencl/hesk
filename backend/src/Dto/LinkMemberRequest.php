<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class LinkMemberRequest
{
    public function __construct(
        #[Assert\Positive]
        public int $userId = 0,
    ) {
    }
}
