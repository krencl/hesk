<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Domain\Exception\DomainException;
use App\Domain\ResultCalculator;
use App\Dto\PlayRequest;
use App\Dto\PlayerRequest;
use App\Entity\Game;
use App\Entity\GameSession;
use App\Entity\Party;
use App\Entity\Play;
use App\Entity\PlayResult;
use App\Entity\PlayTag;
use App\Entity\User;
use App\Enum\ScoreType;
use App\Repository\GameSessionRepository;
use App\Repository\PlayRepository;
use App\Service\PlayService;
use App\Service\SessionAssigner;
use App\Tests\Support\Fixtures;
use App\Tests\Support\DoctrineMocks;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

/** Zápisy: databáze (EntityManager, repozitáře) je mock, ověřuje se logika služby a to, co se ukládá. */
final class PlayServiceTest extends TestCase
{
    use DoctrineMocks;

    private User $anna;
    private User $bob;
    private Party $party;
    private Game $game;
    /** @var list<GameSession> seance, které „v databázi“ už jsou */
    private array $sessions = [];
    /** @var list<Play> */
    private array $playsInSession = [];
    /** @var array<string, PlayTag> */
    private array $knownTags = [];
    private \ArrayObject $calls;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->anna = Fixtures::user('Anna');
        $this->bob = Fixtures::user('Bob');
        $this->party = Fixtures::party($this->anna);
        Fixtures::member($this->party, $this->bob);
        $this->game = Fixtures::game($this->party, 'Azul', ScoreType::MostPoints);
        $this->sessions = [];
        $this->playsInSession = [];
        $this->knownTags = [];
        $this->calls = new \ArrayObject();
    }

    private function service(array $known = []): PlayService
    {
        $sessionRepo = $this->createStub(GameSessionRepository::class);
        // seance „v databázi“: ty z fixtures plus ty, které služba v průběhu testu uložila (a nesmazala)
        $sessionRepo->method('findByParty')->willReturnCallback(function (): array {
            $removed = $this->calls['remove'] ?? [];
            $all = [...$this->sessions, ...array_filter($this->calls['persist'] ?? [], static fn (object $e): bool => $e instanceof GameSession)];

            return array_values(array_unique(array_filter($all, static fn (GameSession $s): bool => !\in_array($s, $removed, true)), \SORT_REGULAR));
        });
        $playRepo = $this->createStub(PlayRepository::class);
        $playRepo->method('findBySession')->willReturnCallback(fn (): array => $this->playsInSession);

        $tags = $this->createStub(EntityRepository::class);
        $tags->method('findOneBy')->willReturnCallback(fn (array $criteria): ?PlayTag => $this->knownTags[$criteria['name']] ?? null);

        $this->em = $this->mockEm([$this->game, ...Fixtures::persist($this->party)->getPlayers()->toArray(), ...$known], $this->calls);
        $this->em->method('getRepository')->willReturn($tags);

        return new PlayService($this->em, new ResultCalculator(), new SessionAssigner($sessionRepo), $playRepo);
    }

    private function d(string $s): \DateTimeImmutable
    {
        return new \DateTimeImmutable($s);
    }

    /** @param list<array{0: User, 1?: ?float, 2?: bool}> $players */
    private function request(string $day, array $players, array $extra = []): PlayRequest
    {
        return new PlayRequest(
            gameId: $extra['gameId'] ?? $this->game->getId(),
            startedAt: $this->d("{$day}T18:00:00+00:00"),
            endedAt: $this->d("{$day}T19:00:00+00:00"),
            players: array_map(fn (array $p): PlayerRequest => new PlayerRequest(Fixtures::player($this->party, $p[0])->getId(), $p[1] ?? null, winner: $p[2] ?? null, characterId: $p[3] ?? null), $players),
            addonIds: $extra['addonIds'] ?? [],
            tags: $extra['tags'] ?? [],
            notes: $extra['notes'] ?? null,
            sessionNumber: $extra['sessionNumber'] ?? null,
        );
    }

    /** @return list<PlayResult> */
    private function results(Play $play): array
    {
        $results = $play->getResults()->toArray();
        usort($results, static fn (PlayResult $a, PlayResult $b): int => $a->getRank() <=> $b->getRank());

        return $results;
    }

    public function testCreateRanksPlayersAndStoresPlayWithSession(): void
    {
        $play = $this->service()->create($this->party, $this->request('2026-03-01', [[$this->anna, 80.0], [$this->bob, 90.0]], ['notes' => 'Super']));

        self::assertSame(['Bob', 'Anna'], array_map(static fn (PlayResult $r): string => $r->getPlayer()->getName(), $this->results($play)));
        self::assertSame([true, false], array_map(static fn (PlayResult $r): bool => $r->isWinner(), $this->results($play)));
        self::assertSame('Super', $play->getNotes());
        self::assertSame(1, $play->getSession()->getNumber());
        self::assertContains($play, $this->calls['persist']);
        self::assertContains($play->getSession(), $this->calls['persist']);
        self::assertSame(1, $this->calls['flush']);
    }

    public function testPlayWithin24HoursJoinsTheExistingSession(): void
    {
        $existing = new GameSession($this->party, 7, $this->d('2026-03-01T18:00:00+00:00'), $this->d('2026-03-01T19:00:00+00:00'));
        $this->sessions = [$existing];

        $same = $this->service()->create($this->party, $this->request('2026-03-02', [[$this->anna, 1.0]]));
        self::assertSame($existing, $same->getSession());
        self::assertEquals($this->d('2026-03-02T19:00:00+00:00'), $existing->getEndedAt(), 'seance se rozšíří');

        $later = $this->service()->create($this->party, $this->request('2026-03-20', [[$this->anna, 1.0]]));
        self::assertNotSame($existing, $later->getSession());
        self::assertSame(8, $later->getSession()->getNumber());
    }

    public function testExplicitSessionNumberJoinsOrCreatesSession(): void
    {
        $existing = new GameSession($this->party, 95, $this->d('2026-03-01T18:00:00+00:00'), $this->d('2026-03-01T19:00:00+00:00'));
        $this->sessions = [$existing];

        self::assertSame($existing, $this->service()->create($this->party, $this->request('2026-05-01', [[$this->anna, 1.0]], ['sessionNumber' => 95]))->getSession());
        self::assertSame(3, $this->service()->create($this->party, $this->request('2026-05-01', [[$this->anna, 1.0]], ['sessionNumber' => 3]))->getSession()->getNumber());
    }

    public function testTagsAreTrimmedDeduplicatedAndOnlyNewOnesAreCreated(): void
    {
        $this->knownTags['víkend'] = Fixtures::id(new PlayTag($this->party, 'víkend'));

        $play = $this->service()->create($this->party, $this->request('2026-03-01', [[$this->anna, 1.0]], ['tags' => ['víkend', ' Doma ', 'Doma', ' ']]));

        self::assertEqualsCanonicalizing(['víkend', 'Doma'], array_map(static fn (PlayTag $t): string => $t->getName(), $play->getTags()->toArray()));
        $created = array_filter($this->calls['persist'], static fn (object $e): bool => $e instanceof PlayTag);
        self::assertSame(['Doma'], array_map(static fn (PlayTag $t): string => $t->getName(), array_values($created)), 'existující tag se znovu nezakládá');
    }

    public function testAddonsAndCharactersMustBelongToTheGame(): void
    {
        $this->game->update('Azul', null, ScoreType::MostPoints, null, null, null, [['id' => null, 'name' => 'Rivers']], [['id' => null, 'name' => 'Gigazaur']]);
        $addon = Fixtures::id($this->game->getAddons()->first());
        $character = Fixtures::id($this->game->getCharacters()->first());

        $play = $this->service()->create($this->party, $this->request('2026-03-01', [[$this->anna, 1.0, null, $character->getId()]], ['addonIds' => [$addon->getId(), $addon->getId()]]));
        self::assertSame([$addon], $play->getAddons()->toArray());
        self::assertSame($character, $this->results($play)[0]->getCharacter());

        $other = Fixtures::game($this->party, 'Jiná');
        $other->update('Jiná', null, ScoreType::MostPoints, null, null, null, [['id' => null, 'name' => 'X']], [['id' => null, 'name' => 'Y']]);
        $foreignAddon = Fixtures::id($other->getAddons()->first());
        $foreignCharacter = Fixtures::id($other->getCharacters()->first());

        $this->expectException(DomainException::class);
        $this->service()->create($this->party, $this->request('2026-03-01', [[$this->anna, 1.0, null, $foreignCharacter->getId()]]));
    }

    public function testForeignAddonIsRejected(): void
    {
        $other = Fixtures::game($this->party, 'Jiná');
        $other->update('Jiná', null, ScoreType::MostPoints, null, null, null, [['id' => null, 'name' => 'X']]);
        $foreignAddon = Fixtures::id($other->getAddons()->first());

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Rozšíření nepatří');
        $this->service()->create($this->party, $this->request('2026-03-01', [[$this->anna, 1.0]], ['addonIds' => [$foreignAddon->getId()]]));
    }

    public function testGameFromAnotherPartyOrMissingGameIsRejected(): void
    {
        $stranger = Fixtures::user('Eve');
        $foreignGame = Fixtures::game(Fixtures::party($stranger, 'Cizí'), 'Cizí hra');
        $unlinkedGame = Fixtures::catalogGame('Z katalogu'); // sdílená hra, kterou parta nemá
        $service = $this->service([$foreignGame, $unlinkedGame]);

        foreach ([$foreignGame->getId(), $unlinkedGame->getId(), 999999] as $gameId) {
            try {
                $service->create($this->party, $this->request('2026-03-01', [[$this->anna, 1.0]], ['gameId' => $gameId]));
                self::fail('Hra mimo partu se musí odmítnout.');
            } catch (DomainException $e) {
                self::assertStringContainsString('Hra neexistuje', $e->getMessage());
            }
        }
        self::assertArrayNotHasKey('flush', $this->calls->getArrayCopy());
    }

    public function testPlayerFromAnotherPartyIsRejected(): void
    {
        $eve = Fixtures::user('Eve');
        $foreign = Fixtures::player(Fixtures::party($eve, 'Cizí'), $eve);
        $request = new PlayRequest($this->game->getId(), $this->d('2026-03-01T18:00:00+00:00'), $this->d('2026-03-01T19:00:00+00:00'), [new PlayerRequest($foreign->getId(), 1.0)]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Hráč nepatří');
        $this->service([$foreign])->create($this->party, $request);
    }

    public function testInvalidResultsAreRejected(): void
    {
        $service = $this->service();
        // bez skóre u hry s bodováním, dva začínající, hráč dvakrát, žádný hráč
        $annaId = Fixtures::player($this->party, $this->anna)->getId();
        $bobId = Fixtures::player($this->party, $this->bob)->getId();
        $times = [$this->d('2026-03-01T18:00:00+00:00'), $this->d('2026-03-01T19:00:00+00:00')];
        $invalid = [
            [new PlayerRequest($annaId)],
            [new PlayerRequest($annaId, 1.0, starting: true), new PlayerRequest($bobId, 2.0, starting: true)],
            [new PlayerRequest($annaId, 1.0), new PlayerRequest($annaId, 2.0)],
            [],
        ];
        foreach ($invalid as $players) {
            try {
                $service->create($this->party, new PlayRequest($this->game->getId(), $times[0], $times[1], $players));
                self::fail('Neplatný zápis se musí odmítnout.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testManualWinnerAndStartingPlayerAreStored(): void
    {
        $service = $this->service();
        $tied = $service->create($this->party, new PlayRequest($this->game->getId(), $this->d('2026-03-01T18:00:00+00:00'), $this->d('2026-03-01T19:00:00+00:00'), [
            new PlayerRequest(Fixtures::player($this->party, $this->anna)->getId(), 50.0),
            new PlayerRequest(Fixtures::player($this->party, $this->bob)->getId(), 50.0, starting: true),
        ]));
        self::assertSame([true, true], array_map(static fn (PlayResult $r): bool => $r->isWinner(), $this->results($tied)), 'shoda skóre = vyhrají oba');
        self::assertSame(['Bob'], array_values(array_map(static fn (PlayResult $r): string => $r->getPlayer()->getName(), array_filter($this->results($tied), static fn (PlayResult $r): bool => $r->isStarting()))));

        $manual = $service->create($this->party, new PlayRequest($this->game->getId(), $this->d('2026-03-01T20:00:00+00:00'), $this->d('2026-03-01T21:00:00+00:00'), [
            new PlayerRequest(Fixtures::player($this->party, $this->anna)->getId(), 50.0, winner: true, starting: true),
            new PlayerRequest(Fixtures::player($this->party, $this->bob)->getId(), 50.0),
        ]));
        $byName = [];
        foreach ($manual->getResults() as $r) {
            $byName[$r->getPlayer()->getName()] = $r;
        }
        self::assertTrue($byName['Anna']->isWinner());
        self::assertFalse($byName['Bob']->isWinner());
        self::assertSame(1, $byName['Bob']->getRank(), 'pořadí sleduje skóre, ne ruční výběr vítěze');
    }

    public function testUpdateRewritesResultsAndRemovesTheOldOnes(): void
    {
        $service = $this->service();
        $play = $service->create($this->party, $this->request('2026-03-01', [[$this->anna, 80.0], [$this->bob, 90.0]]));
        Fixtures::id($play);
        $this->sessions = [$play->getSession()];
        $this->playsInSession = [$play];
        $oldResults = $play->getResults()->toArray();
        $this->calls->exchangeArray([]);

        $updated = $service->update($play, $this->request('2026-03-01', [[$this->anna, 95.0], [$this->bob, 90.0]], ['notes' => 'Upraveno']));

        self::assertSame($play, $updated);
        self::assertSame(['Anna', 'Bob'], array_map(static fn (PlayResult $r): string => $r->getPlayer()->getName(), $this->results($play)));
        self::assertSame('Upraveno', $play->getNotes());
        self::assertEqualsCanonicalizing($oldResults, $this->calls['remove'], 'staré výsledky se smažou dřív, než se vloží nové (unikát play_id + player_id)');
        self::assertSame(1, $play->getSession()->getNumber(), 'stejný den = stejná seance');

        $single = $this->request('2026-03-01', [[$this->anna, 10.0]]);
        $service->update($play, $single);
        $service->update($play, $single);
        self::assertSame(['Anna'], array_map(static fn (PlayResult $r): string => $r->getPlayer()->getName(), $this->results($play)), 'idempotentní uložení');
    }

    public function testMovingAPlayToALaterDayCreatesNewSessionAndDropsTheEmptyOne(): void
    {
        $service = $this->service();
        $play = Fixtures::id($service->create($this->party, $this->request('2026-03-01', [[$this->anna, 1.0]])));
        $old = $play->getSession();
        $this->sessions = [$old];
        $this->playsInSession = [$play];
        $this->calls->exchangeArray([]);

        $service->update($play, $this->request('2026-03-20', [[$this->anna, 1.0]]));

        self::assertNotSame($old, $play->getSession());
        self::assertSame(2, $play->getSession()->getNumber());
        $removedSessions = array_values(array_filter($this->calls['remove'], static fn (object $e): bool => $e instanceof GameSession));
        self::assertSame([$old], $removedSessions, 'prázdná stará seance zmizí');
    }

    public function testUpdateKeepsTheSessionWhenOtherPlaysStayInIt(): void
    {
        $service = $this->service();
        $first = Fixtures::id($service->create($this->party, $this->request('2026-03-01', [[$this->anna, 1.0]])));
        $second = Fixtures::id($service->create($this->party, $this->request('2026-03-01', [[$this->anna, 2.0]])));
        $session = $first->getSession();
        self::assertSame($session, $second->getSession());
        $this->sessions = [$session];
        $this->playsInSession = [$first, $second];
        $this->calls->exchangeArray([]);

        $service->update($second, $this->request('2026-03-25', [[$this->anna, 2.0]]));

        self::assertNotSame($session, $second->getSession());
        self::assertSame(1, $session->getNumber());
        self::assertNotContains($session, $this->calls['remove'] ?? []);
    }

    public function testExplicitSessionNumberOnUpdateMovesThePlay(): void
    {
        $service = $this->service();
        $first = Fixtures::id($service->create($this->party, $this->request('2026-03-01', [[$this->anna, 1.0]], ['sessionNumber' => 95])));
        $session95 = $first->getSession();
        $joined = Fixtures::id($service->create($this->party, $this->request('2026-05-01', [[$this->anna, 1.0]], ['sessionNumber' => 96])));
        $session96 = $joined->getSession();
        $this->sessions = [$session96, $session95];
        $this->playsInSession = [$first];

        $same = $service->update($first, $this->request('2026-03-01', [[$this->anna, 1.0]], ['sessionNumber' => 95]));
        self::assertSame($session95, $same->getSession(), 'stejné číslo = žádný přesun');

        $this->playsInSession = [$joined];
        $moved = $service->update($joined, $this->request('2026-05-01', [[$this->anna, 1.0]], ['sessionNumber' => 95]));
        self::assertSame($session95, $moved->getSession());
    }

    public function testFailedUpdateLeavesResultsUntouched(): void
    {
        $service = $this->service();
        $play = Fixtures::id($service->create($this->party, $this->request('2026-03-01', [[$this->anna, 80.0], [$this->bob, 90.0]])));
        $this->sessions = [$play->getSession()];
        $this->playsInSession = [$play];
        $this->calls->exchangeArray([]);

        $bad = new PlayRequest($this->game->getId(), $this->d('2026-03-01T18:00:00+00:00'), $this->d('2026-03-01T19:00:00+00:00'), [new PlayerRequest(999999, 1.0)]);
        try {
            $service->update($play, $bad);
            self::fail('Cizí hráč se musí odmítnout.');
        } catch (DomainException) {
        }

        self::assertCount(2, $play->getResults(), 'výsledky zůstaly');
        self::assertArrayNotHasKey('remove', $this->calls->getArrayCopy());
    }

    public function testDeleteRemovesPlayAndEmptySession(): void
    {
        $service = $this->service();
        $play = Fixtures::id($service->create($this->party, $this->request('2026-03-01', [[$this->anna, 1.0]])));
        $session = $play->getSession();
        $this->playsInSession = [];
        $this->calls->exchangeArray([]);

        $service->delete($play);

        self::assertSame([$play, $session], $this->calls['remove']);
    }

    public function testDeleteKeepsSessionWithRemainingPlaysAndShrinksIt(): void
    {
        $service = $this->service();
        $first = Fixtures::id($service->create($this->party, $this->request('2026-03-01', [[$this->anna, 1.0]])));
        $second = Fixtures::id($service->create($this->party, $this->request('2026-03-02', [[$this->anna, 1.0]])));
        $session = $first->getSession();
        self::assertSame($session, $second->getSession());
        $this->playsInSession = [$first];
        $this->calls->exchangeArray([]);

        $service->delete($second);

        self::assertSame([$second], $this->calls['remove']);
        self::assertEquals($this->d('2026-03-01T19:00:00+00:00'), $session->getEndedAt());
    }
}
