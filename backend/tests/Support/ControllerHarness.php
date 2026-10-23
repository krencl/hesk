<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Controller\ApiController;
use App\Entity\User;
use App\Security\PartyVoter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authorization\Voter\RoleVoter;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Spouští akce kontrolerů přímo, bez HTTP a kernelu: přihlášený uživatel, skutečný `PartyVoter` a `RoleVoter` (role uživatele) a kontrola
 * atributů `#[IsGranted]` (ty by jinak vyhodnotil až kernel), takže se ověřují i oprávnění.
 */
final class ControllerHarness
{
    private readonly AuthorizationChecker $checker;
    private readonly TokenStorage $tokens;

    public function __construct(private readonly ?User $user = null, private readonly string $environment = 'test')
    {
        $tokens = new TokenStorage();
        if (null !== $user) {
            $tokens->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        }
        $this->checker = new AuthorizationChecker($tokens, new AccessDecisionManager([new PartyVoter(), new RoleVoter()]));
        $this->tokens = $tokens;
    }

    /** Přihlášený uživatel, kontrolery pak běží pod ním. */
    public static function as(User $user, string $environment = 'test'): self
    {
        return new self($user, $environment);
    }

    /**
     * @template T of ApiController
     *
     * @param T $controller
     *
     * @return T
     */
    public function wire(ApiController $controller): ApiController
    {
        $controller->setContainer(new ServiceLocator([
            'security.token_storage' => fn () => $this->tokens,
            'security.authorization_checker' => fn () => $this->checker,
            'parameter_bag' => fn () => new ParameterBag(['kernel.environment' => $this->environment]),
        ]));

        return $controller;
    }

    /**
     * Zavolá akci s pojmenovanými argumenty; před tím vyhodnotí `#[IsGranted]` nad metodou (subjekt = argument
     * s daným jménem), jako by to udělal kernel.
     *
     * @param array<string, mixed> $args
     */
    public function call(ApiController $controller, string $action, array $args = []): mixed
    {
        $this->wire($controller);
        foreach ((new \ReflectionMethod($controller, $action))->getAttributes(IsGranted::class) as $attribute) {
            /** @var IsGranted $granted */
            $granted = $attribute->newInstance();
            if (!$this->checker->isGranted($granted->attribute, $args[$granted->subject] ?? null)) {
                throw new AccessDeniedException();
            }
        }

        return $controller->$action(...$args);
    }

    /** @return array<mixed> */
    public static function json(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
