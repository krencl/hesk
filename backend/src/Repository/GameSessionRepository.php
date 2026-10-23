<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GameSession;
use App\Entity\Party;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<GameSession> */
class GameSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameSession::class);
    }

    public function findOneByNumber(Party $party, int $number): ?GameSession
    {
        return $this->findOneBy(['party' => $party, 'number' => $number]);
    }

    /**
     * Seance, do které by zápis v daném čase spadl (24h pravidlo, viz {@see GameSession::acceptanceWindow()}); je-li jich
     * víc, ta, která skončila naposledy.
     */
    public function findAccepting(Party $party, \DateTimeImmutable $start, \DateTimeImmutable $end): ?GameSession
    {
        [$from, $to] = GameSession::acceptanceWindow($start, $end);
        // v časové zóně aplikace, ve které se seance načítají z databáze
        $zone = new \DateTimeZone(date_default_timezone_get());

        return $this->createQueryBuilder('s')
            ->andWhere('s.party = :party')->setParameter('party', $party)
            ->andWhere('s.endedAt >= :from')->setParameter('from', $from->setTimezone($zone), Types::DATETIME_IMMUTABLE)
            ->andWhere('s.startedAt <= :to')->setParameter('to', $to->setTimezone($zone), Types::DATETIME_IMMUTABLE)
            ->orderBy('s.endedAt', 'DESC')->addOrderBy('s.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }

    /** Nejvyšší číslo seance party (0, když žádná není). */
    public function maxNumber(Party $party): int
    {
        return (int) $this->createQueryBuilder('s')->select('MAX(s.number)')
            ->andWhere('s.party = :party')->setParameter('party', $party)
            ->getQuery()->getSingleScalarResult();
    }
}
