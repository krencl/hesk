<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\PartyRole;

final readonly class ChangeRoleRequest
{
    public function __construct(public PartyRole $role)
    {
    }
}
