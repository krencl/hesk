<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** End-to-end průchod API nad SQLite databází (viz docs/adr/0003). */
final class ApiFlowTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $tool->dropSchema($em->getMetadataFactory()->getAllMetadata());
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());
    }

    /** @return array<mixed> */
    private function api(string $method, string $uri, ?array $body = null, int $expected = 200): array
    {
        $this->client->request($method, $uri, server: ['CONTENT_TYPE' => 'application/json'], content: null === $body ? null : json_encode($body, \JSON_THROW_ON_ERROR));
        self::assertSame($expected, $this->client->getResponse()->getStatusCode(), $this->client->getResponse()->getContent());
        $content = $this->client->getResponse()->getContent();

        return $content ? json_decode($content, true, flags: \JSON_THROW_ON_ERROR) : [];
    }

    private function login(string $email, string $nick): array
    {
        $this->client->getCookieJar()->clear();

        return $this->api('POST', '/api/auth/dev-login', ['email' => $email, 'nickname' => $nick]);
    }

    public function testAdminCanEditPartyNameAndShortName(): void
    {
        $this->login('anna@example.com', 'Anna');
        $party = $this->api('POST', '/api/parties', ['name' => 'Pátek'], 201);
        self::assertSame('Pá', $party['shortName'], 'zkratka se při založení vygeneruje z názvu');
        self::assertSame('VM', $this->api('POST', '/api/parties', ['name' => 'Viki + Marek'], 201)['shortName']);
        $invite = $this->api('POST', "/api/parties/{$party['id']}/invitations", null, 201);

        $edited = $this->api('PATCH', "/api/parties/{$party['id']}", ['name' => ' Pátek večer ', 'shortName' => 'PV']);
        self::assertSame(['Pátek večer', 'PV'], [$edited['name'], $edited['shortName']]);
        self::assertSame('PV', $this->api('GET', '/api/parties')[0]['shortName']);
        self::assertSame('PV', $this->api('PATCH', "/api/parties/{$party['id']}", ['name' => 'Pátek večer', 'shortName' => ' PV '])['shortName'], 'zkratka se ořezává');

        // zkratka je povinná (1–3 znaky), stejně jako název
        $this->api('PATCH', "/api/parties/{$party['id']}", ['name' => 'Pátek večer', 'shortName' => '  '], 422);
        $this->api('PATCH', "/api/parties/{$party['id']}", ['name' => 'Pátek večer'], 422);
        $this->api('PATCH', "/api/parties/{$party['id']}", ['name' => 'Pátek', 'shortName' => 'ABCD'], 422);
        $this->api('PATCH', "/api/parties/{$party['id']}", ['name' => ' '], 422);
        $this->api('POST', '/api/parties', ['name' => '   '], 422);

        // běžný člen partu upravit nemůže
        $this->login('bob@example.com', 'Bob');
        $this->api('POST', "/api/invitations/{$invite['token']}/accept");
        $this->api('PATCH', "/api/parties/{$party['id']}", ['name' => 'Cizí'], 403);
    }

    public function testRequiresAuthentication(): void
    {
        $this->api('GET', '/api/parties', expected: 401);
        $this->api('GET', '/api/health');
    }

    public function testPartyGamePlayAndStatsFlow(): void
    {
        $this->login('anna@example.com', 'Anna');
        $party = $this->api('POST', '/api/parties', ['name' => 'Pátek'], 201);
        self::assertSame('admin', $party['role']);

        // druhý hráč se připojí přes pozvánku
        $invite = $this->api('POST', "/api/parties/{$party['id']}/invitations", null, 201);
        $this->login('bob@example.com', 'Bob');
        $this->api('GET', "/api/parties/{$party['id']}", expected: 403);
        $this->api('POST', "/api/invitations/{$invite['token']}/accept");
        $this->api('POST', "/api/parties/{$party['id']}/invitations", null, 403);

        $game = $this->api('POST', "/api/parties/{$party['id']}/games", [
            'name' => 'Carcassonne', 'scoreType' => 'most_points', 'addons' => [['name' => 'Rivers']],
        ], 201);

        $players = array_column($this->api('GET', "/api/parties/{$party['id']}/players"), 'id', 'name');
        self::assertSame(['Anna', 'Bob'], array_keys($players));
        $guest = $this->api('POST', "/api/parties/{$party['id']}/players", ['name' => 'Host'], 201);
        $this->api('POST', "/api/parties/{$party['id']}/players", ['name' => 'host'], 422);
        self::assertFalse($guest['hasAccount']);

        $payload = fn (string $start, int $annaScore, int $bobScore): array => [
            'gameId' => $game['id'],
            'startedAt' => $start.'T18:00:00+00:00',
            'endedAt' => $start.'T19:00:00+00:00',
            'tags' => ['víkend', 'Doma'],
            'addonIds' => [$game['addons'][0]['id']],
            'players' => [['playerId' => $players['Anna'], 'score' => $annaScore], ['playerId' => $players['Bob'], 'score' => $bobScore]],
        ];

        $p1 = $this->api('POST', "/api/parties/{$party['id']}/plays", $payload('2026-03-01', 80, 90), 201);
        $p2 = $this->api('POST', "/api/parties/{$party['id']}/plays", $payload('2026-03-02', 70, 60), 201);
        $p3 = $this->api('POST', "/api/parties/{$party['id']}/plays", $payload('2026-03-20', 70, 60), 201);

        self::assertSame($p1['sessionId'], $p2['sessionId'], 'plays within 24h share a session');
        self::assertNotSame($p1['sessionId'], $p3['sessionId']);
        self::assertSame('Bob', $p1['results'][0]['name']);
        self::assertTrue($p1['results'][0]['winner']);

        $list = $this->api('GET', "/api/parties/{$party['id']}/plays");
        self::assertSame([$p3['id'], $p2['id'], $p1['id']], array_column($list, 'id'));

        $stats = $this->api('GET', "/api/parties/{$party['id']}/stats");
        self::assertSame(['Anna' => 2, 'Bob' => 1], array_column($stats['wins'], 'wins', 'name'));
        self::assertSame(3, $stats['topGames'][0]['plays']);

        $this->api('DELETE', "/api/games/{$game['id']}", expected: 422);
        $this->api('DELETE', "/api/plays/{$p3['id']}", expected: 204);
        $this->api('DELETE', "/api/plays/{$p3['id']}", expected: 404);
    }

    public function testGameListSearchSortLastPlayAndSessions(): void
    {
        $this->login('anna@example.com', 'Anna');
        $party = $this->api('POST', '/api/parties', ['name' => 'Pátek'], 201);
        $base = "/api/parties/{$party['id']}";
        $anna = $this->api('GET', "$base/players")[0]['id'];
        $dixit = $this->api('POST', "$base/games", ['name' => 'Dixit', 'scoreType' => 'most_points'], 201);
        $azul = $this->api('POST', "$base/games", ['name' => 'Azul', 'scoreType' => 'winner_only'], 201);
        $this->api('POST', "$base/games", ['name' => 'Caj_100%', 'scoreType' => 'winner_only'], 201);

        $play = fn (array $game, string $day): array => $this->api('POST', "$base/plays", [
            'gameId' => $game['id'], 'startedAt' => "{$day}T18:00:00+00:00", 'endedAt' => "{$day}T19:00:00+00:00",
            'players' => [['playerId' => $anna, 'score' => 5, 'winner' => true]],
        ], 201);
        $p1 = $play($dixit, '2026-03-01');
        $p2 = $play($azul, '2026-03-20');
        $p3 = $play($dixit, '2026-04-10');

        self::assertSame([1, 2, 3], [$p1['sessionNumber'], $p2['sessionNumber'], $p3['sessionNumber']]);
        self::assertSame([3, 2, 1], array_column($this->api('GET', "$base/sessions"), 'number'));

        // výchozí řazení = poslední aktivita, hry bez zápisu na konci
        $list = $this->api('GET', "$base/games");
        self::assertSame(['Dixit', 'Azul', 'Caj_100%'], array_column($list, 'name'));
        self::assertSame(2, $list[0]['playCount']);
        self::assertSame($p3['id'], $list[0]['lastPlay']['id']);
        self::assertSame(3, $list[0]['lastPlay']['sessionNumber']);
        self::assertSame(['Anna'], $list[0]['lastPlay']['winners']);
        self::assertEquals(5, $list[0]['lastPlay']['winnerScore']);
        self::assertNull($list[2]['lastPlay']);
        self::assertSame(0, $list[2]['playCount']);

        self::assertSame(['Azul', 'Caj_100%', 'Dixit'], array_column($this->api('GET', "$base/games?sort=name"), 'name'));
        self::assertSame(['Dixit'], array_column($this->api('GET', "$base/games?q=dIX"), 'name'));
        self::assertSame(['Caj_100%'], array_column($this->api('GET', "$base/games?q=".rawurlencode('_100%')), 'name'), 'LIKE wildcards are escaped');
        self::assertSame(['Azul'], array_column($this->api('GET', "$base/games?sessionNumber=2"), 'name'));
        self::assertSame(['Azul', 'Caj_100%'], array_column($this->api('GET', "$base/games?limit=2&offset=1"), 'name'));

        $detail = $this->api('GET', "/api/games/{$dixit['id']}");
        self::assertSame(2, $detail['playCount']);
        self::assertSame($p3['id'], $detail['lastPlay']['id']);

        $byGame = $this->api('GET', "$base/plays?gameId={$dixit['id']}");
        self::assertSame([$p3['id'], $p1['id']], array_column($byGame, 'id'));
    }

    public function testUpdatePlayKeepsPlayersRewritesResultsAndMovesSessions(): void
    {
        $this->login('anna@example.com', 'Anna');
        $party = $this->api('POST', '/api/parties', ['name' => 'Pátek'], 201);
        $base = "/api/parties/{$party['id']}";
        $anna = $this->api('GET', "$base/players")[0]['id'];
        $bob = $this->api('POST', "$base/players", ['name' => 'Bob'], 201)['id'];
        $game = $this->api('POST', "$base/games", ['name' => 'Azul', 'scoreType' => 'most_points', 'addons' => [['name' => 'Rivers'], ['name' => 'Opat']]], 201);
        [$rivers, $opat] = array_column($game['addons'], 'id');

        $payload = fn (string $day, int $annaScore, int $bobScore, array $extra = []): array => $extra + [
            'gameId' => $game['id'], 'startedAt' => "{$day}T18:00:00+00:00", 'endedAt' => "{$day}T19:00:00+00:00",
            'players' => [['playerId' => $anna, 'score' => $annaScore], ['playerId' => $bob, 'score' => $bobScore]],
            'addonIds' => [$rivers], 'tags' => ['víkend', 'Doma'], 'notes' => null,
        ];
        $play = $this->api('POST', "$base/plays", $payload('2026-03-01', 80, 90), 201);
        self::assertSame('Bob', $play['results'][0]['name']);

        // stejní hráči, jiné skóre, jiné rozšíření/tagy/místo: dřív padalo na unikátu play_id + player_id
        $updated = $this->api('PUT', "/api/plays/{$play['id']}", $payload('2026-03-01', 95, 90, ['addonIds' => [$opat], 'tags' => ['víkend', 'liga', 'U Kuby'], 'notes' => 'Upraveno']));
        self::assertSame('Anna', $updated['results'][0]['name']);
        self::assertTrue($updated['results'][0]['winner']);
        self::assertFalse($updated['results'][1]['winner']);
        self::assertSame(['Opat'], array_column($updated['addons'], 'name'));
        self::assertEqualsCanonicalizing(['víkend', 'liga', 'U Kuby'], $updated['tags']);
        self::assertSame('Upraveno', $updated['notes']);
        self::assertSame($play['sessionId'], $updated['sessionId']);

        // odebrání hráče a stejná data znovu (idempotentní uložení)
        $single = $payload('2026-03-01', 95, 90, ['players' => [['playerId' => $anna, 'score' => 10]]]);
        $this->api('PUT', "/api/plays/{$play['id']}", $single);
        $again = $this->api('PUT', "/api/plays/{$play['id']}", $single);
        self::assertSame(['Anna'], array_column($again['results'], 'name'));

        // přesun o týden dál = nová seance s dalším číslem, stará prázdná seance zmizí
        $moved = $this->api('PUT', "/api/plays/{$play['id']}", $payload('2026-03-20', 70, 60));
        self::assertSame(2, $moved['sessionNumber']);
        self::assertSame([2], array_column($this->api('GET', "$base/sessions"), 'number'));

        // chybná úprava (cizí hráč) nic nerozbije: hráči zůstanou
        $this->api('PUT', "/api/plays/{$play['id']}", $payload('2026-03-20', 1, 2, ['players' => [['playerId' => 999999, 'score' => 1]]]), 422);
        self::assertCount(2, $this->api('GET', "/api/plays/{$play['id']}")['results']);
    }

    public function testPlayDefaultsSuggestSessionNumberAndLastPlayers(): void
    {
        $this->login('anna@example.com', 'Anna');
        $party = $this->api('POST', '/api/parties', ['name' => 'Pátek'], 201);
        $base = "/api/parties/{$party['id']}";
        $anna = $this->api('GET', "$base/players")[0]['id'];
        $bob = $this->api('POST', "$base/players", ['name' => 'Bob'], 201)['id'];
        $game = $this->api('POST', "$base/games", ['name' => 'Azul', 'scoreType' => 'winner_only'], 201);
        $defaults = fn (string $day, string $time = '20:00'): array => $this->api('GET', "$base/plays/defaults?startedAt=".rawurlencode("{$day}T{$time}:00+00:00"));

        // první hra: seance ještě není → číslo 1 a žádní předvybraní hráči
        self::assertSame(['sessionNumber' => 1, 'playerIds' => null, 'tags' => []], $defaults('2026-03-01'));

        $play = fn (string $day, string $time, array $ids, array $extra = []): array => $this->api('POST', "$base/plays", $extra + [
            'gameId' => $game['id'], 'startedAt' => "{$day}T{$time}:00+00:00", 'endedAt' => "{$day}T{$time}:00+00:00",
            'players' => array_map(static fn (int $id): array => ['playerId' => $id, 'winner' => $id === $ids[0]], $ids),
        ], 201);
        $play('2026-03-01', '18:00', [$anna, $bob]);
        $play('2026-03-01', '20:00', [$bob], ['tags' => ['liga', 'Doma']]);

        // ten samý večer: stejné číslo a hráči posledního zápisu seance
        $last = $defaults('2026-03-01', '21:00');
        self::assertSame([1, [$bob]], [$last['sessionNumber'], $last['playerIds']]);
        self::assertEqualsCanonicalizing(['liga', 'Doma'], $last['tags'], 'tagy posledního zápisu seance');
        // do 24 h od posledního zápisu je to pořád ta samá seance
        self::assertSame(1, $defaults('2026-03-02', '19:00')['sessionNumber']);
        // o měsíc později další číslo, hráči se neberou
        self::assertSame(['sessionNumber' => 2, 'playerIds' => null, 'tags' => []], $defaults('2026-04-01'));

        $this->api('GET', "$base/plays/defaults", expected: 422);
        $this->api('GET', "$base/plays/defaults?startedAt=nesmysl", expected: 422);
    }

    public function testExplicitSessionNumberOnCreateAndUpdate(): void
    {
        $this->login('anna@example.com', 'Anna');
        $party = $this->api('POST', '/api/parties', ['name' => 'Pátek'], 201);
        $base = "/api/parties/{$party['id']}";
        $anna = $this->api('GET', "$base/players")[0]['id'];
        $game = $this->api('POST', "$base/games", ['name' => 'Azul', 'scoreType' => 'winner_only'], 201);
        $payload = fn (string $day, array $extra = []): array => $extra + [
            'gameId' => $game['id'], 'startedAt' => "{$day}T18:00:00+00:00", 'endedAt' => "{$day}T19:00:00+00:00",
            'players' => [['playerId' => $anna, 'winner' => true]],
        ];

        // ruční číslo (třeba navázání na starou číselnou řadu), další zápis bez čísla pokračuje za ním
        $first = $this->api('POST', "$base/plays", $payload('2026-03-01', ['sessionNumber' => 95]), 201);
        self::assertSame(95, $first['sessionNumber']);
        self::assertSame(96, $this->api('POST', "$base/plays", $payload('2026-04-01'), 201)['sessionNumber']);
        // zápis se zařadí do existující seance s daným číslem
        $joined = $this->api('POST', "$base/plays", $payload('2026-05-01', ['sessionNumber' => 95]), 201);
        self::assertSame($first['sessionId'], $joined['sessionId']);
        $this->api('POST', "$base/plays", $payload('2026-05-02', ['sessionNumber' => 0]), 422);

        // úprava: přepsání čísla přesune zápis, prázdná seance zmizí
        $moved = $this->api('PUT', "/api/plays/{$joined['id']}", $payload('2026-05-01', ['sessionNumber' => 96]));
        self::assertSame(96, $moved['sessionNumber']);
        $same = $this->api('PUT', "/api/plays/{$moved['id']}", $payload('2026-05-01', ['sessionNumber' => 96]));
        self::assertSame(96, $same['sessionNumber']);
        $alone = $this->api('PUT', "/api/plays/{$first['id']}", $payload('2026-03-01', ['sessionNumber' => 95]));
        self::assertSame($first['sessionId'], $alone['sessionId'], 'stejné číslo = žádný přesun');
        self::assertSame([96, 95], array_column($this->api('GET', "$base/sessions"), 'number'));
    }

    public function testGuestsCanBeRenamedAndDeletedOnlyWhenUnused(): void
    {
        $this->login('anna@example.com', 'Anna');
        $party = $this->api('POST', '/api/parties', ['name' => 'Pátek'], 201);
        $base = "/api/parties/{$party['id']}";
        $anna = $this->api('GET', "$base/players")[0];
        $bob = $this->api('POST', "$base/players", ['name' => 'Bob'], 201);
        $cyril = $this->api('POST', "$base/players", ['name' => 'Cyril'], 201);
        $dora = $this->api('POST', "$base/players", ['name' => 'Dora'], 201);
        $this->api('POST', "$base/players", ['name' => '   '], 422);
        self::assertSame([0, 0, 0, 0], array_column($this->api('GET', "$base/players"), 'playCount'));

        // přejmenování hosta; duplicitní nebo prázdné jméno a člen s účtem se odmítnou
        self::assertSame('Bobík', $this->api('PATCH', "/api/players/{$bob['id']}", ['name' => ' Bobík '])['name']);
        $this->api('PATCH', "/api/players/{$bob['id']}", ['name' => 'cyril'], 422);
        $this->api('PATCH', "/api/players/{$bob['id']}", ['name' => '  '], 422);
        $this->api('PATCH', "/api/players/{$anna['id']}", ['name' => 'Annie'], 422);

        // host, který hrál, ani člen s účtem se nesmaže; nepoužitý host ano
        $game = $this->api('POST', "$base/games", ['name' => 'Azul', 'scoreType' => 'winner_only'], 201);
        $this->api('POST', "$base/plays", [
            'gameId' => $game['id'], 'startedAt' => '2026-03-01T18:00:00+00:00', 'endedAt' => '2026-03-01T19:00:00+00:00',
            'players' => [['playerId' => $anna['id'], 'winner' => true], ['playerId' => $bob['id']]],
        ], 201);
        $counts = array_column($this->api('GET', "$base/players"), 'playCount', 'name');
        self::assertSame(['Anna' => 1, 'Bobík' => 1, 'Cyril' => 0, 'Dora' => 0], $counts);
        $this->api('DELETE', "/api/players/{$bob['id']}", expected: 422);
        $this->api('DELETE', "/api/players/{$anna['id']}", expected: 422);
        $this->api('POST', "$base/games", ['name' => 'Coop', 'scoreType' => 'winner_only', 'playerId' => $dora['id']], 201);
        $this->api('DELETE', "/api/players/{$dora['id']}", expected: 422); // hráč hry

        $this->login('eve@example.com', 'Eve');
        $this->api('DELETE', "/api/players/{$cyril['id']}", expected: 403);
        $this->api('PATCH', "/api/players/{$cyril['id']}", ['name' => 'X'], 403);

        $this->login('anna@example.com', 'Anna');
        $this->api('DELETE', "/api/players/{$cyril['id']}", expected: 204);
        self::assertSame(['Anna', 'Bobík', 'Dora'], array_column($this->api('GET', "$base/players"), 'name'));
        $this->api('DELETE', "/api/players/{$cyril['id']}", expected: 404);
    }

    public function testTagsAreListedForSuggestions(): void
    {
        $this->login('anna@example.com', 'Anna');
        $party = $this->api('POST', '/api/parties', ['name' => 'Pátek'], 201);
        $base = "/api/parties/{$party['id']}";
        $anna = $this->api('GET', "$base/players")[0]['id'];
        $game = $this->api('POST', "$base/games", ['name' => 'Azul', 'scoreType' => 'winner_only'], 201);
        self::assertSame([], $this->api('GET', "$base/tags"));

        $this->api('POST', "$base/plays", [
            'gameId' => $game['id'], 'startedAt' => '2026-03-01T18:00:00+00:00', 'endedAt' => '2026-03-01T19:00:00+00:00',
            'players' => [['playerId' => $anna, 'winner' => true]], 'tags' => ['víkend', 'Liga'],
        ], 201);

        self::assertSame(['Liga', 'víkend'], $this->api('GET', "$base/tags"));

        $this->login('eve@example.com', 'Eve');
        $this->api('GET', "$base/tags", expected: 403);
    }

    public function testStartingPlayerAndManualWinnerAreStoredAndUpdatable(): void
    {
        $this->login('anna@example.com', 'Anna');
        $party = $this->api('POST', '/api/parties', ['name' => 'Pátek'], 201);
        $base = "/api/parties/{$party['id']}";
        $anna = $this->api('GET', "$base/players")[0]['id'];
        $bob = $this->api('POST', "$base/players", ['name' => 'Bob'], 201)['id'];
        $game = $this->api('POST', "$base/games", ['name' => 'Azul', 'scoreType' => 'most_points'], 201);
        $payload = fn (array $players): array => [
            'gameId' => $game['id'], 'startedAt' => '2026-03-01T18:00:00+00:00', 'endedAt' => '2026-03-01T19:00:00+00:00', 'players' => $players,
        ];

        // shoda skóre: automaticky vyhrají oba, začíná Bob
        $play = $this->api('POST', "$base/plays", $payload([['playerId' => $anna, 'score' => 50], ['playerId' => $bob, 'score' => 50, 'starting' => true]]), 201);
        self::assertSame([true, true], array_column($play['results'], 'winner'));
        self::assertSame(['Bob'], array_column(array_filter($play['results'], static fn (array $r): bool => $r['starting']), 'name'));

        // ruční výběr: vyhrává jen Anna, začínající hráč se změní
        $edited = $this->api('PUT', "/api/plays/{$play['id']}", $payload([['playerId' => $anna, 'score' => 50, 'winner' => true, 'starting' => true], ['playerId' => $bob, 'score' => 50]]));
        $byName = array_column($edited['results'], null, 'name');
        self::assertTrue($byName['Anna']['winner']);
        self::assertFalse($byName['Bob']['winner']);
        self::assertTrue($byName['Anna']['starting']);
        self::assertFalse($byName['Bob']['starting']);
        self::assertSame(1, $byName['Bob']['rank'], 'rank follows the score, not the manual winner');

        // dva začínající hráči nejsou možní
        $this->api('POST', "$base/plays", $payload([['playerId' => $anna, 'score' => 1, 'starting' => true], ['playerId' => $bob, 'score' => 2, 'starting' => true]]), 422);
    }

    public function testGameShortNameCharactersOwnPlayerAndRenamesKeepLinks(): void
    {
        $this->login('anna@example.com', 'Anna');
        $party = $this->api('POST', '/api/parties', ['name' => 'Pátek'], 201);
        $base = "/api/parties/{$party['id']}";
        $anna = $this->api('GET', "$base/players")[0]['id'];
        $boss = $this->api('POST', "$base/players", ['name' => 'Boss'], 201)['id'];

        $game = $this->api('POST', "$base/games", [
            'name' => 'King of Tokyo', 'shortName' => ' Tokyo ', 'scoreType' => 'winner_only', 'playerId' => $boss,
            'addons' => [['name' => 'Power Up']], 'characters' => [['name' => 'Gigazaur'], ['name' => 'Kraken'], ['name' => '  ']],
        ], 201);
        self::assertSame('Tokyo', $game['shortName']);
        self::assertSame(['id' => $boss, 'name' => 'Boss'], $game['player']);
        self::assertSame(['Gigazaur', 'Kraken'], array_column($game['characters'], 'name'));

        // postava ve výsledku zápisu
        [$giga, $kraken] = array_column($game['characters'], 'id');
        $payload = fn (?int $character): array => [
            'gameId' => $game['id'], 'startedAt' => '2026-03-01T18:00:00+00:00', 'endedAt' => '2026-03-01T19:00:00+00:00',
            'addonIds' => [$game['addons'][0]['id']],
            'players' => [['playerId' => $anna, 'winner' => true, 'characterId' => $character], ['playerId' => $boss]],
        ];
        $play = $this->api('POST', "$base/plays", $payload($giga), 201);
        self::assertSame(['id' => $giga, 'name' => 'Gigazaur'], array_column($play['results'], 'character', 'name')['Anna']);
        self::assertNull(array_column($play['results'], 'character', 'name')['Boss']);

        // přejmenování podle id zachová id (vazby na zápisy), odebrání postavy ji z výsledku odpojí
        $updated = $this->api('PUT', "/api/games/{$game['id']}", [
            'name' => 'King of Tokyo', 'scoreType' => 'winner_only', 'shortName' => null, 'playerId' => null,
            'addons' => [['id' => $game['addons'][0]['id'], 'name' => 'Power Up 2']],
            'characters' => [['id' => $giga, 'name' => 'Giga'], ['name' => 'Alienoid']],
        ]);
        self::assertSame([$giga], array_slice(array_column($updated['characters'], 'id'), 0, 1), 'renamed, not recreated');
        self::assertSame(['Giga', 'Alienoid'], array_column($updated['characters'], 'name'));
        self::assertSame($game['addons'][0]['id'], $updated['addons'][0]['id']);
        self::assertNull($updated['shortName']);
        self::assertNull($updated['player']);
        $reloaded = $this->api('GET', "/api/plays/{$play['id']}");
        self::assertSame('Giga', array_column($reloaded['results'], 'character', 'name')['Anna']['name']);
        self::assertSame(['Power Up 2'], array_column($reloaded['addons'], 'name'));

        // postava z jiné hry a cizí hráč se odmítnou
        $other = $this->api('POST', "$base/games", ['name' => 'Jiná', 'scoreType' => 'winner_only', 'characters' => [['name' => 'X']]], 201);
        $this->api('POST', "$base/plays", $payload($other['characters'][0]['id']), 422);
        $foreign = $this->api('POST', '/api/parties', ['name' => 'Cizí'], 201);
        $foreignPlayer = $this->api('GET', "/api/parties/{$foreign['id']}/players")[0]['id'];
        $this->api('PUT', "/api/games/{$game['id']}", ['name' => 'King of Tokyo', 'scoreType' => 'winner_only', 'playerId' => $foreignPlayer], 422);

        // zkrácený název ve statistikách
        $this->api('PUT', "/api/games/{$game['id']}", ['name' => 'King of Tokyo', 'scoreType' => 'winner_only', 'shortName' => 'Tokyo', 'addons' => [['id' => $game['addons'][0]['id'], 'name' => 'Power Up 2']], 'characters' => [['id' => $giga, 'name' => 'Giga']]]);
        $stats = $this->api('GET', "$base/stats");
        self::assertSame(['Tokyo'], array_column($stats['topGames'], 'shortName'));
    }

    public function testGameImageUrlAcceptsHttpLinksAndLocalPathsOnly(): void
    {
        $this->login('anna@example.com', 'Anna');
        $party = $this->api('POST', '/api/parties', ['name' => 'Pátek'], 201);
        $base = "/api/parties/{$party['id']}";

        foreach (['https://example.com/a.jpg', '/static/covers/a.jpg', '', null] as $i => $url) {
            $this->api('POST', "$base/games", ['name' => "Hra $i", 'scoreType' => 'most_points', 'imageUrl' => $url], 201);
        }
        foreach (['javascript:alert(1)', '//evil.example/a.jpg', 'data:text/html;base64,AAAA', 'obrazek.jpg'] as $i => $url) {
            $this->api('POST', "$base/games", ['name' => "Zlá $i", 'scoreType' => 'most_points', 'imageUrl' => $url], 422);
        }
    }

    public function testInvalidPlayIsRejected(): void
    {
        $this->login('anna@example.com', 'Anna');
        $party = $this->api('POST', '/api/parties', ['name' => 'Pátek'], 201);
        $annaPlayer = $this->api('GET', "/api/parties/{$party['id']}/players")[0]['id'];
        $game = $this->api('POST', "/api/parties/{$party['id']}/games", ['name' => 'Dixit', 'scoreType' => 'most_points'], 201);

        $this->api('POST', "/api/parties/{$party['id']}/plays", [
            'gameId' => $game['id'], 'startedAt' => '2026-03-01T18:00:00+00:00', 'endedAt' => '2026-03-01T19:00:00+00:00',
            'players' => [['playerId' => $annaPlayer]],
        ], 422);
        $this->api('POST', "/api/parties/{$party['id']}/plays", [
            'gameId' => $game['id'], 'startedAt' => '2026-03-01T19:00:00+00:00', 'endedAt' => '2026-03-01T18:00:00+00:00',
            'players' => [['playerId' => $annaPlayer, 'score' => 1]],
        ], 422);
    }

    public function testPlayerFromAnotherPartyIsRejected(): void
    {
        $this->login('anna@example.com', 'Anna');
        $a = $this->api('POST', '/api/parties', ['name' => 'A'], 201);
        $b = $this->api('POST', '/api/parties', ['name' => 'B'], 201);
        $foreign = $this->api('GET', "/api/parties/{$b['id']}/players")[0]['id'];
        $game = $this->api('POST', "/api/parties/{$a['id']}/games", ['name' => 'G', 'scoreType' => 'most_points'], 201);

        $this->api('POST', "/api/parties/{$a['id']}/plays", [
            'gameId' => $game['id'], 'startedAt' => '2026-03-01T18:00:00+00:00', 'endedAt' => '2026-03-01T19:00:00+00:00',
            'players' => [['playerId' => $foreign, 'score' => 1]],
        ], 422);
    }

    public function testNicknameIsRequiredToBeSet(): void
    {
        $this->client->request('POST', '/api/auth/dev-login', server: ['CONTENT_TYPE' => 'application/json'], content: '{"email":"x@example.com"}');
        $me = $this->api('GET', '/api/me');
        self::assertFalse($me['hasNickname']);
        $me = $this->api('PATCH', '/api/me', ['nickname' => 'Xena']);
        self::assertTrue($me['hasNickname']);
    }
}
