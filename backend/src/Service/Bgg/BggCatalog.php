<?php

declare(strict_types=1);

namespace App\Service\Bgg;

use App\Domain\Exception\DomainException;
use App\Entity\BggGame;
use App\Entity\Game;
use App\Entity\Party;
use App\Repository\BggGameRepository;
use App\Repository\GameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Hledání hry pro založení (ADR 0021). Zdroje podle priority: 1) hry party (lokální databáze), 2) lokální cache BGG,
 * 3) živé BGG API – to se volá, jen když první dva zdroje dohromady nedají aspoň `LOCAL_ENOUGH` výsledků,
 * a co vrátí, se uloží do cache.
 */
class BggCatalog
{
    public const LOCAL_ENOUGH = 5;
    public const PARTY_LIMIT = 5;

    public const BGG_SKIPPED = 'skipped';
    public const BGG_USED = 'used';
    public const BGG_UNAVAILABLE = 'unavailable';

    public function __construct(
        private readonly GameRepository $games,
        private readonly BggGameRepository $cache,
        private readonly BggClient $client,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{results: list<array{source: string, name: string, year: int|null, bggId: int|null, gameId: int|null}>, bgg: string}
     *                                                                                                                                 `source` je `party` (hra už v partě, `gameId`), `cache` nebo `bgg`; `bgg` říká, jestli se živé API použilo
     */
    public function search(Party $party, string $query): array
    {
        $results = [];
        $seen = [];

        foreach ($this->games->search($party, $query, GameRepository::SORT_NAME, null, self::PARTY_LIMIT, 0) as $game) {
            $results[] = ['source' => 'party', 'name' => $game->getName(), 'year' => null, 'bggId' => $game->getBggId(), 'gameId' => $game->getId()];
            if (null !== $game->getBggId()) {
                $seen[$game->getBggId()] = true;
            }
        }
        foreach ($this->cache->searchByName($query, BggClient::MAX_RESULTS) as $cached) {
            if (!isset($seen[$cached->getBggId()])) {
                $results[] = ['source' => 'cache', 'name' => $cached->getName(), 'year' => $cached->getYear(), 'bggId' => $cached->getBggId(), 'gameId' => null];
                $seen[$cached->getBggId()] = true;
            }
        }

        if (\count($results) >= self::LOCAL_ENOUGH) {
            return ['results' => $results, 'bgg' => self::BGG_SKIPPED];
        }

        try {
            $items = $this->client->search($query);
        } catch (BggUnavailableException $e) {
            $this->logger->warning('BGG search failed: '.$e->getMessage());

            return ['results' => $results, 'bgg' => self::BGG_UNAVAILABLE];
        }
        foreach ($items as $item) {
            $this->remember($item);
            if (!isset($seen[$item->id])) {
                $results[] = ['source' => 'bgg', 'name' => $item->name, 'year' => $item->year, 'bggId' => $item->id, 'gameId' => null];
                $seen[$item->id] = true;
            }
        }
        $this->em->flush();

        return ['results' => $results, 'bgg' => self::BGG_USED];
    }

    /**
     * Připraví hru z BGG pro předvyplnění formuláře: jméno a adresa originálního obrázku na BGG CDN (ten se ukáže
     * v náhledu a uživatel ho ořízne v prohlížeči; lokálně se uloží až výřez, ADR 0021). Je-li hra i s adresou obrázku
     * v cache, BGG se nevolá vůbec.
     *
     * @return array{bggId: int, name: string, bggImageUrl: string|null}
     */
    public function prepare(int $bggId): array
    {
        $cached = $this->cache->findOneBy(['bggId' => $bggId]);
        if (null !== $cached && null !== $cached->getImageUrl()) {
            return ['bggId' => $bggId, 'name' => $cached->getName(), 'bggImageUrl' => $cached->getImageUrl()];
        }

        try {
            $item = $this->client->thing($bggId);
        } catch (BggUnavailableException $e) {
            if (null !== $cached) { // jméno máme, jen bez obrázku; formulář se dá vyplnit i tak
                $this->logger->warning('BGG thing failed: '.$e->getMessage());

                return ['bggId' => $bggId, 'name' => $cached->getName(), 'bggImageUrl' => null];
            }
            throw $e;
        }
        if (null === $item) {
            throw new DomainException('Hra na BoardGameGeek neexistuje.');
        }

        $entry = $this->remember($item);
        $entry->setImageUrl($item->imageUrl);
        $this->em->flush();

        return ['bggId' => $bggId, 'name' => $entry->getName(), 'bggImageUrl' => $entry->getImageUrl()];
    }

    private function remember(BggItem $item): BggGame
    {
        $entry = $this->cache->findOneBy(['bggId' => $item->id]);
        if (null === $entry) {
            $entry = new BggGame($item->id, $item->name, $item->year);
            $this->em->persist($entry);
        } else {
            $entry->refresh($item->name, $item->year);
        }

        return $entry;
    }
}
