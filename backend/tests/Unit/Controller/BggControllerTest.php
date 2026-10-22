<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\BggController;
use App\Dto\BggImportRequest;
use App\Entity\Party;
use App\Entity\User;
use App\Service\Bgg\BggCatalog;
use App\Service\Bgg\BggClient;
use App\Service\Bgg\BggUnavailableException;
use App\Service\Presenter;
use App\Tests\Support\ControllerHarness;
use App\Tests\Support\Fixtures;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/** Hledání a převzetí hry z BGG: katalog i klient jsou mocky. */
final class BggControllerTest extends TestCase
{
    private User $anna;
    private User $eve;
    private Party $party;

    protected function setUp(): void
    {
        $this->anna = Fixtures::user('Anna');
        $this->eve = Fixtures::user('Eve');
        $this->party = Fixtures::party($this->anna);
    }

    private function controller(?BggCatalog $catalog = null, ?BggClient $client = null): BggController
    {
        return new BggController(new Presenter(), $catalog ?? $this->createStub(BggCatalog::class), $client ?? $this->createStub(BggClient::class));
    }

    public function testSearchPassesTheTrimmedQueryToTheCatalog(): void
    {
        $result = ['results' => [['source' => 'bgg', 'name' => 'Catan', 'year' => 1995, 'bggId' => 13, 'gameId' => null]], 'bgg' => 'used'];
        $catalog = $this->createMock(BggCatalog::class);
        $catalog->expects(self::once())->method('search')->with($this->party, 'catan')->willReturn($result);

        $response = ControllerHarness::as($this->anna)->call($this->controller($catalog), 'search', ['party' => $this->party, 'request' => new Request(['q' => '  catan '])]);

        self::assertSame($result, ControllerHarness::json($response));
    }

    public function testShortQueryReturnsNothingWithoutCallingTheCatalog(): void
    {
        $catalog = $this->createMock(BggCatalog::class);
        $catalog->expects(self::never())->method('search');

        foreach ([['q' => 'c'], ['q' => '  '], []] as $query) {
            $response = ControllerHarness::as($this->anna)->call($this->controller($catalog), 'search', ['party' => $this->party, 'request' => new Request($query)]);
            self::assertSame(['results' => [], 'bgg' => 'skipped'], ControllerHarness::json($response));
        }
    }

    public function testImportReturnsThePreparedGame(): void
    {
        $prepared = ['bggId' => 13, 'name' => 'Catan', 'bggImageUrl' => 'https://cf.geekdo-images.com/catan.jpg'];
        $catalog = $this->createMock(BggCatalog::class);
        $catalog->expects(self::once())->method('prepare')->with(13)->willReturn($prepared);

        $response = ControllerHarness::as($this->anna)->call($this->controller($catalog), 'import', ['party' => $this->party, 'req' => new BggImportRequest(13)]);

        self::assertSame($prepared, ControllerHarness::json($response));
    }

    public function testImportTurnsUnavailableBggInto503(): void
    {
        $catalog = $this->createStub(BggCatalog::class);
        $catalog->method('prepare')->willThrowException(new BggUnavailableException('down'));

        $this->expectException(ServiceUnavailableHttpException::class);
        ControllerHarness::as($this->anna)->call($this->controller($catalog), 'import', ['party' => $this->party, 'req' => new BggImportRequest(13)]);
    }

    public function testOnlyPartyMembersCanSearchOrImport(): void
    {
        $harness = ControllerHarness::as($this->eve);
        foreach ([['search', ['party' => $this->party, 'request' => new Request(['q' => 'catan'])]], ['import', ['party' => $this->party, 'req' => new BggImportRequest(13)]]] as [$action, $args]) {
            try {
                $harness->call($this->controller(), $action, $args);
                self::fail("Cizí uživatel nesmí volat $action.");
            } catch (AccessDeniedException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testImageProxyServesTheCdnImageThroughOurOrigin(): void
    {
        $url = 'https://cf.geekdo-images.com/catan.jpg';
        $client = $this->createMock(BggClient::class);
        $client->expects(self::once())->method('fetchImage')->with($url)->willReturn(['content' => 'fake-image', 'type' => 'image/jpeg']);

        $response = ControllerHarness::as($this->anna)->call($this->controller(client: $client), 'image', ['request' => new Request(['url' => $url])]);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['image/jpeg', 'fake-image', 'nosniff'], [$response->headers->get('Content-Type'), $response->getContent(), $response->headers->get('X-Content-Type-Options')]);
        self::assertStringContainsString('max-age=86400', (string) $response->headers->get('Cache-Control'));
    }

    public function testImageProxyTurnsUnavailableBggInto503(): void
    {
        $client = $this->createStub(BggClient::class);
        $client->method('fetchImage')->willThrowException(new BggUnavailableException('down'));

        $this->expectException(ServiceUnavailableHttpException::class);
        ControllerHarness::as($this->anna)->call($this->controller(client: $client), 'image', ['request' => new Request(['url' => 'https://cf.geekdo-images.com/catan.jpg'])]);
    }
}
