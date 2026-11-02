<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\PartyExportController;
use App\Domain\Exception\DomainException;
use App\Domain\PartyExportFilter;
use App\Entity\Party;
use App\Entity\User;
use App\Enum\PartyRole;
use App\Service\PartyExporter;
use App\Service\Presenter;
use App\Tests\Support\ControllerHarness;
use App\Tests\Support\Fixtures;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class PartyExportControllerTest extends TestCase
{
    private User $anna;
    private User $bob;
    private User $eve;
    private Party $party;

    protected function setUp(): void
    {
        $this->anna = Fixtures::user('Anna');
        $this->bob = Fixtures::user('Bob');
        $this->eve = Fixtures::user('Eve');
        $this->party = Fixtures::party($this->anna);
        Fixtures::member($this->party, $this->bob, PartyRole::Writer);
    }

    private function call(User $user, array $query = [], ?PartyExporter $exporter = null): JsonResponse
    {
        $exporter ??= $this->createStub(PartyExporter::class);

        return ControllerHarness::as($user)->call(new PartyExportController(new Presenter(), $exporter), '__invoke', ['party' => $this->party, 'request' => new Request($query)]);
    }

    public function testAdminDownloadsTheExportAsAJsonAttachment(): void
    {
        $exporter = $this->createMock(PartyExporter::class);
        $exporter->expects(self::once())->method('export')
            ->with($this->party, new PartyExportFilter(), self::isInstanceOf(\DateTimeImmutable::class))
            ->willReturn(['meta' => ['format' => 'x'], 'players' => [['id' => 1, 'name' => 'Čeněk']]]);

        $response = $this->call($this->anna, exporter: $exporter);

        self::assertMatchesRegularExpression('/^attachment; filename=hesk-export-\d{4}-\d{2}-\d{2}\.json$/', (string) $response->headers->get('Content-Disposition'));
        self::assertStringContainsString('"name": "Čeněk"', (string) $response->getContent()); // pretty-printed, unicode kept
        self::assertSame(['meta' => ['format' => 'x'], 'players' => [['id' => 1, 'name' => 'Čeněk']]], ControllerHarness::json($response));
    }

    public function testQueryParametersBecomeTheFilter(): void
    {
        $exporter = $this->createMock(PartyExporter::class);
        $exporter->expects(self::once())->method('export')
            ->with($this->party, new PartyExportFilter(fromSession: 3, toSession: 5), self::anything())
            ->willReturn([]);

        $this->call($this->anna, ['fromSession' => '3', 'toSession' => '5'], $exporter);
    }

    public function testInvalidFilterIsRejected(): void
    {
        $this->expectException(DomainException::class);
        $this->call($this->anna, ['from' => '2026-03-01T00:00:00+01:00', 'fromSession' => '1']);
    }

    public function testOnlyAdminsMayExport(): void
    {
        foreach ([$this->bob, $this->eve] as $user) {
            try {
                $this->call($user);
                self::fail('Expected access denied.');
            } catch (AccessDeniedException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
