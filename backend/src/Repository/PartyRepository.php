<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Party;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Party> */
class PartyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Party::class);
    }

    /** @return list<Party> */
    public function findByMember(User $user): array
    {
        return $this->createQueryBuilder('p')->join('p.members', 'm')
            ->andWhere('m.user = :u')->setParameter('u', $user)
            ->orderBy('p.name')->getQuery()->getResult();
    }
}
