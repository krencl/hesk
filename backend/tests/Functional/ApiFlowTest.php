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
            'name' => 'Carcassonne', 'scoreType' => 'most_points', 'addons' => ['Rivers'],
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
            'place' => 'Doma',
            'tags' => ['víkend'],
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
