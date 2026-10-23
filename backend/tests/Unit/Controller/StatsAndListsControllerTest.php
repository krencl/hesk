<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\SessionController;
use App\Controller\StatsController;
use App\Controller\TagController;
use App\Entity\GameSession;
use App\Entity\Party;
use App\Entity\Tag;
use App\Entity\User;
use App\Repository\GameSessionRepository;
use App\Repository\PlayRepository;
use App\Repository\StatsFilter;
use App\Service\Presenter;
use App\Tests\Support\ControllerHarness;
use App\Tests\Support\DoctrineMocks;
use App\Tests\Support\Fixtures;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/** Statistiky, seance a tagy party: data z repozitářů jsou namockovaná, ověřuje se výstup API a přístup. */
final class StatsAndListsControllerTest extends TestCase
{
    use DoctrineMocks;

    private User $anna;
    private User $eve;
    private Party $party;

    protected function setUp(): void
    {
        $this->anna = Fixtures::user('Anna');
        $this->eve = Fixtures::user('Eve');
        $this->party = Fixtures::party($this->anna);
    }

    public function testStatsReturnWinsTopGamesStartersAndPlayTagsFromTheRepository(): void
    {
        $wins = [['playerId' => 1, 'name' => 'Anna', 'wins' => 2, 'plays' => 3], ['playerId' => 2, 'name' => 'Bob', 'wins' => 1, 'plays' => 3]];
        $top = [['gameId' => 5, 'name' => 'King of Tokyo', 'shortName' => 'Tokyo', 'plays' => 3]];
        $starters = [['playerId' => 2, 'name' => 'Bob', 'starts' => 4]];
        $tags = [['name' => 'Skalka', 'plays' => 6]];
        $plays = $this->createMock(PlayRepository::class);
        $plays->expects(self::once())->method('winsPerPlayer')->with($this->party, new StatsFilter())->willReturn($wins);
        $plays->expects(self::once())->method('topGames')->with($this->party, 10, new StatsFilter())->willReturn($top);
        $plays->expects(self::once())->method('startingPerPlayer')->with($this->party, new StatsFilter())->willReturn($starters);
        $plays->expects(self::once())->method('topPlayTags')->with($this->party, 10, new StatsFilter())->willReturn($tags);

        $response = ControllerHarness::as($this->anna)->call(new StatsController(new Presenter(), $plays), '__invoke', ['party' => $this->party, 'request' => new Request()]);

        self::assertSame(['wins' => $wins, 'topGames' => $top, 'starters' => $starters, 'playTags' => $tags], ControllerHarness::json($response));
    }

    public function testStatsAreForMembersOnly(): void
    {
        $this->expectException(AccessDeniedException::class);
        ControllerHarness::as($this->eve)->call(new StatsController(new Presenter(), $this->createStub(PlayRepository::class)), '__invoke', ['party' => $this->party, 'request' => new Request()]);
    }

    public function testStatsCanBeFilteredByGamesAndGameTags(): void
    {
        $filter = new StatsFilter(['Strategie'], ['Rychlovka', 'Párty'], [4, 7], [9]);
        $plays = $this->createMock(PlayRepository::class);
        $plays->expects(self::once())->method('winsPerPlayer')->with($this->party, $filter)->willReturn([]);
        $plays->expects(self::once())->method('topGames')->with($this->party, 10, $filter)->willReturn([]);
        $plays->expects(self::once())->method('startingPerPlayer')->with($this->party, $filter)->willReturn([]);
        $plays->expects(self::once())->method('topPlayTags')->with($this->party, 10, $filter)->willReturn([]);
        $request = new Request(['tags' => ['Strategie', ' ', 'Strategie'], 'excludeTags' => ['Rychlovka', 'Párty'], 'games' => ['4', '7', 'x', '4'], 'excludeGames' => ['9']]);

        ControllerHarness::as($this->anna)->call(new StatsController(new Presenter(), $plays), '__invoke', ['party' => $this->party, 'request' => $request]);
    }

    public function testSessionsAreListedWithNumberAndBounds(): void
    {
        $d = static fn (string $s): \DateTimeImmutable => new \DateTimeImmutable($s);
        $sessions = $this->createMock(GameSessionRepository::class);
        $sessions->expects(self::once())->method('findBy')->with(['party' => $this->party], ['number' => 'DESC'])->willReturn([
            Fixtures::id(new GameSession($this->party, 3, $d('2026-04-10T18:00:00+00:00'), $d('2026-04-10T19:00:00+00:00')), 30),
            Fixtures::id(new GameSession($this->party, 1, $d('2026-03-01T18:00:00+00:00'), $d('2026-03-01T22:00:00+00:00')), 10),
        ]);

        $list = ControllerHarness::json(ControllerHarness::as($this->anna)->call(new SessionController(new Presenter(), $sessions), 'list', ['party' => $this->party]));

        self::assertSame([3, 1], array_column($list, 'number'));
        self::assertSame([30, 10], array_column($list, 'id'));
        self::assertSame('2026-03-01T22:00:00+00:00', $list[1]['endedAt']);
    }

    public function testSessionsAreForMembersOnly(): void
    {
        $this->expectException(AccessDeniedException::class);
        ControllerHarness::as($this->eve)->call(new SessionController(new Presenter(), $this->createStub(GameSessionRepository::class)), 'list', ['party' => $this->party]);
    }

    private function tagController(array $rows): TagController
    {
        $query = $this->mockQuery(['getArrayResult' => $rows]);
        $qb = $this->createStub(QueryBuilder::class);
        foreach (['select', 'from', 'where', 'setParameter'] as $fluent) {
            $qb->method($fluent)->willReturnSelf();
        }
        $qb->method('getQuery')->willReturn($query);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);

        return new TagController(new Presenter(), $em);
    }

    public function testTagsAreSortedCaseInsensitively(): void
    {
        $response = ControllerHarness::as($this->anna)->call($this->tagController([['name' => 'víkend'], ['name' => 'Liga'], ['name' => 'doma']]), 'list', ['party' => $this->party]);

        self::assertSame(['doma', 'Liga', 'víkend'], ControllerHarness::json($response));
    }

    public function testGameTagsOfThePartyAndOfTheCatalogAreSortedCaseInsensitively(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('createQuery')->willReturn($this->mockQuery(['getArrayResult' => [['name' => 'Strategie'], ['name' => 'karetní'], ['name' => 'Rychlovka']]]));
        $controller = new TagController(new Presenter(), $em);

        self::assertSame(['karetní', 'Rychlovka', 'Strategie'], ControllerHarness::json(ControllerHarness::as($this->anna)->call($controller, 'partyGameTags', ['party' => $this->party])));
        self::assertSame(['karetní', 'Rychlovka', 'Strategie'], ControllerHarness::json(ControllerHarness::as($this->anna)->call($controller, 'gameTags')));

        $this->expectException(AccessDeniedException::class);
        ControllerHarness::as($this->eve)->call($controller, 'partyGameTags', ['party' => $this->party]);
    }

    public function testNoTagsGiveEmptyListAndStrangersAreDenied(): void
    {
        self::assertSame([], ControllerHarness::json(ControllerHarness::as($this->anna)->call($this->tagController([]), 'list', ['party' => $this->party])));

        $this->expectException(AccessDeniedException::class);
        ControllerHarness::as($this->eve)->call($this->tagController([]), 'list', ['party' => $this->party]);
    }
}
