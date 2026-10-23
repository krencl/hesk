<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\NamedItems;
use App\Entity\Party;
use App\Entity\PlayTag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PlayTag> */
class PlayTagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlayTag::class);
    }

    /**
     * Tagy zápisů party podle názvů: názvy se očistí a sloučí bez ohledu na velikost písmen a diakritiku (jako kolace DB),
     * existující tagy se najdou jedním dotazem, chybějící se založí (persist bez flush).
     *
     * @param list<string> $names
     *
     * @return list<PlayTag> každý tag nejvýš jednou
     */
    public function findOrCreate(Party $party, array $names): array
    {
        $names = NamedItems::uniqueNames($names);
        if ([] === $names) {
            return [];
        }
        $existing = $this->createQueryBuilder('t')
            ->where('t.party = :party')->andWhere('t.name IN (:names)')
            ->setParameter('party', $party)->setParameter('names', $names)
            ->getQuery()->getResult();

        return NamedItems::resolve($names, $existing, static fn (PlayTag $t): string => $t->getName(), function (string $name) use ($party): PlayTag {
            $this->getEntityManager()->persist($tag = new PlayTag($party, $name));

            return $tag;
        });
    }
}
