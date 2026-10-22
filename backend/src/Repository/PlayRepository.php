<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Game;
use App\Entity\GameSession;
use App\Entity\Party;
use App\Entity\Play;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Play> */
class PlayRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Play::class);
    }

    /** @return list<Play> */
    public function findPage(Party $party, int $limit, int $offset, ?int $gameId = null): array
    {
        $qb = $this->createQueryBuilder('p')->addSelect('s')->join('p.session', 's')
            ->andWhere('p.party = :party')->setParameter('party', $party)
            ->orderBy('p.startedAt', 'DESC')->addOrderBy('p.id', 'DESC')
            ->setMaxResults($limit)->setFirstResult($offset);
        if (null !== $gameId) {
            $qb->andWhere('IDENTITY(p.game) = :game')->setParameter('game', $gameId);
        }

        return $qb->getQuery()->getResult();
    }

    /** Počet zápisů hry; s `$party` jen zápisy té party, jinak ze všech part. */
    public function countForGame(Game $game, ?Party $party = null): int
    {
        $qb = $this->createQueryBuilder('p')->select('COUNT(p.id)')
            ->andWhere('p.game = :game')->setParameter('game', $game);
        if (null !== $party) {
            $qb->andWhere('p.party = :party')->setParameter('party', $party);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /** @return list<Play> */
    public function findBySession(GameSession $session): array
    {
        return $this->findBy(['session' => $session]);
    }

    /** Nejnovější zápis seance (podle konce hry). */
    public function findLastInSession(GameSession $session): ?Play
    {
        return $this->findOneBy(['session' => $session], ['endedAt' => 'DESC', 'id' => 'DESC']);
    }

    /**
     * Počet zápisů, ve kterých každý hráč party hrál.
     *
     * @return array<int, int> id hráče => počet zápisů (hráči bez zápisu chybí)
     */
    public function playCountsByPlayer(Party $party): array
    {
        $rows = $this->getEntityManager()->createQuery(
            'SELECT IDENTITY(r.player) AS playerId, COUNT(r.id) AS plays FROM App\Entity\PlayResult r JOIN r.play p WHERE p.party = :party GROUP BY r.player',
        )->setParameter('party', $party)->getArrayResult();

        return array_combine(array_map(static fn (array $r): int => (int) $r['playerId'], $rows), array_map(static fn (array $r): int => (int) $r['plays'], $rows));
    }

    /** @return list<array{playerId: int, name: string, wins: int, plays: int}> */
    public function winsPerPlayer(Party $party): array
    {
        $rows = $this->getEntityManager()->createQuery(
            'SELECT pl.id AS playerId, pl.name AS name, COUNT(r.id) AS plays, SUM(CASE WHEN r.winner = true THEN 1 ELSE 0 END) AS wins
             FROM App\Entity\PlayResult r JOIN r.play p JOIN r.player pl
             WHERE p.party = :party GROUP BY pl.id, pl.name ORDER BY wins DESC, plays DESC',
        )->setParameter('party', $party)->getArrayResult();

        return array_map(static fn (array $r): array => [
            'playerId' => (int) $r['playerId'], 'name' => $r['name'], 'wins' => (int) $r['wins'], 'plays' => (int) $r['plays'],
        ], $rows);
    }

    /** @return list<array{gameId: int, name: string, shortName: ?string, plays: int}> */
    public function topGames(Party $party, int $limit = 10): array
    {
        $rows = $this->getEntityManager()->createQuery(
            'SELECT g.id AS gameId, g.name AS name, g.shortName AS shortName, COUNT(p.id) AS plays
             FROM App\Entity\Play p JOIN p.game g WHERE p.party = :party
             GROUP BY g.id, g.name, g.shortName ORDER BY plays DESC, g.name ASC',
        )->setParameter('party', $party)->setMaxResults($limit)->getArrayResult();

        return array_map(static fn (array $r): array => [
            'gameId' => (int) $r['gameId'], 'name' => $r['name'], 'shortName' => $r['shortName'], 'plays' => (int) $r['plays'],
        ], $rows);
    }
}
