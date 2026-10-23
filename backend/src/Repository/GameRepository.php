<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Game;
use App\Entity\Party;
use App\Entity\PartyGame;
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
     * Hry zařazené do party: hledání v názvu, filtr podle čísla seance, řazení (podle poslední aktivity party, nebo názvu).
     *
     * @return list<Game>
     */
    public function search(Party $party, ?string $query, string $sort, ?int $sessionNumber, ?int $limit, int $offset): array
    {
        $qb = $this->createQueryBuilder('g')
            ->join(PartyGame::class, 'pg', 'WITH', 'pg.game = g AND pg.party = :party')->setParameter('party', $party);

        $query = null === $query ? '' : trim($query);
        if ('' !== $query) {
            $qb->andWhere('LOWER(g.name) LIKE :q ESCAPE \'!\'')
                ->setParameter('q', '%'.strtr(mb_strtolower($query), ['!' => '!!', '%' => '!%', '_' => '!_']).'%');
        }
        if (null !== $sessionNumber) {
            $qb->andWhere('EXISTS (SELECT 1 FROM '.Play::class.' fp JOIN fp.session fs WHERE fp.game = g AND fp.party = :party AND fs.number = :sn)')
                ->setParameter('sn', $sessionNumber);
        }

        if (self::SORT_NAME === $sort) {
            $qb->orderBy('g.name', 'ASC');
        } else {
            $qb->addSelect('(SELECT MAX(lp.startedAt) FROM '.Play::class.' lp WHERE lp.game = g AND lp.party = :party) AS HIDDEN lastAt')
                ->orderBy('lastAt', 'DESC')->addOrderBy('g.name', 'ASC');
        }
        $qb->addOrderBy('g.id', 'ASC')->setFirstResult($offset);
        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Hry z katalogu, které party ještě nemá, pro nabídku při přidávání hry (ADR 0021, 0023). Nejdřív názvy začínající
     * hledaným textem.
     *
     * @return list<Game>
     */
    public function searchOutsideParty(Party $party, string $query, int $limit): array
    {
        $escaped = strtr(mb_strtolower(trim($query)), ['!' => '!!', '%' => '!%', '_' => '!_']);

        return $this->createQueryBuilder('g')
            ->andWhere('NOT EXISTS (SELECT 1 FROM '.PartyGame::class.' x WHERE x.game = g AND x.party = :party)')->setParameter('party', $party)
            ->andWhere('LOWER(g.name) LIKE :q ESCAPE \'!\'')->setParameter('q', "%$escaped%")
            ->addSelect('CASE WHEN LOWER(g.name) LIKE :prefix ESCAPE \'!\' THEN 0 ELSE 1 END AS HIDDEN matchRank')->setParameter('prefix', "$escaped%")
            ->orderBy('matchRank', 'ASC')->addOrderBy('g.name', 'ASC')->addOrderBy('g.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    /**
     * Hra, která by byla duplicitou: se stejným `bgg_id`; u hry bez `bgg_id` se stejným názvem (bez ohledu na velikost
     * písmen). `$except` je upravovaná hra, ta duplicitou sama sobě není.
     */
    public function findDuplicate(?int $bggId, string $name, ?Game $except = null): ?Game
    {
        $qb = $this->createQueryBuilder('g')->setMaxResults(1);
        if (null !== $bggId) {
            $qb->andWhere('g.bggId = :bgg')->setParameter('bgg', $bggId);
        } else {
            $qb->andWhere('LOWER(g.name) = :name')->setParameter('name', mb_strtolower(trim($name)));
        }
        if (null !== $except) {
            $qb->andWhere('g != :except')->setParameter('except', $except);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Načte najednou to, co výstup seznamu her čte z kolekcí (rozšíření, postavy, tagy a vlastní hráči hry v partě), aby se
     * kolekce nenačítaly po jedné hře. Každá kolekce má vlastní dotaz (fetch join), takže se nenásobí řádky.
     *
     * @param list<Game> $games
     */
    public function preloadDetails(Party $party, array $games): void
    {
        if ([] === $games) {
            return;
        }
        $em = $this->getEntityManager();
        foreach (['addons', 'characters', 'tags'] as $collection) {
            $em->createQuery('SELECT g, x FROM '.Game::class.' g LEFT JOIN g.'.$collection.' x WHERE g IN (:games)')
                ->setParameter('games', $games)->getResult();
        }
        $em->createQuery('SELECT pg, pl FROM '.PartyGame::class.' pg LEFT JOIN pg.players pl WHERE pg.party = :party AND pg.game IN (:games)')
            ->setParameter('party', $party)->setParameter('games', $games)->getResult();
    }

    /**
     * Poslední zápis a počet zápisů party pro dané hry (klíč = id hry).
     *
     * @param list<Game> $games
     *
     * @return array<int, array{lastPlay: Play|null, playCount: int}>
     */
    public function statsFor(Party $party, array $games): array
    {
        $stats = [];
        foreach ($games as $game) {
            $stats[(int) $game->getId()] = ['lastPlay' => null, 'playCount' => 0];
        }
        if ([] === $stats) {
            return [];
        }

        $em = $this->getEntityManager();
        foreach ($em->createQuery('SELECT IDENTITY(p.game) AS gameId, COUNT(p.id) AS cnt FROM '.Play::class.' p WHERE p.party = :party AND p.game IN (:games) GROUP BY p.game')
            ->setParameter('party', $party)->setParameter('games', $games)->getArrayResult() as $row) {
            $stats[(int) $row['gameId']]['playCount'] = (int) $row['cnt'];
        }

        /** @var list<Play> $lastPlays */
        $lastPlays = $em->createQuery(
            'SELECT p, s, r, pl FROM '.Play::class.' p JOIN p.session s LEFT JOIN p.results r LEFT JOIN r.player pl
             WHERE p.party = :party AND p.game IN (:games) AND p.startedAt = (SELECT MAX(p2.startedAt) FROM '.Play::class.' p2 WHERE p2.party = :party AND p2.game = p.game)
             ORDER BY p.id ASC',
        )->setParameter('party', $party)->setParameter('games', $games)->getResult();
        foreach ($lastPlays as $play) {
            $stats[(int) $play->getGame()->getId()]['lastPlay'] = $play; // při shodě času vyhrává poslední (nejvyšší id)
        }

        return $stats;
    }
}
