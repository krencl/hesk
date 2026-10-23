<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Bgg;

use App\Service\Bgg\BggClient;
use App\Service\Bgg\BggUnavailableException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class BggClientTest extends TestCase
{
    private const SEARCH = <<<'XML'
        <?xml version="1.0" encoding="utf-8"?>
        <items total="4" termsofuse="https://boardgamegeek.com/xmlapi/termsofuse">
          <item type="boardgame" id="1"><name type="primary" value="Big Catan Party"/><yearpublished value="2001"/></item>
          <item type="boardgame" id="2"><name type="alternate" value="Alt only"/></item>
          <item type="boardgame" id="13"><name type="primary" value="Catan"/><yearpublished value="1995"/></item>
          <item type="boardgame" id="3"><name type="primary" value="Catanish"/></item>
        </items>
        XML;

    /** @param list<MockResponse> $responses */
    private function client(array $responses, ?string $token = 'secret', ?\ArrayObject $requests = null): BggClient
    {
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$responses, $requests): MockResponse {
            $requests?->append([$url, $options['normalized_headers']['authorization'][0] ?? null]);

            return array_shift($responses) ?? new MockResponse('', ['http_code' => 500]);
        });

        return new BggClient($http, $token);
    }

    public function testSearchParsesPrimaryNamesAndRanksExactThenPrefix(): void
    {
        $requests = new \ArrayObject();
        $items = $this->client([new MockResponse(self::SEARCH)], requests: $requests)->search('catan');

        self::assertSame(['Catan', 'Catanish', 'Big Catan Party'], array_map(static fn ($i) => $i->name, $items));
        self::assertSame([13, 1995], [$items[0]->id, $items[0]->year]);
        self::assertNull($items[1]->year, 'rok je nepovinný');
        self::assertStringStartsWith('https://boardgamegeek.com/xmlapi2/search?query=catan&type=boardgame', $requests[0][0]);
        self::assertSame('Authorization: Bearer secret', $requests[0][1]);
    }

    public function testThingReturnsImageOrNull(): void
    {
        $xml = '<items><item type="boardgame" id="13"><image>https://cf.geekdo-images.com/a/catan.jpg</image><name type="primary" value="Catan"/><name type="alternate" value="Siedler"/><yearpublished value="1995"/></item></items>';
        $item = $this->client([new MockResponse($xml), new MockResponse('<items/>')])->thing(13);
        self::assertSame(['Catan', 'https://cf.geekdo-images.com/a/catan.jpg'], [$item->name, $item->imageUrl]);
        self::assertNull($this->client([new MockResponse('<items/>')])->thing(99));
    }

    public function testWithoutTokenNothingIsCalled(): void
    {
        $requests = new \ArrayObject();
        foreach ([null, '', '  '] as $token) {
            $client = $this->client([], $token, $requests);
            self::assertFalse($client->isConfigured());
            try {
                $client->search('catan');
                self::fail('má vyhodit výjimku');
            } catch (BggUnavailableException) {
            }
        }
        self::assertCount(0, $requests);
    }

    public function testErrorsBecomeUnavailable(): void
    {
        foreach ([401, 403, 429, 500, 202] as $status) {
            try {
                $this->client([new MockResponse('', ['http_code' => $status])])->search('catan');
                self::fail("stav $status má vyhodit výjimku");
            } catch (BggUnavailableException) {
            }
        }
        $this->expectException(BggUnavailableException::class);
        $this->client([new MockResponse('<html>not xml')])->search('catan');
    }

    public function testImageIsFetchedOnlyFromTheBggCdnAndTypedByContent(): void
    {
        $png = (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $client = $this->client([new MockResponse($png, ['response_headers' => ['content-type' => 'text/html']]), new MockResponse('<html>nope</html>')]);
        self::assertSame('image/png', $client->fetchImage('https://cf.geekdo-images.com/a/b.jpg')['type']);

        try {
            $client->fetchImage('https://cf.geekdo-images.com/a/c.jpg');
            self::fail('HTML není obrázek');
        } catch (BggUnavailableException) {
        }

        foreach (['http://cf.geekdo-images.com/a.jpg', 'https://evil.example/a.jpg', 'https://geekdo-images.com.evil.example/a.jpg', 'https://127.0.0.1/a.jpg', 'https://user@cf.geekdo-images.com/a.jpg', 'https://cf.geekdo-images.com:8443/a.jpg', 'file:///etc/passwd', ''] as $url) {
            try {
                $client->fetchImage($url);
                self::fail("$url nesmí projít");
            } catch (BggUnavailableException) {
            }
        }
    }
}
