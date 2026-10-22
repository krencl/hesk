<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Service\Bgg\BggItem;
use App\Tests\Support\FakeBggClient;
use App\Tests\Support\TestDatabase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Hledání hry pro založení: hry party → cache BGG → živé API (ADR 0021). */
final class BggFlowTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        FakeBggClient::reset();
        $this->client = static::createClient();
        TestDatabase::clear(static::getContainer());
    }

    /** @return array<mixed> */
    private function api(string $method, string $uri, ?array $body = null, int $expected = 200): array
    {
        $this->client->request($method, $uri, server: ['CONTENT_TYPE' => 'application/json'], content: null === $body ? null : json_encode($body, \JSON_THROW_ON_ERROR));
        self::assertSame($expected, $this->client->getResponse()->getStatusCode(), $this->client->getResponse()->getContent());
        $content = $this->client->getResponse()->getContent();

        return $content ? json_decode($content, true, flags: \JSON_THROW_ON_ERROR) : [];
    }

    private function login(string $email, string $nick): void
    {
        $this->client->getCookieJar()->clear();
        $this->api('POST', '/api/auth/dev-login', ['email' => $email, 'nickname' => $nick]);
    }

    /** @return array{0: array<string, mixed>, 1: string} */
    private function party(): array
    {
        $this->login('anna@example.com', 'Anna');
        $party = $this->api('POST', '/api/parties', ['name' => 'Pátek'], 201);

        return [$party, "/api/parties/{$party['id']}/bgg"];
    }

    /** @param list<array{string, string}> $results zkrácené výsledky: [zdroj, název] */
    private static function brief(array $payload): array
    {
        return array_map(static fn (array $r): array => [$r['source'], $r['name']], $payload['results']);
    }

    public function testSearchGoesPartyThenCacheThenBgg(): void
    {
        [$party, $bgg] = $this->party();
        FakeBggClient::$games = [
            13 => new BggItem(13, 'Catan', 1995, 'https://cf.geekdo-images.com/catan.jpg'),
            14 => new BggItem(14, 'Catan: Seafarers', 1997),
        ];
        $own = $this->api('POST', "/api/parties/{$party['id']}/games", ['name' => 'Catan vlastní', 'scoreType' => 'most_points'], 201);

        // nejdřív hra party, pak (prázdná) cache, nakonec živé API; výsledky z API se uloží do cache
        $first = $this->api('GET', "$bgg/search?q=catan");
        self::assertSame([['party', 'Catan vlastní'], ['bgg', 'Catan'], ['bgg', 'Catan: Seafarers']], self::brief($first));
        self::assertSame($own['id'], $first['results'][0]['gameId']);
        self::assertSame(['bggId' => 13, 'year' => 1995], ['bggId' => $first['results'][1]['bggId'], 'year' => $first['results'][1]['year']]);
        self::assertSame('used', $first['bgg']);
        self::assertSame(1, FakeBggClient::$searchCalls);

        // živé API padne: výsledky z party a z cache zůstanou, odpověď to jen oznámí
        FakeBggClient::$down = true;
        $second = $this->api('GET', "$bgg/search?q=catan");
        self::assertSame([['party', 'Catan vlastní'], ['cache', 'Catan'], ['cache', 'Catan: Seafarers']], self::brief($second));
        self::assertSame('unavailable', $second['bgg']);
    }

    public function testBggIsNotCalledWhenLocalSourcesAreEnough(): void
    {
        [$party, $bgg] = $this->party();
        FakeBggClient::$games = [];
        foreach (range(1, 5) as $i) {
            FakeBggClient::$games[$i] = new BggItem($i, "Dixit $i", 2008);
        }
        $this->api('GET', "$bgg/search?q=dixit");
        self::assertSame(1, FakeBggClient::$searchCalls);

        $again = $this->api('GET', "$bgg/search?q=dixit");
        self::assertSame(5, \count($again['results']));
        self::assertSame(['skipped', 1], [$again['bgg'], FakeBggClient::$searchCalls], 'pět výsledků z cache stačí, API se znovu nevolá');
    }

    public function testGameAlreadyInThePartyIsNotOfferedTwice(): void
    {
        [$party, $bgg] = $this->party();
        FakeBggClient::$games = [13 => new BggItem(13, 'Catan', 1995)];
        $this->api('POST', "/api/parties/{$party['id']}/games", ['name' => 'Catan', 'scoreType' => 'most_points', 'bggId' => 13], 201);

        // stejné bggId v partě i v API/cache se nabídne jednou, jako hra party
        self::assertSame([['party', 'Catan']], self::brief($this->api('GET', "$bgg/search?q=catan")));
        self::assertSame([['party', 'Catan']], self::brief($this->api('GET', "$bgg/search?q=catan")));
    }

    public function testShortQueryReturnsNothingWithoutCallingBgg(): void
    {
        [, $bgg] = $this->party();
        self::assertSame(['results' => [], 'bgg' => 'skipped'], $this->api('GET', "$bgg/search?q=c"));
        self::assertSame(0, FakeBggClient::$searchCalls);
    }

    public function testImportReturnsTheCdnImageAndCachesIt(): void
    {
        [, $bgg] = $this->party();
        $cdn = 'https://cf.geekdo-images.com/catan.jpg';
        FakeBggClient::$games = [13 => new BggItem(13, 'Catan', 1995, $cdn)];

        $prepared = $this->api('POST', "$bgg/import", ['bggId' => 13]);
        self::assertSame(['bggId' => 13, 'name' => 'Catan', 'bggImageUrl' => $cdn], $prepared);
        self::assertSame(1, FakeBggClient::$thingCalls);
        self::assertSame(0, FakeBggClient::$imageCalls, 'server obrázek nestahuje, ukáže se rovnou z CDN');

        // podruhé je adresa v cache: BGG se nevolá
        self::assertSame($prepared, $this->api('POST', "$bgg/import", ['bggId' => 13]));
        self::assertSame(1, FakeBggClient::$thingCalls);
    }

    public function testImportWithoutImageStillPrefillsTheName(): void
    {
        [, $bgg] = $this->party();
        FakeBggClient::$games = [7 => new BggItem(7, 'Bez obrázku', 2001)];
        self::assertSame(['bggId' => 7, 'name' => 'Bez obrázku', 'bggImageUrl' => null], $this->api('POST', "$bgg/import", ['bggId' => 7]));
    }

    public function testImageProxyServesTheCdnImageThroughOurOrigin(): void
    {
        $this->party();
        $this->client->request('GET', '/api/bgg/image?url='.rawurlencode('https://cf.geekdo-images.com/catan.jpg'));
        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['image/jpeg', 'fake-image'], [$response->headers->get('Content-Type'), $response->getContent()]);

        FakeBggClient::$down = true;
        $this->api('GET', '/api/bgg/image?url='.rawurlencode('https://cf.geekdo-images.com/catan.jpg'), expected: 503);
    }

    public function testImageProxyRequiresLogin(): void
    {
        $this->client->request('GET', '/api/bgg/image?url='.rawurlencode('https://cf.geekdo-images.com/catan.jpg'));
        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testImportErrors(): void
    {
        [, $bgg] = $this->party();
        $this->api('POST', "$bgg/import", ['bggId' => 999], 422); // na BGG není
        $this->api('POST', "$bgg/import", ['bggId' => 0], 422);
        FakeBggClient::$games = [13 => new BggItem(13, 'Catan', 1995)];
        FakeBggClient::$down = true;
        $this->api('POST', "$bgg/import", ['bggId' => 13], 503);
    }

    public function testOnlyPartyMembersCanSearch(): void
    {
        [, $bgg] = $this->party();
        $this->login('eve@example.com', 'Eve');
        $this->api('GET', "$bgg/search?q=catan", expected: 403);
        $this->api('POST', "$bgg/import", ['bggId' => 13], 403);
    }

    public function testGameKeepsItsBggId(): void
    {
        [$party] = $this->party();
        $game = $this->api('POST', "/api/parties/{$party['id']}/games", ['name' => 'Catan', 'scoreType' => 'most_points', 'bggId' => 13], 201);
        self::assertSame(13, $game['bggId']);
        self::assertSame(13, $this->api('PUT', "/api/games/{$game['id']}", ['name' => 'Catan', 'scoreType' => 'most_points', 'bggId' => 13])['bggId']);
        self::assertNull($this->api('POST', "/api/parties/{$party['id']}/games", ['name' => 'Vlastní', 'scoreType' => 'most_points'], 201)['bggId']);
        $this->api('POST', "/api/parties/{$party['id']}/games", ['name' => 'Zlá', 'scoreType' => 'most_points', 'bggId' => -1], 422);
    }
}
