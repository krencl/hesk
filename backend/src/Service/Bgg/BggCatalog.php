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
 * Hledání hry pro přidání do party (ADR 0021, 0023). Zdroje podle priority: 1) hry party, 2) ostatní hry z katalogu
 * (stejná databáze; zařadí se do party bez zakládání nové hry), 3) lokální cache BGG, 4) živé BGG API – to se volá, jen
 * když první tři zdroje dohromady nedají aspoň `LOCAL_ENOUGH` výsledků, a co vrátí, se uloží do cache.
 */
class BggCatalog
{
    public const LOCAL_ENOUGH = 5;
    public const PARTY_LIMIT = 5;
    public const CATALOG_LIMIT = 5;

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
     * @return array{results: list<array{source: string, name: string, year: int|null, bggId: int|null, gameId: int|null, imageUrl: string|null}>, bgg: string}
     *                                                                                                                                 `source` je `party` (hra už v partě), `catalog` (hra z katalogu, kterou party nemá; stačí ji zařadit podle `gameId`), `cache` nebo `bgg`; `bgg` říká, jestli se živé API použilo
     */
    public function search(Party $party, string $query): array
    {
        $results = [];
        $seen = [];

        foreach ($this->games->search($party, $query, GameRepository::SORT_NAME, null, self::PARTY_LIMIT, 0) as $game) {
            $results[] = ['source' => 'party', 'name' => $game->getName(), 'year' => null, 'bggId' => $game->getBggId(), 'gameId' => $game->getId(), 'imageUrl' => $game->getImageUrl()];
            if (null !== $game->getBggId()) {
                $seen[$game->getBggId()] = true;
            }
        }
        foreach ($this->games->searchOutsideParty($party, $query, self::CATALOG_LIMIT) as $game) {
            $results[] = ['source' => 'catalog', 'name' => $game->getName(), 'year' => null, 'bggId' => $game->getBggId(), 'gameId' => $game->getId(), 'imageUrl' => $game->getImageUrl()];
            if (null !== $game->getBggId()) {
                $seen[$game->getBggId()] = true;
            }
        }
        foreach ($this->cache->searchByName($query, BggClient::MAX_RESULTS) as $cached) {
            if (!isset($seen[$cached->getBggId()])) {
                $results[] = ['source' => 'cache', 'name' => $cached->getName(), 'year' => $cached->getYear(), 'bggId' => $cached->getBggId(), 'gameId' => null, 'imageUrl' => $cached->getImageUrl()];
                $seen[$cached->getBggId()] = true;
            }
        }

        if (\count($results) >= self::LOCAL_ENOUGH) {
            return ['results' => self::sorted($results), 'bgg' => self::BGG_SKIPPED];
        }

        try {
            $items = $this->client->search($query);
        } catch (BggUnavailableException $e) {
            $this->logger->warning('BGG search failed: '.$e->getMessage());

            return ['results' => self::sorted($results), 'bgg' => self::BGG_UNAVAILABLE];
        }
        $this->rememberAll($items);
        foreach ($items as $item) {
            if (!isset($seen[$item->id])) {
                $results[] = ['source' => 'bgg', 'name' => $item->name, 'year' => $item->year, 'bggId' => $item->id, 'gameId' => null, 'imageUrl' => $item->imageUrl];
                $seen[$item->id] = true;
            }
        }
        $this->em->flush();

        return ['results' => self::sorted($results), 'bgg' => self::BGG_USED];
    }

    /**
     * Výsledky abecedně podle názvu bez ohledu na zdroj (česky, bez ohledu na velikost písmen a diakritiku, je-li
     * k dispozici `intl`); při shodě názvu podle roku a původního pořadí (hra party, katalog, cache, BGG).
     *
     * @template T of array{name: string, year: int|null}
     *
     * @param list<T> $results
     *
     * @return list<T>
     */
    private static function sorted(array $results): array
    {
        $collator = class_exists(\Collator::class) ? new \Collator('cs_CZ') : null;
        $collator?->setStrength(\Collator::SECONDARY);
        $indexed = array_map(null, array_keys($results), $results);
        usort($indexed, static function (array $a, array $b) use ($collator): int {
            [$ia, $ra] = $a;
            [$ib, $rb] = $b;
            $byName = $collator ? $collator->compare($ra['name'], $rb['name']) : strcmp(mb_strtolower($ra['name']), mb_strtolower($rb['name']));

            return $byName ?: ($ra['year'] ?? 0) <=> ($rb['year'] ?? 0) ?: $ia <=> $ib;
        });

        return array_column($indexed, 1);
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

        $entry = $this->rememberAll([$item])[$item->id];
        $entry->setImageUrl($item->imageUrl);
        $this->em->flush();

        return ['bggId' => $bggId, 'name' => $entry->getName(), 'bggImageUrl' => $entry->getImageUrl()];
    }

    /**
     * Uloží hry z BGG do cache: opakovaná položka se bere jednou (první vyhrává), existující řádky se načtou jedním
     * dotazem a jen obnoví, nové se založí.
     *
     * @param list<BggItem> $items
     *
     * @return array<int, BggGame> záznamy cache podle BGG id
     */
    private function rememberAll(array $items): array
    {
        $unique = [];
        foreach ($items as $item) {
            $unique[$item->id] ??= $item;
        }
        if ([] === $unique) {
            return [];
        }

        $entries = [];
        foreach ($this->cache->findBy(['bggId' => array_keys($unique)]) as $existing) {
            $entries[$existing->getBggId()] = $existing;
        }
        foreach ($unique as $id => $item) {
            if (isset($entries[$id])) {
                $entries[$id]->refresh($item->name, $item->year);
            } else {
                $this->em->persist($entries[$id] = new BggGame($id, $item->name, $item->year));
            }
        }

        return $entries;
    }
}
