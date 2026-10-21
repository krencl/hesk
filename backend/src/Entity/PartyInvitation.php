<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class PartyInvitation
{
    public const TTL = '+7 days';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $token;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Party $party,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $invitedBy,
        ?\DateTimeImmutable $now = null,
        /** Pozvánka pro konkrétního hosta: po přijetí se jeho zápisy převedou na účet pozvaného. */
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        private ?Player $guest = null,
    ) {
        $this->token = bin2hex(random_bytes(24));
        $this->expiresAt = ($now ?? new \DateTimeImmutable())->modify(self::TTL);
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getParty(): Party
    {
        return $this->party;
    }

    public function getGuest(): ?Player
    {
        return $this->guest;
    }

    /** Host zanikl (smazán nebo propojen s členem): pozvánka se pak chová jako obyčejná. */
    public function clearGuest(): void
    {
        $this->guest = null;
    }

    public function getInvitedBy(): User
    {
        return $this->invitedBy;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpired(?\DateTimeImmutable $now = null): bool
    {
        return ($now ?? new \DateTimeImmutable()) > $this->expiresAt;
    }
}
