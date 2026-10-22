<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Bgg;

use App\Domain\Exception\DomainException;
use App\Entity\BggGame;
use App\Entity\Game;
use App\Entity\User;
use App\Enum\ScoreType;
use App\Repository\BggGameRepository;
use App\Repository\GameRepository;
use App\Service\Bgg\BggCatalog;
use App\Service\Bgg\BggItem;
use App\Service\Bgg\BggUnavailableException;
use App\Tests\Support\DoctrineMocks;
use App\Tests\Support\FakeBggClient;
use App\Tests\Support\Fixtures;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/** Hledání her: hry party → cache BGG → živé API. Databáze (repozitáře, EntityManager) je mock, BGG je `FakeBggClient`. */
final class BggCatalogTest extends TestCase
{
    use DoctrineMocks;

    private \App\Entity\Party $party;
    /** @var list<Game> hry party, které by našel repozitář */
    private array $partyGames = [];
    /** @var list<BggGame> cache BGG „v databázi“ */
    private array $cached = [];
    private \ArrayObject $calls;

    protected function setUp(): void
    {
        FakeBggClient::reset();
        $this->party = Fixtures::party(Fixtures::user('Anna'));
        $this->partyGames = [];
        $this->cached = [];
        $this->calls = new \ArrayObject();
    }

    private function catalog(): BggCatalog
    {
        $games = $this->createStub(GameRepository::class);
        $games->method('search')->willReturnCallback(fn (mixed $party, ?string $q): array => array_values(array_filter(
            $this->partyGames,
            static fn (Game $g): bool => str_contains(mb_strtolower($g->getName()), mb_strtolower((string) $q)),
        )));

        // cache = to, co je v „databázi“, plus to, co služba v průběhu testu uložila
        $all = fn (): array => [...$this->cached, ...array_filter($this->calls['persist'] ?? [], static fn (object $e): bool => $e instanceof BggGame)];
        $cache = $this->createStub(BggGameRepository::class);
        $cache->method('searchByName')->willReturnCallback(function (string $q, int $limit) use ($all): array {
            $hits = array_filter($all(), static fn (BggGame $b): bool => str_contains(mb_strtolower($b->getName()), mb_strtolower($q)));
            usort($hits, static fn (BggGame $a, BggGame $b): int => $a->getName() <=> $b->getName());

            return \array_slice(array_values($hits), 0, $limit);
        });
        $cache->method('findOneBy')->willReturnCallback(function (array $criteria) use ($all): ?BggGame {
            foreach ($all() as $entry) {
                if ($entry->getBggId() === $criteria['bggId']) {
                    return $entry;
                }
            }

            return null;
        });

        return new BggCatalog($games, $cache, new FakeBggClient(), $this->mockEm([], $this->calls), new NullLogger());
    }

    /** @return list<array{string, string}> */
    private static function brief(array $payload): array
    {
        return array_map(static fn (array $r): array => [$r['source'], $r['name']], $payload['results']);
    }

    public function testSearchGoesPartyThenCacheThenBggAndRemembersBggResults(): void
    {
        FakeBggClient::$games = [
            13 => new BggItem(13, 'Catan', 1995, 'https://cf.geekdo-images.com/catan.jpg'),
            14 => new BggItem(14, 'Catan: Seafarers', 1997),
        ];
        $own = Fixtures::game($this->party, 'Catan vlastní');
        $this->partyGames = [$own];

        $first = $this->catalog()->search($this->party, 'catan');
        self::assertSame([['party', 'Catan vlastní'], ['bgg', 'Catan'], ['bgg', 'Catan: Seafarers']], self::brief($first));
        self::assertSame($own->getId(), $first['results'][0]['gameId']);
        self::assertSame([13, 1995], [$first['results'][1]['bggId'], $first['results'][1]['year']]);
        self::assertSame('used', $first['bgg']);
        self::assertSame(1, FakeBggClient::$searchCalls);
        self::assertCount(2, array_filter($this->calls['persist'], static fn (object $e): bool => $e instanceof BggGame), 'výsledky z API se uloží do cache');

        // živé API padne: hry party a cache zůstanou, odpověď to jen oznámí
        FakeBggClient::$down = true;
        $second = $this->catalog()->search($this->party, 'catan');
        self::assertSame([['party', 'Catan vlastní'], ['cache', 'Catan'], ['cache', 'Catan: Seafarers']], self::brief($second));
        self::assertSame('unavailable', $second['bgg']);
    }

    public function testBggIsNotCalledWhenLocalSourcesAreEnough(): void
    {
        foreach (range(1, 5) as $i) {
            $this->cached[] = new BggGame($i, "Dixit $i", 2008);
        }

        $result = $this->catalog()->search($this->party, 'dixit');

        self::assertSame(5, \count($result['results']));
        self::assertSame(['skipped', 0], [$result['bgg'], FakeBggClient::$searchCalls], 'pět výsledků z cache stačí, API se nevolá');
    }

    public function testGameAlreadyInThePartyIsNotOfferedTwice(): void
    {
        FakeBggClient::$games = [13 => new BggItem(13, 'Catan', 1995)];
        $game = Fixtures::game($this->party, 'Catan');
        $game->update('Catan', null, ScoreType::MostPoints, null, null, null, [], bggId: 13);
        $this->partyGames = [$game];
        $this->cached = [new BggGame(13, 'Catan', 1995)];

        self::assertSame([['party', 'Catan']], self::brief($this->catalog()->search($this->party, 'catan')));
    }

    public function testPrepareReturnsTheCdnImageAndThenServesItFromTheCache(): void
    {
        $cdn = 'https://cf.geekdo-images.com/catan.jpg';
        FakeBggClient::$games = [13 => new BggItem(13, 'Catan', 1995, $cdn)];
        $this->calls['persist'] = [];

        $prepared = $this->catalog()->prepare(13);
        self::assertSame(['bggId' => 13, 'name' => 'Catan', 'bggImageUrl' => $cdn], $prepared);
        self::assertSame([1, 0], [FakeBggClient::$thingCalls, FakeBggClient::$imageCalls], 'server obrázek nestahuje, ukáže se rovnou z CDN');

        self::assertSame($prepared, $this->catalog()->prepare(13));
        self::assertSame(1, FakeBggClient::$thingCalls, 'podruhé je adresa v cache a BGG se nevolá');
    }

    public function testPrepareWithoutImageStillPrefillsTheName(): void
    {
        FakeBggClient::$games = [7 => new BggItem(7, 'Bez obrázku', 2001)];

        self::assertSame(['bggId' => 7, 'name' => 'Bez obrázku', 'bggImageUrl' => null], $this->catalog()->prepare(7));
    }

    public function testPrepareOfAnUnknownGameIsADomainError(): void
    {
        $this->expectException(DomainException::class);
        $this->catalog()->prepare(999);
    }

    public function testPrepareWhenBggIsDownFallsBackToTheCachedNameOrFails(): void
    {
        FakeBggClient::$down = true;
        $this->cached = [new BggGame(13, 'Catan', 1995)];

        self::assertSame(['bggId' => 13, 'name' => 'Catan', 'bggImageUrl' => null], $this->catalog()->prepare(13));

        $this->expectException(BggUnavailableException::class);
        $this->catalog()->prepare(14);
    }
}
