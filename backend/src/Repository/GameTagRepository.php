<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\NamedItems;
use App\Entity\GameTag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<GameTag> */
class GameTagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameTag::class);
    }

    /**
     * Tagy hry (sdílené v katalogu) podle názvů: názvy se očistí a sloučí bez ohledu na velikost písmen a diakritiku
     * (jako kolace DB), existující tagy se najdou jedním dotazem, chybějící se založí (persist bez flush).
     *
     * @param list<string> $names
     *
     * @return list<GameTag> každý tag nejvýš jednou
     */
    public function findOrCreate(array $names): array
    {
        $names = NamedItems::uniqueNames($names);
        if ([] === $names) {
            return [];
        }
        $existing = $this->createQueryBuilder('t')->where('t.name IN (:names)')->setParameter('names', $names)->getQuery()->getResult();

        return NamedItems::resolve($names, $existing, static fn (GameTag $t): string => $t->getName(), function (string $name): GameTag {
            $this->getEntityManager()->persist($tag = new GameTag($name));

            return $tag;
        });
    }
}
