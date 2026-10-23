<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Štítek hry ve sdíleném katalogu (např. „rychlovka“, „strategická“); jiný než `Tag` u zápisů (místo, příležitost). */
#[ORM\Entity]
class GameTag
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\Column(length: 100, unique: true)]
        private string $name,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
