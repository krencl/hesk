<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'play_result')]
#[ORM\UniqueConstraint(columns: ['play_id', 'player_id'])]
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
        private Player $player,
        #[ORM\Column(nullable: true)]
        private ?float $score,
        #[ORM\Column(name: 'placement')]
        private int $rank,
        #[ORM\Column]
        private bool $winner,
        #[ORM\Column(name: 'is_starting')]
        private bool $starting = false,
        /** Postava, kterou hráč v zápisu hrál (jen u her s postavami). */
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        private ?GameCharacter $character = null,
    ) {
    }

    public function getPlayer(): Player
    {
        return $this->player;
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

    public function getCharacter(): ?GameCharacter
    {
        return $this->character;
    }

    /** Hráč, který ve hře začínal. */
    public function isStarting(): bool
    {
        return $this->starting;
    }
}
