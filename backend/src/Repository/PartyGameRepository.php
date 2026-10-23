<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PartyGame;
use App\Entity\Player;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PartyGame> */
class PartyGameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PartyGame::class);
    }

    /**
     * Hry, ve kterých je hráč „vlastním hráčem hry“ (např. Boss u kooperativních her).
     *
     * @return list<PartyGame>
     */
    public function findByPlayer(Player $player, ?int $limit = null): array
    {
        $query = $this->getEntityManager()->createQuery('SELECT pg FROM '.PartyGame::class.' pg WHERE :player MEMBER OF pg.players')
            ->setParameter('player', $player);
        if (null !== $limit) {
            $query->setMaxResults($limit);
        }

        return $query->getResult();
    }
}
