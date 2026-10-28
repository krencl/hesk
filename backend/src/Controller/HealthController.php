<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    public function __construct(#[Autowire('%kernel.environment%')] private readonly string $environment)
    {
    }

    #[Route('/api/health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok', 'env' => $this->environment]);
    }
}
