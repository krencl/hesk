<?php

declare(strict_types=1);

namespace App\Enum;

enum PartyRole: string
{
    case Admin = 'admin';
    case Member = 'member';
}
