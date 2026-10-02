<?php

declare(strict_types=1);

namespace App\Enum;

enum ScoreType: string
{
    case MostPoints = 'most_points';
    case FewestPoints = 'fewest_points';
    case RankOrder = 'rank_order';
    case WinnerOnly = 'winner_only';
}
