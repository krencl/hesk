<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BggGame;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<BggGame> */
class BggGameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BggGame::class);
    }

    /**
     * Cache podle názvu: shody na začátku názvu první, pak abecedně.
     *
     * @return list<BggGame>
     */
    public function searchByName(string $query, int $limit): array
    {
        $escaped = strtr(mb_strtolower($query), ['!' => '!!', '%' => '!%', '_' => '!_']);

        return $this->createQueryBuilder('b')
            ->andWhere('LOWER(b.name) LIKE :q ESCAPE \'!\'')->setParameter('q', "%$escaped%")
            ->addSelect('CASE WHEN LOWER(b.name) LIKE :prefix ESCAPE \'!\' THEN 0 ELSE 1 END AS HIDDEN matchRank')->setParameter('prefix', "$escaped%")
            ->orderBy('matchRank', 'ASC')->addOrderBy('b.name', 'ASC')->addOrderBy('b.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }
}
