<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BggGameRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Lokální cache her z BoardGameGeek (ADR 0021): jméno a rok z hledání, adresa originálního obrázku na BGG CDN
 * po prvním výběru hry (obrázek samotný se neukládá, ořez jde přes upload z prohlížeče, ADR 0020). Sdílená mezi všemi partami, neobsahuje nic z partiového obsahu.
 */
#[ORM\Entity(repositoryClass: BggGameRepository::class)]
class BggGame
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column]
    private \DateTimeImmutable $fetchedAt;

    public function __construct(
        #[ORM\Column(unique: true)]
        private int $bggId,
        #[ORM\Column(length: 255)]
        private string $name,
        #[ORM\Column(name: 'published_year', nullable: true)]
        private ?int $year = null,
    ) {
        $this->fetchedAt = new \DateTimeImmutable();
    }

    public function getBggId(): int
    {
        return $this->bggId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function refresh(string $name, ?int $year): void
    {
        $this->name = $name;
        $this->year = $year ?? $this->year;
        $this->fetchedAt = new \DateTimeImmutable();
    }

    public function setImageUrl(?string $imageUrl): void
    {
        $this->imageUrl = $imageUrl;
    }
}
