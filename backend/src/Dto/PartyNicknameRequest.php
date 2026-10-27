<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PartyNicknameRequest
{
    /** Prázdný nebo jen z mezer = `null` = použít přezdívku účtu. */
    #[Assert\Length(min: 2, max: 100, normalizer: 'trim')]
    public ?string $nickname;

    public function __construct(?string $nickname = null)
    {
        $this->nickname = null === $nickname || '' === trim($nickname) ? null : $nickname;
    }
}
