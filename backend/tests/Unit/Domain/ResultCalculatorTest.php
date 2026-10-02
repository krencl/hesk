<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain;

use App\Domain\Exception\DomainException;
use App\Domain\PlayerInput;
use App\Domain\ResultCalculator;
use App\Enum\ScoreType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ResultCalculatorTest extends TestCase
{
    private ResultCalculator $calc;

    protected function setUp(): void
    {
        $this->calc = new ResultCalculator();
    }

    public function testMostPointsRanksDescending(): void
    {
        $out = $this->calc->calculate(ScoreType::MostPoints, [
            new PlayerInput(1, score: 10), new PlayerInput(2, score: 30), new PlayerInput(3, score: 20),
        ]);

        self::assertSame([3, 1, 2], array_map(static fn ($o) => $o->rank, $out));
        self::assertSame([false, true, false], array_map(static fn ($o) => $o->winner, $out));
    }

    public function testFewestPointsRanksAscending(): void
    {
        $out = $this->calc->calculate(ScoreType::FewestPoints, [
            new PlayerInput(1, score: 10), new PlayerInput(2, score: 30),
        ]);

        self::assertTrue($out[0]->winner);
        self::assertSame(2, $out[1]->rank);
    }

    public function testTiesShareRankAndWin(): void
    {
        $out = $this->calc->calculate(ScoreType::MostPoints, [
            new PlayerInput(1, score: 50), new PlayerInput(2, score: 50), new PlayerInput(3, score: 10),
        ]);

        self::assertSame([1, 1, 3], array_map(static fn ($o) => $o->rank, $out));
        self::assertSame([true, true, false], array_map(static fn ($o) => $o->winner, $out));
    }

    public function testRankOrderUsesGivenRanks(): void
    {
        $out = $this->calc->calculate(ScoreType::RankOrder, [
            new PlayerInput(1, rank: 2), new PlayerInput(2, rank: 1),
        ]);

        self::assertFalse($out[0]->winner);
        self::assertTrue($out[1]->winner);
        self::assertNull($out[1]->score);
    }

    public function testWinnerOnlyAllowsMultipleWinners(): void
    {
        $out = $this->calc->calculate(ScoreType::WinnerOnly, [
            new PlayerInput(1, winner: true), new PlayerInput(2, winner: true), new PlayerInput(3, winner: false),
        ]);

        self::assertSame([true, true, false], array_map(static fn ($o) => $o->winner, $out));
        self::assertSame([1, 1, 2], array_map(static fn ($o) => $o->rank, $out));
    }

    /** @return iterable<string, array{ScoreType, list<PlayerInput>}> */
    public static function invalidInputs(): iterable
    {
        yield 'no players' => [ScoreType::MostPoints, []];
        yield 'duplicate player' => [ScoreType::MostPoints, [new PlayerInput(1, score: 1), new PlayerInput(1, score: 2)]];
        yield 'missing score' => [ScoreType::MostPoints, [new PlayerInput(1)]];
        yield 'missing rank' => [ScoreType::RankOrder, [new PlayerInput(1)]];
        yield 'rank zero' => [ScoreType::RankOrder, [new PlayerInput(1, rank: 0)]];
        yield 'nobody first' => [ScoreType::RankOrder, [new PlayerInput(1, rank: 2)]];
        yield 'no winner' => [ScoreType::WinnerOnly, [new PlayerInput(1, winner: false)]];
    }

    /** @param list<PlayerInput> $inputs */
    #[DataProvider('invalidInputs')]
    public function testInvalidInputIsRejected(ScoreType $type, array $inputs): void
    {
        $this->expectException(DomainException::class);
        $this->calc->calculate($type, $inputs);
    }
}
