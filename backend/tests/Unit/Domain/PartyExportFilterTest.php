<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain;

use App\Domain\Exception\DomainException;
use App\Domain\PartyExportFilter;
use PHPUnit\Framework\TestCase;

final class PartyExportFilterTest extends TestCase
{
    public function testEmptyQueryMeansEverything(): void
    {
        $filter = PartyExportFilter::fromQuery(['from' => '', 'toSession' => '']);

        self::assertFalse($filter->isDateRange());
        self::assertFalse($filter->isSessionRange());
        self::assertSame(['type' => 'all'], $filter->toArray());
    }

    public function testDateRangeMayBeOpenOnOneSide(): void
    {
        $filter = PartyExportFilter::fromQuery(['from' => '2026-03-01T00:00:00+01:00']);

        self::assertTrue($filter->isDateRange());
        self::assertSame(['type' => 'date', 'from' => '2026-03-01T00:00:00+01:00', 'to' => null], $filter->toArray());
    }

    public function testSessionRangeIsParsedToNumbers(): void
    {
        $filter = PartyExportFilter::fromQuery(['fromSession' => '12', 'toSession' => '12']);

        self::assertSame(12, $filter->fromSession);
        self::assertSame(['type' => 'session', 'from' => 12, 'to' => 12], $filter->toArray());
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidQueries(): iterable
    {
        yield 'date and session range together' => [['from' => '2026-03-01T00:00:00+01:00', 'toSession' => '3']];
        yield 'date range backwards' => [['from' => '2026-03-02T00:00:00+01:00', 'to' => '2026-03-01T00:00:00+01:00']];
        yield 'empty date range' => [['from' => '2026-03-01T00:00:00+01:00', 'to' => '2026-03-01T00:00:00+01:00']];
        yield 'session range backwards' => [['fromSession' => '5', 'toSession' => '4']];
        yield 'bad date' => [['from' => 'yesterday-ish']];
        yield 'date as array' => [['from' => ['2026-03-01']]];
        yield 'negative session' => [['fromSession' => '-1']];
        yield 'session not a number' => [['toSession' => 'x']];
    }

    /** @param array<string, mixed> $query */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidQueries')]
    public function testInvalidQueriesAreRejected(array $query): void
    {
        $this->expectException(DomainException::class);
        PartyExportFilter::fromQuery($query);
    }
}
