<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\PlayController;
use App\Domain\Exception\DomainException;
use App\Dto\PlayRequest;
use App\Entity\GameSession;
use App\Entity\Party;
use App\Entity\Play;
use App\Entity\PlayResult;
use App\Entity\PlayTag;
use App\Entity\User;
use App\Enum\PartyRole;
use App\Repository\PlayRepository;
use App\Service\PlayService;
use App\Service\Presenter;
use App\Service\SessionAssigner;
use App\Tests\Support\ControllerHarness;
use App\Tests\Support\Fixtures;
use App\Tests\Support\InMemorySessions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/** Zápisy v API: služby a repozitáře jsou mocky, kontroler se spouští přímo, ale pod skutečným voterem. */
final class PlayControllerTest extends TestCase
{
    use InMemorySessions;

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
        Fixtures::member($this->party, $this->bob);
    }

    private function play(string $start = '2026-03-01T18:00:00+00:00', int $number = 1): Play
    {
        $game = Fixtures::game($this->party);
        $session = Fixtures::id(new GameSession($this->party, $number, Fixtures::date($start), Fixtures::date($start)));
        $play = Fixtures::id(new Play($this->party, $game, Fixtures::date($start), Fixtures::date($start), $session));
        $play->replaceResults([
            new PlayResult($play, Fixtures::player($this->party, $this->anna), 80.0, 2, false),
            new PlayResult($play, Fixtures::player($this->party, $this->bob), 90.0, 1, true, true),
        ]);

        return $play;
    }

    private function controller(?PlayService $service = null, ?PlayRepository $plays = null, array $sessions = []): PlayController
    {
        return new PlayController(new Presenter(), $service ?? $this->createStub(PlayService::class), $plays ?? $this->createStub(PlayRepository::class), new SessionAssigner($this->sessionRepository(static fn (): array => $sessions)));
    }

    public function testListReturnsPresentedPlaysAndPassesPaging(): void
    {
        $newer = $this->play('2026-03-20T18:00:00+00:00', 2);
        $older = $this->play();
        $plays = $this->createMock(PlayRepository::class);
        $plays->expects(self::once())->method('findPage')->with($this->party, 20, 0, null)->willReturn([$newer, $older]);

        $response = ControllerHarness::as($this->bob)->call($this->controller(plays: $plays), 'list', ['party' => $this->party, 'request' => new Request()]);
        $list = ControllerHarness::json($response);

        self::assertSame([$newer->getId(), $older->getId()], array_column($list, 'id'));
        self::assertSame(['Bob', 'Anna'], array_column($list[0]['results'], 'name'), 'výsledky podle pořadí');
        self::assertTrue($list[0]['results'][0]['winner']);
        self::assertTrue($list[0]['results'][0]['starting']);
    }

    public function testListLimitsAreClampedAndGameFilterIsPassed(): void
    {
        $plays = $this->createMock(PlayRepository::class);
        $plays->expects(self::once())->method('findPage')->with($this->party, 100, 0, 7)->willReturn([]);

        ControllerHarness::as($this->anna)->call($this->controller(plays: $plays), 'list', ['party' => $this->party, 'request' => new Request(['limit' => '5000', 'offset' => '-4', 'gameId' => '7'])]);
    }

    public function testStrangerCannotListOrReadPlays(): void
    {
        $this->expectException(AccessDeniedException::class);
        ControllerHarness::as($this->eve)->call($this->controller(), 'list', ['party' => $this->party, 'request' => new Request()]);
    }

    public function testShowIsForMembersOnly(): void
    {
        $play = $this->play();
        $harness = ControllerHarness::as($this->bob);
        self::assertSame($play->getId(), ControllerHarness::json($harness->call($this->controller(), 'show', ['play' => $play]))['id']);

        $this->expectException(AccessDeniedException::class);
        ControllerHarness::as($this->eve)->call($this->controller(), 'show', ['play' => $play]);
    }

    public function testPlainMemberCannotWriteButWriterCan(): void
    {
        $play = $this->play();
        $request = new PlayRequest($play->getGame()->getId(), $play->getStartedAt(), $play->getEndedAt());
        $service = $this->createMock(PlayService::class);
        $service->expects(self::once())->method('create')->with($this->party, $request)->willReturn($play);
        $service->expects(self::once())->method('update')->with($play, $request)->willReturn($play);
        $service->expects(self::once())->method('delete')->with($play);

        // člen: čte, ale nezapisuje (ani výchozí hodnoty formuláře)
        $member = ControllerHarness::as($this->bob);
        foreach ([
            ['create', ['party' => $this->party, 'req' => $request]],
            ['update', ['play' => $play, 'req' => $request]],
            ['delete', ['play' => $play]],
            ['defaults', ['party' => $this->party, 'request' => new Request(['startedAt' => '2026-03-02T18:00:00+00:00'])]],
        ] as [$action, $args]) {
            try {
                $member->call($this->controller($service), $action, $args);
                self::fail("Člen nesmí volat $action.");
            } catch (AccessDeniedException) {
                $this->addToAssertionCount(1);
            }
        }

        // admin povýší Boba na zapisovače
        $this->party->changeRole($this->bob, PartyRole::Writer);
        $writer = ControllerHarness::as($this->bob);
        $controller = $this->controller($service);
        self::assertSame(201, $writer->call($controller, 'create', ['party' => $this->party, 'req' => $request])->getStatusCode());
        self::assertSame(200, $writer->call($controller, 'update', ['play' => $play, 'req' => $request])->getStatusCode());
        self::assertSame(204, $writer->call($controller, 'delete', ['play' => $play])->getStatusCode());
    }

    public function testCreateReturnsThePresentedPlay(): void
    {
        $play = $this->play();
        $service = $this->createStub(PlayService::class);
        $service->method('create')->willReturn($play);

        $response = ControllerHarness::as($this->anna)->call($this->controller($service), 'create', ['party' => $this->party, 'req' => new PlayRequest($play->getGame()->getId(), $play->getStartedAt(), $play->getEndedAt())]);
        $payload = ControllerHarness::json($response);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame(['Azul', 1, 1], [$payload['game']['name'], $payload['sessionNumber'], $payload['sessionId'] === $play->getSession()->getId() ? 1 : 0]);
        self::assertSame('2026-03-01T18:00:00+00:00', $payload['startedAt']);
    }

    public function testDomainErrorFromServiceIsNotSwallowed(): void
    {
        $service = $this->createStub(PlayService::class);
        $service->method('create')->willThrowException(new DomainException('Hráč nepatří do této party.'));
        $play = $this->play();

        $this->expectException(DomainException::class);
        ControllerHarness::as($this->anna)->call($this->controller($service), 'create', ['party' => $this->party, 'req' => new PlayRequest(1, $play->getStartedAt(), $play->getEndedAt())]);
    }

    public function testDefaultsForFirstGameOfTheDay(): void
    {
        $response = ControllerHarness::as($this->anna)->call($this->controller(), 'defaults', ['party' => $this->party, 'request' => new Request(['startedAt' => '2026-03-01T20:00:00+00:00'])]);

        self::assertSame(['sessionNumber' => 1, 'playerIds' => null, 'tags' => []], ControllerHarness::json($response));
    }

    public function testDefaultsTakePlayersAndTagsOfTheLastPlayInTheSession(): void
    {
        $last = $this->play('2026-03-01T20:00:00+00:00', 5);
        $last->replaceResults([new PlayResult($last, Fixtures::player($this->party, $this->bob), 1.0, 1, true)]);
        $last->setTags([new PlayTag($this->party, 'liga'), new PlayTag($this->party, 'Doma')]);
        $session = $last->getSession();
        $plays = $this->createStub(PlayRepository::class);
        $plays->method('findLastInSession')->willReturnCallback(static fn (GameSession $s): ?Play => $s === $session ? $last : null);
        $controller = $this->controller(plays: $plays, sessions: [$session]);
        $harness = ControllerHarness::as($this->anna);

        // ten samý večer: číslo seance a hráči/tagy posledního zápisu
        $sameEvening = ControllerHarness::json($harness->call($controller, 'defaults', ['party' => $this->party, 'request' => new Request(['startedAt' => '2026-03-01T21:00:00+00:00'])]));
        self::assertSame([5, [Fixtures::player($this->party, $this->bob)->getId()]], [$sameEvening['sessionNumber'], $sameEvening['playerIds']]);
        self::assertEqualsCanonicalizing(['liga', 'Doma'], $sameEvening['tags']);

        // do 24 h od posledního zápisu je to pořád tatáž seance; o měsíc později další číslo bez předvybraných hráčů
        self::assertSame(5, ControllerHarness::json($harness->call($controller, 'defaults', ['party' => $this->party, 'request' => new Request(['startedAt' => '2026-03-02T19:00:00+00:00'])]))['sessionNumber']);
        self::assertSame(['sessionNumber' => 6, 'playerIds' => null, 'tags' => []], ControllerHarness::json($harness->call($controller, 'defaults', ['party' => $this->party, 'request' => new Request(['startedAt' => '2026-04-01T20:00:00+00:00'])])));
    }

    public function testDefaultsRequireValidTime(): void
    {
        $harness = ControllerHarness::as($this->anna);
        foreach ([[], ['startedAt' => 'nesmysl'], ['endedAt' => '2026-03-01T20:00:00+00:00']] as $query) {
            try {
                $harness->call($this->controller(), 'defaults', ['party' => $this->party, 'request' => new Request($query)]);
                self::fail('Chybějící nebo neplatný čas se musí odmítnout.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
