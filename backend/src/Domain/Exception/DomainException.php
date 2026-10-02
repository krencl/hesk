<?php

declare(strict_types=1);

namespace App\Domain\Exception;

/** Porušení doménového pravidla; API ji překládá na HTTP 422. */
class DomainException extends \DomainException
{
}
