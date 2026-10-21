<?php

declare(strict_types=1);

namespace App\Service\Bgg;

/** BGG API nejde použít (není nastavený token, chyba sítě, odmítnutý token, přetížení). */
final class BggUnavailableException extends \RuntimeException
{
}
