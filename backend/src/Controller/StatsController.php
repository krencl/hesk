<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Party;
use App\Repository\PlayRepository;
use App\Security\PartyVoter;
use App\Service\Presenter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class StatsController extends ApiController
{
    public function __construct(Presenter $presenter, private readonly PlayRepository $plays)
    {
        parent::__construct($presenter);
    }

    #[Route('/api/parties/{id}/stats', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(PartyVoter::VIEW, 'party')]
    public function __invoke(Party $party): JsonResponse
    {
        return new JsonResponse([
            'wins' => $this->plays->winsPerPlayer($party),
            'topGames' => $this->plays->topGames($party),
        ]);
    }
}
