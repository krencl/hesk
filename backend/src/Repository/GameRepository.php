<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Game;
use App\Entity\Party;
use App\Entity\Play;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Game> */
class GameRepository extends ServiceEntityRepository
{
    public const SORT_ACTIVITY = 'activity';
    public const SORT_NAME = 'name';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game::class);
    }

    /**
     * Hry skupiny: hledání v názvu, filtr podle čísla seance, řazení (podle poslední aktivity, nebo názvu).
     *
     * @return list<Game>
     */
    public function search(Party $party, ?string $query, string $sort, ?int $sessionNumber, ?int $limit, int $offset): array
    {
        $qb = $this->createQueryBuilder('g')
            ->andWhere('g.party = :party')->setParameter('party', $party);

        $query = null === $query ? '' : trim($query);
        if ('' !== $query) {
            $qb->andWhere('LOWER(g.name) LIKE :q ESCAPE \'!\'')
                ->setParameter('q', '%'.strtr(mb_strtolower($query), ['!' => '!!', '%' => '!%', '_' => '!_']).'%');
        }
        if (null !== $sessionNumber) {
            $qb->andWhere('EXISTS (SELECT 1 FROM '.Play::class.' fp JOIN fp.session fs WHERE fp.game = g AND fs.number = :sn)')
                ->setParameter('sn', $sessionNumber);
        }

        if (self::SORT_NAME === $sort) {
            $qb->orderBy('g.name', 'ASC');
        } else {
            $qb->addSelect('(SELECT MAX(lp.startedAt) FROM '.Play::class.' lp WHERE lp.game = g) AS HIDDEN lastAt')
                ->orderBy('lastAt', 'DESC')->addOrderBy('g.name', 'ASC');
        }
        $qb->addOrderBy('g.id', 'ASC')->setFirstResult($offset);
        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Poslední zápis a počet zápisů pro dané hry (klíč = id hry).
     *
     * @param list<Game> $games
     *
     * @return array<int, array{lastPlay: Play|null, playCount: int}>
     */
    public function statsFor(array $games): array
    {
        $stats = [];
        foreach ($games as $game) {
            $stats[(int) $game->getId()] = ['lastPlay' => null, 'playCount' => 0];
        }
        if ([] === $stats) {
            return [];
        }

        $em = $this->getEntityManager();
        foreach ($em->createQuery('SELECT IDENTITY(p.game) AS gameId, COUNT(p.id) AS cnt FROM '.Play::class.' p WHERE p.game IN (:games) GROUP BY p.game')
            ->setParameter('games', $games)->getArrayResult() as $row) {
            $stats[(int) $row['gameId']]['playCount'] = (int) $row['cnt'];
        }

        /** @var list<Play> $lastPlays */
        $lastPlays = $em->createQuery(
            'SELECT p, s, r, pl FROM '.Play::class.' p JOIN p.session s LEFT JOIN p.results r LEFT JOIN r.player pl
             WHERE p.game IN (:games) AND p.startedAt = (SELECT MAX(p2.startedAt) FROM '.Play::class.' p2 WHERE p2.game = p.game)
             ORDER BY p.id ASC',
        )->setParameter('games', $games)->getResult();
        foreach ($lastPlays as $play) {
            $stats[(int) $play->getGame()->getId()]['lastPlay'] = $play; // při shodě času vyhrává poslední (nejvyšší id)
        }

        return $stats;
    }
}
