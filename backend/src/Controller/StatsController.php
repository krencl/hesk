<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Party;
use App\Repository\PlayRepository;
use App\Repository\StatsFilter;
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
        $filter = new StatsFilter(
            $this->names($request, 'tags'),
            $this->names($request, 'excludeTags'),
            $this->ids($request, 'games'),
            $this->ids($request, 'excludeGames'),
        );

        return new JsonResponse([
            'wins' => $this->plays->winsPerPlayer($party, $filter),
            'topGames' => $this->plays->topGames($party, 10, $filter),
            'starters' => $this->plays->startingPerPlayer($party, $filter),
            'playTags' => $this->plays->topPlayTags($party, 10, $filter),
        ]);
    }

    /** Statistiky přihlášeného člena v partě: hry s nejvíc výhrami a výhry podle tagů her. */
    #[Route('/api/parties/{id}/stats/me', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(PartyVoter::VIEW, 'party')]
    public function mine(Party $party): JsonResponse
    {
        $player = $party->findPlayerFor($this->currentUser());
        if (null === $player) {
            return new JsonResponse(['player' => null, 'plays' => 0, 'wins' => 0, 'topGames' => [], 'gameTags' => []]);
        }
        $totals = array_values(array_filter($this->plays->winsPerPlayer($party), static fn (array $w): bool => $w['playerId'] === $player->getId()))[0] ?? null;

        return new JsonResponse([
            'player' => ['id' => $player->getId(), 'name' => $player->getName()],
            'plays' => $totals['plays'] ?? 0,
            'wins' => $totals['wins'] ?? 0,
            'topGames' => $this->plays->topWinningGamesOfPlayer($party, $player),
            'gameTags' => $this->plays->winsOfPlayerByGameTag($party, $player),
        ]);
    }

    /**
     * Filtr podle her a tagů her (`tags[]`, `excludeTags[]`, `games[]`, `excludeGames[]`), viz `StatsFilter`.
     *
     * @return list<string>
     */
    private function names(Request $request, string $key): array
    {
        $names = array_filter(array_map(static fn (mixed $n): string => trim((string) $n), $request->query->all($key)), static fn (string $n): bool => '' !== $n);

        return array_values(array_unique($names));
    }

    /** @return list<int> */
    private function ids(Request $request, string $key): array
    {
        return array_values(array_unique(array_map('intval', array_filter($request->query->all($key), static fn (mixed $v): bool => is_numeric($v)))));
    }
}
