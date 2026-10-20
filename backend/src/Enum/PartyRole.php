<?php

declare(strict_types=1);

namespace App\Enum;

enum PartyRole: string
{
    /** Spravuje partu a může vše. */
    case Admin = 'admin';
    /** Může přidávat a upravovat zápisy her. */
    case Writer = 'writer';
    /** Zápisy jen čte. */
    case Member = 'member';
}
