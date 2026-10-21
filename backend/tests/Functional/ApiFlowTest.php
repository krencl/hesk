<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

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

        // nový člen zápisy jen čte; admin ho povýší na zapisovače (podrobně testMembersCannotWritePlaysButWritersCan)
        $this->login('anna@example.com', 'Anna');
        $bobUser = array_values(array_filter($this->api('GET', "/api/parties/{$party['id']}")['members'], static fn (array $m): bool => 'Bob' === $m['nickname']))[0];
        $this->api('PATCH', "/api/parties/{$party['id']}/members/{$bobUser['userId']}", ['role' => 'writer']);
        $this->login('bob@example.com', 'Bob');

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
        $this->api('PATCH', "/api/players/{$anna['id']}", ['visible' => false], 422);
        $this->api('PATCH', "/api/players/{$bob['id']}", [], 422);

        // příznak „zobrazovat“: výchozí true, mění se samostatně (jméno zůstane), vrací ho i seznam hráčů
        self::assertSame([true, true, true, true], array_column($this->api('GET', "$base/players"), 'visible'));
        $hidden = $this->api('PATCH', "/api/players/{$cyril['id']}", ['visible' => false]);
        self::assertSame(['Cyril', false], [$hidden['name'], $hidden['visible']]);
        self::assertSame(['Anna' => true, 'Bobík' => true, 'Cyril' => false, 'Dora' => true], array_column($this->api('GET', "$base/players"), 'visible', 'name'));
        self::assertTrue($this->api('PATCH', "/api/players/{$cyril['id']}", ['name' => 'Cyril', 'visible' => true])['visible']);

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
        $this->api('POST', "$base/games", ['name' => 'Coop', 'scoreType' => 'winner_only', 'players' => [['id' => $dora['id'], 'name' => 'Dora']]], 201);
        $this->api('DELETE', "/api/players/{$dora['id']}", expected: 422); // hráč hry

        $this->login('eve@example.com', 'Eve');
        $this->api('DELETE', "/api/players/{$cyril['id']}", expected: 403);
        $this->api('PATCH', "/api/players/{$cyril['id']}", ['name' => 'X'], 403);

        $this->login('anna@example.com', 'Anna');
        $this->api('DELETE', "/api/players/{$cyril['id']}", expected: 204);
        self::assertSame(['Anna', 'Bobík', 'Dora'], array_column($this->api('GET', "$base/players"), 'name'));
        $this->api('DELETE', "/api/players/{$cyril['id']}", expected: 404);
    }

    public function testMembersCannotWritePlaysButWritersCan(): void
    {
        $this->login('anna@example.com', 'Anna');
        $party = $this->api('POST', '/api/parties', ['name' => 'Pátek'], 201);
        $base = "/api/parties/{$party['id']}";
        $anna = $this->api('GET', "$base/players")[0]['id'];
        $game = $this->api('POST', "$base/games", ['name' => 'Azul', 'scoreType' => 'winner_only'], 201);
        $payload = ['gameId' => $game['id'], 'startedAt' => '2026-03-01T18:00:00+00:00', 'endedAt' => '2026-03-01T19:00:00+00:00', 'players' => [['playerId' => $anna, 'winner' => true]]];
        $play = $this->api('POST', "$base/plays", $payload, 201);

        // nově připojený člen je jen „člen“: zápisy čte, ale nezapisuje
        $invite = $this->api('POST', "$base/invitations", null, 201);
        $this->login('bob@example.com', 'Bob');
        $this->api('POST', "/api/invitations/{$invite['token']}/accept");
        self::assertSame('member', $this->api('GET', $base)['role']);
        self::assertCount(1, $this->api('GET', "$base/plays"));
        $this->api('GET', "/api/plays/{$play['id']}");
        $this->api('POST', "$base/plays", $payload, 403);
        $this->api('GET', "$base/plays/defaults?startedAt=".rawurlencode('2026-03-02T18:00:00+00:00'), expected: 403);
        $this->api('PUT', "/api/plays/{$play['id']}", $payload, 403);
        $this->api('DELETE', "/api/plays/{$play['id']}", expected: 403);

        // admin ho povýší na zapisovače → zapisuje, ale partu nespravuje
        $this->login('anna@example.com', 'Anna');
        $bobUser = array_values(array_filter($this->api('GET', $base)['members'], static fn (array $m): bool => 'Bob' === $m['nickname']))[0];
        self::assertSame('writer', $this->api('PATCH', "$base/members/{$bobUser['userId']}", ['role' => 'writer'])['members'][1]['role']);
        $this->login('bob@example.com', 'Bob');
        $second = $this->api('POST', "$base/plays", ['startedAt' => '2026-03-05T18:00:00+00:00', 'endedAt' => '2026-03-05T19:00:00+00:00'] + $payload, 201);
        $this->api('PUT', "/api/plays/{$second['id']}", $payload, 200);
        $this->api('DELETE', "/api/plays/{$second['id']}", expected: 204);
        $this->api('POST', "$base/invitations", null, 403);
        $this->api('PATCH', "$base/members/{$bobUser['userId']}", ['role' => 'admin'], 403);

        // poslední admin se nedá změnit na zapisovače ani na člena
        $this->login('anna@example.com', 'Anna');
        $annaUser = array_values(array_filter($this->api('GET', $base)['members'], static fn (array $m): bool => 'Anna' === $m['nickname']))[0];
        $this->api('PATCH', "$base/members/{$annaUser['userId']}", ['role' => 'writer'], 422);
        $this->api('PATCH', "$base/members/{$annaUser['userId']}", ['role' => 'member'], 422);
        $this->api('PATCH', "$base/members/{$annaUser['userId']}", ['role' => 'neznámá'], 422);
    }

    /** @return array{party: array, base: string, anna: array, game: array, play: callable} */
    private function partyWithGame(): array
    {
        $this->login('anna@example.com', 'Anna');
        $party = $this->api('POST', '/api/parties', ['name' => 'Pátek'], 201);
        $base = "/api/parties/{$party['id']}";
        $anna = $this->api('GET', "$base/players")[0];
        $game = $this->api('POST', "$base/games", ['name' => 'Azul', 'scoreType' => 'winner_only'], 201);
        $play = fn (string $day, array $playerIds): array => $this->api('POST', "$base/plays", [
            'gameId' => $game['id'], 'startedAt' => "{$day}T18:00:00+00:00", 'endedAt' => "{$day}T19:00:00+00:00",
            'players' => array_map(static fn (int $id): array => ['playerId' => $id, 'winner' => $id === $playerIds[0]], $playerIds),
        ], 201);

        return ['party' => $party, 'base' => $base, 'anna' => $anna, 'game' => $game, 'play' => $play];
    }

    public function testAdminLinksAGuestToAnExistingMember(): void
    {
        ['party' => $party, 'base' => $base, 'anna' => $anna, 'game' => $game, 'play' => $play] = $this->partyWithGame();
        $invite = $this->api('POST', "$base/invitations", null, 201);
        $this->login('bob@example.com', 'Bob');
        $this->api('POST', "/api/invitations/{$invite['token']}/accept");
        $this->login('anna@example.com', 'Anna');
        $players = array_column($this->api('GET', "$base/players"), null, 'name');
        $bob = $players['Bob'];
        $bobUser = array_values(array_filter($this->api('GET', $base)['members'], static fn (array $m): bool => 'Bob' === $m['nickname']))[0];
        $host = $this->api('POST', "$base/players", ['name' => 'Bobík'], 201);
        $coop = $this->api('POST', "$base/games", ['name' => 'Coop', 'scoreType' => 'winner_only', 'players' => [['id' => $host['id'], 'name' => 'Bobík']]], 201);
        $play('2026-03-01', [$host['id'], $anna['id']]);
        $play('2026-03-02', [$host['id']]);
        $play('2026-03-03', [$bob['id']]);

        // jen admin; cílem musí být člen party
        $this->login('bob@example.com', 'Bob');
        $this->api('POST', "/api/players/{$host['id']}/link-member", ['userId' => $bobUser['userId']], 403);
        $this->login('anna@example.com', 'Anna');
        $this->api('POST', "/api/players/{$host['id']}/link-member", ['userId' => 999999], 422);
        $this->api('POST', "/api/players/{$anna['id']}/link-member", ['userId' => $bobUser['userId']], 422); // člen s účtem není host

        $linked = $this->api('POST', "/api/players/{$host['id']}/link-member", ['userId' => $bobUser['userId']]);
        self::assertSame(['Bob', 3], [$linked['name'], $linked['playCount']], 'zápisy hosta přešly na člena (2 + 1 vlastní)');
        $after = array_column($this->api('GET', "$base/players"), 'playCount', 'name');
        self::assertSame(['Anna' => 1, 'Bob' => 3], $after, 'host zmizel');
        self::assertSame([$bob['id']], array_column($this->api('GET', "/api/games/{$coop['id']}")['players'], 'id'), 'hráč hry se přepsal na člena');
        self::assertSame(1, \count(array_filter($this->api('GET', "$base/plays?limit=10"), static fn (array $p): bool => \in_array('Bob', array_column($p['results'], 'name'), true) && \in_array('Anna', array_column($p['results'], 'name'), true))));
    }

    public function testGuestAndMemberWhoPlayedTheSamePlayCannotBeLinked(): void
    {
        ['base' => $base, 'anna' => $anna, 'play' => $play] = $this->partyWithGame();
        $host = $this->api('POST', "$base/players", ['name' => 'Anička'], 201);
        $play('2026-03-01', [$anna['id'], $host['id']]);
        $annaUser = $this->api('GET', $base)['members'][0];

        $error = $this->api('POST', "/api/players/{$host['id']}/link-member", ['userId' => $annaUser['userId']], 422);
        self::assertStringContainsString('stejných zápisech', $error['error']);
        self::assertCount(2, $this->api('GET', "$base/players"), 'nic se nezměnilo');
    }

    public function testInvitationForAGuestLinksHimWhenAccepted(): void
    {
        ['party' => $party, 'base' => $base, 'anna' => $anna, 'game' => $game, 'play' => $play] = $this->partyWithGame();
        $host = $this->api('POST', "$base/players", ['name' => 'Bob'], 201);
        $play('2026-03-01', [$host['id'], $anna['id']]);
        $play('2026-03-02', [$host['id']]);

        // pozvat jde jen hosta z této party
        $this->api('POST', "$base/invitations", ['guestId' => $anna['id']], 422);
        $this->api('POST', "$base/invitations", ['guestId' => 999999], 422);
        $invite = $this->api('POST', "$base/invitations", ['guestId' => $host['id']], 201);

        $this->login('bob@example.com', 'Bob');
        $joined = $this->api('POST', "/api/invitations/{$invite['token']}/accept");
        self::assertSame('Bob', $joined['linkedGuest']);
        $this->login('anna@example.com', 'Anna');
        $players = $this->api('GET', "$base/players");
        self::assertSame(['Anna' => 1, 'Bob' => 2], array_column($players, 'playCount', 'name'), 'host zmizel, jeho zápisy má člen Bob (jméno bez „Bob 2“)');
        self::assertTrue(array_column($players, 'hasAccount', 'name')['Bob']);

        // další člověk, který stejný odkaz použije, se jen připojí (host už není)
        $this->login('eve@example.com', 'Eve');
        self::assertNull($this->api('POST', "/api/invitations/{$invite['token']}/accept")['linkedGuest']);
    }

    public function testInvitationForAGuestStillJoinsWhenThereIsNothingToMerge(): void
    {
        ['base' => $base, 'anna' => $anna, 'play' => $play] = $this->partyWithGame();
        $host = $this->api('POST', "$base/players", ['name' => 'Cyril'], 201);
        $invite = $this->api('POST', "$base/invitations", ['guestId' => $host['id']], 201);
        // host Cyril je smazán dřív, než pozvánku někdo přijme
        $this->api('DELETE', "/api/players/{$host['id']}", expected: 204);

        $this->login('cyril@example.com', 'Cyril');
        $joined = $this->api('POST', "/api/invitations/{$invite['token']}/accept");
        self::assertNull($joined['linkedGuest']);
        self::assertContains('Cyril', array_column($joined['members'], 'nickname'));
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
            'name' => 'King of Tokyo', 'shortName' => ' Tokyo ', 'scoreType' => 'winner_only', 'players' => [['id' => $boss, 'name' => 'Boss']],
            'addons' => [['name' => 'Power Up']], 'characters' => [['name' => 'Gigazaur'], ['name' => 'Kraken'], ['name' => '  ']],
        ], 201);
        self::assertSame('Tokyo', $game['shortName']);
        self::assertSame([['id' => $boss, 'name' => 'Boss']], $game['players']);
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
            'name' => 'King of Tokyo', 'scoreType' => 'winner_only', 'shortName' => null, 'players' => [],
            'addons' => [['id' => $game['addons'][0]['id'], 'name' => 'Power Up 2']],
            'characters' => [['id' => $giga, 'name' => 'Giga'], ['name' => 'Alienoid']],
        ]);
        self::assertSame([$giga], array_slice(array_column($updated['characters'], 'id'), 0, 1), 'renamed, not recreated');
        self::assertSame(['Giga', 'Alienoid'], array_column($updated['characters'], 'name'));
        self::assertSame($game['addons'][0]['id'], $updated['addons'][0]['id']);
        self::assertNull($updated['shortName']);
        self::assertSame([], $updated['players']);
        $reloaded = $this->api('GET', "/api/plays/{$play['id']}");
        self::assertSame('Giga', array_column($reloaded['results'], 'character', 'name')['Anna']['name']);
        self::assertSame(['Power Up 2'], array_column($reloaded['addons'], 'name'));

        // postava z jiné hry a cizí hráč se odmítnou
        $other = $this->api('POST', "$base/games", ['name' => 'Jiná', 'scoreType' => 'winner_only', 'characters' => [['name' => 'X']]], 201);
        $this->api('POST', "$base/plays", $payload($other['characters'][0]['id']), 422);
        $foreign = $this->api('POST', '/api/parties', ['name' => 'Cizí'], 201);
        $foreignPlayer = $this->api('GET', "/api/parties/{$foreign['id']}/players")[0]['id'];
        $this->api('PUT', "/api/games/{$game['id']}", ['name' => 'King of Tokyo', 'scoreType' => 'winner_only', 'players' => [['id' => $foreignPlayer, 'name' => 'Cizí']]], 422);

        // zkrácený název ve statistikách
        $this->api('PUT', "/api/games/{$game['id']}", ['name' => 'King of Tokyo', 'scoreType' => 'winner_only', 'shortName' => 'Tokyo', 'addons' => [['id' => $game['addons'][0]['id'], 'name' => 'Power Up 2']], 'characters' => [['id' => $giga, 'name' => 'Giga']]]);
        $stats = $this->api('GET', "$base/stats");
        self::assertSame(['Tokyo'], array_column($stats['topGames'], 'shortName'));
    }

    public function testGameCanHaveSeveralOwnPlayersEditedByNameLikeCharacters(): void
    {
        $this->login('anna@example.com', 'Anna');
        $party = $this->api('POST', '/api/parties', ['name' => 'Pátek'], 201);
        $base = "/api/parties/{$party['id']}";
        $anna = $this->api('GET', "$base/players")[0];
        $boss = $this->api('POST', "$base/players", ['name' => 'Boss'], 201);

        // existující host podle id, nový podle jména (vznikne host), jméno existujícího hráče se spáruje, prázdné a duplicitní se zahodí
        $game = $this->api('POST', "$base/games", [
            'name' => 'Coop', 'scoreType' => 'winner_only',
            'players' => [['id' => $boss['id'], 'name' => 'Boss'], ['name' => 'Minion'], ['name' => 'minion'], ['name' => ' '], ['name' => 'anna']],
        ], 201);
        $byName = array_column($game['players'], 'id', 'name');
        self::assertSame(['Boss', 'Minion', 'Anna'], array_keys($byName));
        self::assertSame($boss['id'], $byName['Boss']);
        self::assertSame($anna['id'], $byName['Anna'], 'jméno člena se spáruje s jeho hráčem, nový se nezakládá');
        self::assertSame(['Anna', 'Boss', 'Minion'], array_column($this->api('GET', "$base/players"), 'name'));

        // přejmenování podle id zachová hráče, odebraný zůstává v partě; člena s účtem přejmenovat nejde
        $updated = $this->api('PUT', "/api/games/{$game['id']}", [
            'name' => 'Coop', 'scoreType' => 'winner_only',
            'players' => [['id' => $boss['id'], 'name' => 'Velký Boss'], ['id' => $byName['Minion'], 'name' => 'Minion']],
        ]);
        self::assertSame([['id' => $boss['id'], 'name' => 'Velký Boss'], ['id' => $byName['Minion'], 'name' => 'Minion']], $updated['players']);
        self::assertSame(['Anna', 'Minion', 'Velký Boss'], array_column($this->api('GET', "$base/players"), 'name'));
        $this->api('PUT', "/api/games/{$game['id']}", ['name' => 'Coop', 'scoreType' => 'winner_only', 'players' => [['id' => $anna['id'], 'name' => 'Jiná']]], 422);
        $this->api('PUT', "/api/games/{$game['id']}", ['name' => 'Coop', 'scoreType' => 'winner_only', 'players' => [['id' => $boss['id'], 'name' => 'Anna']]], 422);

        // hráč hry se nesmaže, dokud u hry je; po odebrání ano
        $this->api('DELETE', "/api/players/{$byName['Minion']}", expected: 422);
        $this->api('PUT', "/api/games/{$game['id']}", ['name' => 'Coop', 'scoreType' => 'winner_only', 'players' => [['id' => $boss['id'], 'name' => 'Velký Boss']]]);
        $this->api('DELETE', "/api/players/{$byName['Minion']}", expected: 204);
    }

    public function testGameImageUploadAcceptsOnlySquareImagesAndReturnsAUsableUrl(): void
    {
        $this->login('anna@example.com', 'Anna');
        $party = $this->api('POST', '/api/parties', ['name' => 'Pátek'], 201);
        $upload = function (string $png, string $name = 'cover.png') use ($party): int {
            $path = tempnam(sys_get_temp_dir(), 'cover');
            file_put_contents($path, $png);
            $this->client->request('POST', "/api/parties/{$party['id']}/game-images", files: ['file' => new UploadedFile($path, $name, null, null, true)]);

            return $this->client->getResponse()->getStatusCode();
        };

        self::assertSame(201, $upload(self::png(200, 200)));
        $url = json_decode($this->client->getResponse()->getContent(), true)['url'];
        self::assertMatchesRegularExpression('~^/uploads/covers/[0-9a-f]{40}\.png$~', $url);
        self::assertFileExists(static::getContainer()->getParameter('app.uploads_dir').'/covers/'.basename($url));
        self::assertSame(201, $upload(self::png(200, 200)), 'stejný obrázek = stejná adresa');
        self::assertSame($url, json_decode($this->client->getResponse()->getContent(), true)['url']);

        // adresu jde uložit k hře
        $game = $this->api('POST', "/api/parties/{$party['id']}/games", ['name' => 'Hra', 'scoreType' => 'most_points', 'imageUrl' => $url], 201);
        self::assertSame($url, $game['imageUrl']);

        self::assertSame(422, $upload(self::png(200, 100)), 'není čtverec');
        self::assertSame(422, $upload(self::png(50, 50)), 'příliš malý');
        self::assertSame(422, $upload('<?php echo 1;', 'cover.png'), 'není obrázek');
        self::assertSame(422, $upload('GIF89a'.str_repeat("\0", 20), 'cover.gif'), 'nepodporovaný formát');

        $this->client->request('POST', "/api/parties/{$party['id']}/game-images");
        self::assertSame(422, $this->client->getResponse()->getStatusCode(), 'bez souboru');

        // jen člen party
        $this->login('eve@example.com', 'Eve');
        self::assertSame(403, $upload(self::png(200, 200)));
    }

    /** Jednobarevné PNG zadaného rozměru bez závislosti na GD. */
    private static function png(int $width, int $height): string
    {
        $chunk = static fn (string $type, string $data): string => pack('N', \strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $rows = str_repeat("\0".str_repeat("\x20\x60\xa0", $width), $height);

        return "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0)).$chunk('IDAT', (string) gzcompress($rows)).$chunk('IEND', '');
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
