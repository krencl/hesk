<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Domain\PartyExportFilter;
use App\Entity\GameSession;
use App\Entity\GameTag;
use App\Entity\Party;
use App\Entity\Play;
use App\Entity\PlayResult;
use App\Entity\PlayTag;
use App\Entity\User;
use App\Enum\ScoreType;
use App\Repository\GameRepository;
use App\Repository\PlayRepository;
use App\Repository\PlayTagRepository;
use App\Service\PartyExporter;
use App\Tests\Support\Fixtures;
use PHPUnit\Framework\TestCase;

final class PartyExporterTest extends TestCase
{
    private User $anna;
    private Party $party;

    protected function setUp(): void
    {
        $this->anna = Fixtures::user('Anna');
        $this->party = Fixtures::party($this->anna);
    }

    private function exporter(array $plays, array $playTags = [], ?PartyExportFilter $expectedFilter = null): PartyExporter
    {
        $repo = $this->createMock(PlayRepository::class);
        $repo->expects(self::once())->method('findForExport')->with($this->party, $expectedFilter ?? new PartyExportFilter())->willReturn($plays);
        $tags = $this->createStub(PlayTagRepository::class);
        $tags->method('findBy')->willReturn($playTags);

        return new PartyExporter($repo, $this->createStub(GameRepository::class), $tags);
    }

    private function export(PartyExporter $exporter, ?PartyExportFilter $filter = null): array
    {
        return $exporter->export($this->party, $filter ?? new PartyExportFilter(), Fixtures::date('2026-11-02T14:05:00+01:00'));
    }

    public function testDescribesTheExportInMeta(): void
    {
        $filter = new PartyExportFilter(fromSession: 3, toSession: 5);

        $meta = $this->export($this->exporter([], expectedFilter: $filter), $filter)['meta'];

        self::assertSame([
            'format' => 'hesk-party-export',
            'version' => 1,
            'exportedAt' => '2026-11-02T14:05:00+01:00',
            'party' => ['id' => $this->party->getId(), 'name' => 'Pátek', 'shortName' => 'Pá'],
            'filter' => ['type' => 'session', 'from' => 3, 'to' => 5],
        ], $meta);
    }

    public function testPlayersCarryNoAccountDataAndMembersAreNotExported(): void
    {
        $bob = Fixtures::guest($this->party, 'Bob');

        $data = $this->export($this->exporter([]));

        self::assertArrayNotHasKey('members', $data);
        self::assertSame(
            [['id' => Fixtures::player($this->party, $this->anna)->getId(), 'name' => 'Anna', 'visible' => true], ['id' => $bob->getId(), 'name' => 'Bob', 'visible' => true]],
            $data['players'],
        );
    }

    public function testGamesExportOnlyBasicDataAndReferenceTheirChildrenByGameId(): void
    {
        $game = Fixtures::game($this->party, 'Azul', ScoreType::FewestPoints);
        $tag = Fixtures::id(new GameTag('Rodinná'));
        $game->setTags([$tag]);
        $game->update('Azul', 'Az', ScoreType::FewestPoints, '/uploads/covers/a.webp', 'Long description', '# Cheat sheet', [['id' => null, 'name' => 'Letní pavilon']], [['id' => null, 'name' => 'Modrý']], 8);
        $addon = Fixtures::id($game->getAddons()->first());
        $character = Fixtures::id($game->getCharacters()->first());
        $this->party->findGameLink($game)->syncPlayers([['id' => Fixtures::player($this->party, $this->anna)->getId(), 'name' => 'Anna']]);

        $data = $this->export($this->exporter([]));

        self::assertSame([[
            'id' => $game->getId(), 'name' => 'Azul', 'shortName' => 'Az', 'scoreType' => 'fewest_points', 'bggId' => 8,
            'imageUrl' => '/uploads/covers/a.webp', 'tagIds' => [$tag->getId()],
        ]], $data['games']);
        self::assertSame([['id' => $addon->getId(), 'gameId' => $game->getId(), 'name' => 'Letní pavilon']], $data['gameAddons']);
        self::assertSame([['id' => $character->getId(), 'gameId' => $game->getId(), 'name' => 'Modrý']], $data['gameCharacters']);
        self::assertSame([['id' => $tag->getId(), 'name' => 'Rodinná']], $data['gameTags']);
        self::assertSame([['gameId' => $game->getId(), 'playerIds' => [Fixtures::player($this->party, $this->anna)->getId()]]], $data['partyGames']);
    }

    public function testPlaysReferenceEverythingByIdAndListEachSessionOnce(): void
    {
        $bob = Fixtures::guest($this->party, 'Bob');
        $annaPlayer = Fixtures::player($this->party, $this->anna);
        $game = Fixtures::game($this->party);
        $game->update('Azul', null, ScoreType::MostPoints, null, null, null, [['id' => null, 'name' => 'Rozšíření']], [['id' => null, 'name' => 'Modrý']]);
        $addon = Fixtures::id($game->getAddons()->first());
        $character = Fixtures::id($game->getCharacters()->first());
        $tag = Fixtures::id(new PlayTag($this->party, 'Turnaj'));
        $session = Fixtures::id(new GameSession($this->party, 12, Fixtures::date('2026-10-01T18:00:00+02:00'), Fixtures::date('2026-10-01T23:00:00+02:00')));
        $first = Fixtures::id(new Play($this->party, $game, Fixtures::date('2026-10-01T18:00:00+02:00'), Fixtures::date('2026-10-01T19:00:00+02:00'), $session));
        $second = Fixtures::id(new Play($this->party, $game, Fixtures::date('2026-10-01T20:00:00+02:00'), Fixtures::date('2026-10-01T21:00:00+02:00'), $session));
        $first->setNotes('Close one');
        $first->setAddons([$addon]);
        $first->setTags([$tag]);
        $first->replaceResults([
            new PlayResult($first, $annaPlayer, 70.5, 2, false, false, $character),
            new PlayResult($first, $bob, 80.0, 1, true, true),
        ]);

        $data = $this->export($this->exporter([$first, $second], [$tag]));

        self::assertSame([['id' => $tag->getId(), 'name' => 'Turnaj']], $data['playTags']);
        self::assertSame([['id' => $session->getId(), 'number' => 12, 'startedAt' => '2026-10-01T18:00:00+02:00', 'endedAt' => '2026-10-01T23:00:00+02:00']], $data['sessions']);
        self::assertSame([$first->getId(), $second->getId()], array_column($data['plays'], 'id'));
        self::assertSame([
            'id' => $first->getId(), 'gameId' => $game->getId(), 'sessionId' => $session->getId(),
            'startedAt' => '2026-10-01T18:00:00+02:00', 'endedAt' => '2026-10-01T19:00:00+02:00', 'notes' => 'Close one',
            'addonIds' => [$addon->getId()], 'tagIds' => [$tag->getId()],
            'results' => [ // by rank
                ['playerId' => $bob->getId(), 'score' => 80.0, 'rank' => 1, 'winner' => true, 'starting' => true, 'characterId' => null],
                ['playerId' => $annaPlayer->getId(), 'score' => 70.5, 'rank' => 2, 'winner' => false, 'starting' => false, 'characterId' => $character->getId()],
            ],
        ], $data['plays'][0]);
        self::assertSame([], $data['plays'][1]['results']);
    }

    public function testFilteredExportKeepsWholePlayersAndGamesButOnlyReferencedSessions(): void
    {
        Fixtures::guest($this->party, 'Bob');
        Fixtures::game($this->party);

        $data = $this->export($this->exporter([], expectedFilter: new PartyExportFilter(fromSession: 99)), new PartyExportFilter(fromSession: 99));

        self::assertCount(2, $data['players']);
        self::assertCount(1, $data['games']);
        self::assertSame([], $data['sessions']);
        self::assertSame([], $data['plays']);
    }

    public function testResultIsJsonEncodableWithKeysInDocumentedOrder(): void
    {
        $data = $this->export($this->exporter([]));

        self::assertSame(['meta', 'players', 'games', 'gameAddons', 'gameCharacters', 'gameTags', 'partyGames', 'playTags', 'sessions', 'plays'], array_keys($data));
        self::assertIsString(json_encode($data, \JSON_THROW_ON_ERROR));
    }
}
