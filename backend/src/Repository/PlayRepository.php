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
        $qb = $this->createQueryBuilder('p')->addSelect('s', 'g')->join('p.session', 's')->join('p.game', 'g')
            ->andWhere('p.party = :party')->setParameter('party', $party)
            ->orderBy('p.startedAt', 'DESC')->addOrderBy('p.id', 'DESC')
            ->setMaxResults($limit)->setFirstResult($offset);
        if (null !== $gameId) {
            $qb->andWhere('IDENTITY(p.game) = :game')->setParameter('game', $gameId);
        }

        /** @var list<Play> $plays */
        $plays = $qb->getQuery()->getResult();
        $this->preloadDetails($plays);

        return $plays;
    }

    /**
     * Načte najednou kolekce, které výstup zápisu čte (výsledky s hráči a postavami, rozšíření, tagy), aby se nenačítaly
     * po jednom zápisu. Každá kolekce má vlastní dotaz (fetch join), takže se nenásobí řádky. Výsledky se řadí podle
     * hráče (id), aby pořadí při shodě umístění bylo stálé a stejné, jaké dával dřívější lazy load (unikát play_id + player_id).
     *
     * @param list<Play> $plays
     */
    public function preloadDetails(array $plays): void
    {
        if ([] === $plays) {
            return;
        }
        $em = $this->getEntityManager();
        $em->createQuery('SELECT p, r, pl, c FROM '.Play::class.' p LEFT JOIN p.results r LEFT JOIN r.player pl LEFT JOIN r.character c WHERE p IN (:plays) ORDER BY pl.id ASC')
            ->setParameter('plays', $plays)->getResult();
        foreach (['addons', 'tags'] as $collection) {
            $em->createQuery('SELECT p, x FROM '.Play::class.' p LEFT JOIN p.'.$collection.' x WHERE p IN (:plays)')
                ->setParameter('plays', $plays)->getResult();
        }
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

    /**
     * Výhry hráčů v partě (volitelně jen zápisy odpovídající filtru her a tagů).
     *
     * @return list<array{playerId: int, name: string, wins: int, plays: int}>
     */
    public function winsPerPlayer(Party $party, StatsFilter $filter = new StatsFilter()): array
    {
        $query = $this->getEntityManager()->createQuery(
            'SELECT pl.id AS playerId, pl.name AS name, COUNT(r.id) AS plays, SUM(CASE WHEN r.winner = true THEN 1 ELSE 0 END) AS wins
             FROM App\Entity\PlayResult r JOIN r.play p JOIN r.player pl
             WHERE p.party = :party'.$this->filterDql($filter).' GROUP BY pl.id, pl.name ORDER BY wins DESC, plays DESC',
        )->setParameter('party', $party);
        $rows = $this->bindFilter($query, $filter)->getArrayResult();

        return array_map(static fn (array $r): array => [
            'playerId' => (int) $r['playerId'], 'name' => $r['name'], 'wins' => (int) $r['wins'], 'plays' => (int) $r['plays'],
        ], $rows);
    }

    /** @return list<array{gameId: int, name: string, shortName: ?string, plays: int}> */
    public function topGames(Party $party, int $limit = 10, StatsFilter $filter = new StatsFilter()): array
    {
        $query = $this->getEntityManager()->createQuery(
            'SELECT g.id AS gameId, g.name AS name, g.shortName AS shortName, COUNT(p.id) AS plays
             FROM App\Entity\Play p JOIN p.game g WHERE p.party = :party'.$this->filterDql($filter).'
             GROUP BY g.id, g.name, g.shortName ORDER BY plays DESC, g.name ASC',
        )->setParameter('party', $party)->setMaxResults($limit);
        $rows = $this->bindFilter($query, $filter)->getArrayResult();

        return array_map(static fn (array $r): array => [
            'gameId' => (int) $r['gameId'], 'name' => $r['name'], 'shortName' => $r['shortName'], 'plays' => (int) $r['plays'],
        ], $rows);
    }

    /**
     * Kolikrát byl každý hráč označen jako začínající (jen u her, kde se začínající eviduje).
     *
     * @return list<array{playerId: int, name: string, starts: int}>
     */
    public function startingPerPlayer(Party $party, StatsFilter $filter = new StatsFilter()): array
    {
        $query = $this->getEntityManager()->createQuery(
            'SELECT pl.id AS playerId, pl.name AS name, COUNT(r.id) AS starts
             FROM App\Entity\PlayResult r JOIN r.play p JOIN r.player pl
             WHERE p.party = :party AND r.starting = true'.$this->filterDql($filter).' GROUP BY pl.id, pl.name ORDER BY starts DESC, pl.name ASC',
        )->setParameter('party', $party);
        $rows = $this->bindFilter($query, $filter)->getArrayResult();

        return array_map(static fn (array $r): array => ['playerId' => (int) $r['playerId'], 'name' => $r['name'], 'starts' => (int) $r['starts']], $rows);
    }

    /**
     * Nejčastější tagy zápisů (libovolný význam, např. místo nebo příležitost); zápis se počítá do každého svého tagu.
     *
     * @return list<array{name: string, plays: int}>
     */
    public function topPlayTags(Party $party, int $limit = 10, StatsFilter $filter = new StatsFilter()): array
    {
        $query = $this->getEntityManager()->createQuery(
            'SELECT t.name AS name, COUNT(p.id) AS plays
             FROM App\Entity\Play p JOIN p.tags t WHERE p.party = :party'.$this->filterDql($filter).'
             GROUP BY t.id, t.name ORDER BY plays DESC, t.name ASC',
        )->setParameter('party', $party)->setMaxResults($limit);
        $rows = $this->bindFilter($query, $filter)->getArrayResult();

        return array_map(static fn (array $r): array => ['name' => $r['name'], 'plays' => (int) $r['plays']], $rows);
    }

    /** Podmínky filtru na hru zápisu (alias zápisu je `p`); poddotazy mají unikátní aliasy, ať jdou použít vedle sebe. */
    private function filterDql(StatsFilter $filter): string
    {
        $match = static function (array $games, array $tags, string $suffix): string {
            $parts = [];
            if ([] !== $games) {
                $parts[] = 'IDENTITY(p.game) IN (:'.$suffix.'Games)';
            }
            if ([] !== $tags) {
                $parts[] = 'EXISTS (SELECT 1 FROM App\Entity\Game g_'.$suffix.' JOIN g_'.$suffix.'.tags t_'.$suffix.' WHERE g_'.$suffix.' = p.game AND t_'.$suffix.'.name IN (:'.$suffix.'Tags))';
            }

            return implode(' OR ', $parts);
        };
        $dql = '';
        if ([] !== $filter->includeGames || [] !== $filter->includeTags) {
            $dql .= ' AND ('.$match($filter->includeGames, $filter->includeTags, 'include').')';
        }
        if ([] !== $filter->excludeGames || [] !== $filter->excludeTags) {
            $dql .= ' AND NOT ('.$match($filter->excludeGames, $filter->excludeTags, 'exclude').')';
        }

        return $dql;
    }

    private function bindFilter(\Doctrine\ORM\Query $query, StatsFilter $filter): \Doctrine\ORM\Query
    {
        foreach (['include' => [$filter->includeGames, $filter->includeTags], 'exclude' => [$filter->excludeGames, $filter->excludeTags]] as $suffix => [$games, $tags]) {
            if ([] !== $games) {
                $query->setParameter($suffix.'Games', $games);
            }
            if ([] !== $tags) {
                $query->setParameter($suffix.'Tags', $tags);
            }
        }

        return $query;
    }
}
