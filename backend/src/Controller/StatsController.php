<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Party;
use App\Repository\PlayRepository;
use App\Security\PartyVoter;
use App\Service\Presenter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
    public function __invoke(Party $party, Request $request): JsonResponse
    {
        $include = $this->tagNames($request, 'tags');
        $exclude = $this->tagNames($request, 'excludeTags');

        return new JsonResponse([
            'wins' => $this->plays->winsPerPlayer($party, $include, $exclude),
            'topGames' => $this->plays->topGames($party, 10, $include, $exclude),
        ]);
    }

    /**
     * Filtr podle tagů her: `tags[]` = jen hry s aspoň jedním z nich, `excludeTags[]` = bez her s kterýmkoli z nich.
     *
     * @return list<string>
     */
    private function tagNames(Request $request, string $key): array
    {
        $names = array_filter(array_map(static fn (mixed $n): string => trim((string) $n), (array) $request->query->all($key)), static fn (string $n): bool => '' !== $n);

        return array_values(array_unique($names));
    }
}
