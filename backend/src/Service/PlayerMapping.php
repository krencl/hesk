<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Player;

/**
 * Co se při přesunu zápisu stane s jedním hráčem: buď přejde na existujícího hráče cílové party (`$to`),
 * nebo se tam pod jménem `$newName` založí nový host.
 */
final readonly class PlayerMapping
{
    public function __construct(
        public Player $from,
        public ?Player $to,
        /** Jméno nového hosta; `null`, když hráč přechází na existujícího. */
        public ?string $newName = null,
    ) {
    }

    public function targetName(): string
    {
        return $this->to?->getName() ?? $this->newName ?? $this->from->getName();
    }

    public function isCreated(): bool
    {
        return null === $this->to;
    }

    /** Je cílový hráč člen s účtem? (Nový host nikdy.) */
    public function targetIsMember(): bool
    {
        return null !== $this->to?->getUser();
    }
}
