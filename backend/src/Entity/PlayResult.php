<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'play_result')]
#[ORM\UniqueConstraint(columns: ['play_id', 'user_id'])]
class PlayResult
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'results')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Play $play,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        private User $user,
        #[ORM\Column(nullable: true)]
        private ?float $score,
        #[ORM\Column(name: 'placement')]
        private int $rank,
        #[ORM\Column]
        private bool $winner,
    ) {
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getScore(): ?float
    {
        return $this->score;
    }

    public function getRank(): int
    {
        return $this->rank;
    }

    public function isWinner(): bool
    {
        return $this->winner;
    }
}
