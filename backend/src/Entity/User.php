<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'app_user')]
class User implements UserInterface
{
    /** Správce sdíleného katalogu her: smí upravovat a mazat hry (ADR 0023). */
    public const ROLE_GAMES_ADMIN = 'ROLE_GAMES_ADMIN';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $nickname = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * Role navíc kromě `ROLE_USER`, např. `ROLE_GAMES_ADMIN` (správce sdíleného katalogu her, ADR 0023).
     *
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    private array $roles = [];

    public function __construct(
        #[ORM\Column(length: 180, unique: true, nullable: true)]
        private ?string $googleId,
        #[ORM\Column(length: 180, unique: true)]
        private string $email,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGoogleId(): ?string
    {
        return $this->googleId;
    }

    /** Účty převzaté ze staré aplikace nemají Google ID; doplní se při prvním přihlášení. */
    public function linkGoogle(string $googleId): void
    {
        $this->googleId = $googleId;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getNickname(): ?string
    {
        return $this->nickname;
    }

    public function setNickname(string $nickname): void
    {
        $this->nickname = $nickname;
    }

    /** Každý uživatel musí mít přezdívku; dokud ji nenastaví, nemůže používat aplikaci. */
    public function hasNickname(): bool
    {
        return null !== $this->nickname && '' !== $this->nickname;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getRoles(): array
    {
        return array_values(array_unique(['ROLE_USER', ...$this->roles]));
    }

    public function addRole(string $role): void
    {
        if (!\in_array($role, $this->roles, true)) {
            $this->roles[] = $role;
        }
    }

    public function removeRole(string $role): void
    {
        $this->roles = array_values(array_filter($this->roles, static fn (string $r): bool => $r !== $role));
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }
}
