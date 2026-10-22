<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\JsonEntryPoint;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;

/** Přihlášení k API: nepřihlášený dostane 401 JSON a konfigurace pustí bez přihlášení jen health a auth. */
final class ApiAccessTest extends TestCase
{
    public function testEntryPointAnswers401WithJsonInsteadOfRedirecting(): void
    {
        $response = (new JsonEntryPoint())->start(Request::create('/api/parties'));

        self::assertSame(401, $response->getStatusCode());
        self::assertSame(['error' => 'Unauthorized'], json_decode((string) $response->getContent(), true));
    }

    public function testOnlyHealthAndAuthArePublicAndEverythingElseUnderApiNeedsLogin(): void
    {
        $config = Yaml::parseFile(\dirname(__DIR__, 3).'/config/packages/security.yaml');
        $rules = array_map(static fn (array $r): array => [$r['path'], $r['roles']], $config['security']['access_control']);

        // pravidla se vyhodnocují shora, první shoda vyhrává
        $roleFor = static function (string $path) use ($rules): ?string {
            foreach ($rules as [$pattern, $role]) {
                if (1 === preg_match('~'.$pattern.'~', $path)) {
                    return $role;
                }
            }

            return null;
        };

        self::assertSame('PUBLIC_ACCESS', $roleFor('/api/health'));
        self::assertSame('PUBLIC_ACCESS', $roleFor('/api/auth/dev-login'));
        foreach (['/api/parties', '/api/me', '/api/bgg/image', '/api/plays/1', '/api/games/3', '/api/players/2', '/api/invitations/abc/accept'] as $path) {
            self::assertSame('IS_AUTHENTICATED', $roleFor($path), $path);
        }
    }
}
