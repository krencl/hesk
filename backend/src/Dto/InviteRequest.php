<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Nová pozvánka do party; s `guestId` je to pozvánka pro hosta, jehož zápisy se po přijetí převedou na účet pozvaného. */
final readonly class InviteRequest
{
    public function __construct(
        #[Assert\Positive]
        public ?int $guestId = null,
    ) {
    }
}
