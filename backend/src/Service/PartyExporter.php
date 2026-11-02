<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\PartyExportFilter;
use App\Entity\Game;
use App\Entity\GameSession;
use App\Entity\Party;
use App\Entity\Play;
use App\Entity\PlayResult;
use App\Repository\GameRepository;
use App\Repository\PlayRepository;
use App\Repository\PlayTagRepository;
use Doctrine\Common\Collections\Collection;

/**
 * Party data as one JSON-ready document (ADR 0033): every entity has its own top-level key and entities refer to each
 * other only by id. The filter limits just `sessions` and `plays`; players, games and tags are always exported whole
 * (game tags only those of exported games – the table is shared by all parties). Accounts are not exported.
 */
final class PartyExporter
{
    public const FORMAT = 'hesk-party-export';
    public const VERSION = 1;

    public function __construct(
        private readonly PlayRepository $plays,
        private readonly GameRepository $games,
        private readonly PlayTagRepository $playTags,
    ) {
    }

    /** @return array<string, mixed> */
    public function export(Party $party, PartyExportFilter $filter, \DateTimeImmutable $now): array
    {
        $plays = $this->plays->findForExport($party, $filter);

        // the games enrolled in the party; a game of an exported play is always enrolled too (it cannot be removed otherwise)
        $games = [];
        foreach ($party->getGameLinks() as $link) {
            $games[self::id($link->getGame())] = $link->getGame();
        }
        foreach ($plays as $play) {
            $games[self::id($play->getGame())] ??= $play->getGame();
        }
        ksort($games);
        $games = array_values($games);
        $this->games->preloadDetails($party, $games);

        $addons = $characters = $gameTags = [];
        foreach ($games as $game) {
            foreach ($game->getAddons() as $addon) {
                $addons[] = ['id' => self::id($addon), 'gameId' => self::id($game), 'name' => $addon->getName()];
            }
            foreach ($game->getCharacters() as $character) {
                $characters[] = ['id' => self::id($character), 'gameId' => self::id($game), 'name' => $character->getName()];
            }
            foreach ($game->getTags() as $tag) {
                $gameTags[self::id($tag)] = ['id' => self::id($tag), 'name' => $tag->getName()];
            }
        }
        ksort($gameTags);

        $sessions = [];
        foreach ($plays as $play) {
            $sessions[self::id($play->getSession())] = $play->getSession();
        }
        usort($sessions, static fn (GameSession $a, GameSession $b): int => [$a->getNumber(), self::id($a)] <=> [$b->getNumber(), self::id($b)]);

        return [
            'meta' => [
                'format' => self::FORMAT,
                'version' => self::VERSION,
                'exportedAt' => $now->format(\DATE_ATOM),
                'party' => ['id' => self::id($party), 'name' => $party->getName(), 'shortName' => $party->getShortName()],
                'filter' => $filter->toArray(),
            ],
            'players' => array_map(
                static fn ($player): array => ['id' => self::id($player), 'name' => $player->getName(), 'visible' => $player->isVisible()],
                self::byId($party->getPlayers()->toArray()),
            ),
            'games' => array_map(static fn (Game $game): array => [
                'id' => self::id($game),
                'name' => $game->getName(),
                'shortName' => $game->getShortName(),
                'scoreType' => $game->getScoreType()->value,
                'bggId' => $game->getBggId(),
                'imageUrl' => $game->getImageUrl(),
                'tagIds' => self::ids($game->getTags()),
            ], $games),
            'gameAddons' => $addons,
            'gameCharacters' => $characters,
            'gameTags' => array_values($gameTags),
            'partyGames' => array_map(static fn ($link): array => [
                'gameId' => self::id($link->getGame()),
                'playerIds' => self::ids($link->getPlayers()),
            ], self::sortedLinks($party)),
            'playTags' => array_map(
                static fn ($tag): array => ['id' => self::id($tag), 'name' => $tag->getName()],
                $this->playTags->findBy(['party' => $party], ['id' => 'ASC']),
            ),
            'sessions' => array_map(static fn (GameSession $session): array => [
                'id' => self::id($session),
                'number' => $session->getNumber(),
                'startedAt' => $session->getStartedAt()->format(\DATE_ATOM),
                'endedAt' => $session->getEndedAt()->format(\DATE_ATOM),
            ], $sessions),
            'plays' => array_map(static fn (Play $play): array => [
                'id' => self::id($play),
                'gameId' => self::id($play->getGame()),
                'sessionId' => self::id($play->getSession()),
                'startedAt' => $play->getStartedAt()->format(\DATE_ATOM),
                'endedAt' => $play->getEndedAt()->format(\DATE_ATOM),
                'notes' => $play->getNotes(),
                'addonIds' => self::ids($play->getAddons()),
                'tagIds' => self::ids($play->getTags()),
                'results' => self::results($play),
            ], $plays),
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function results(Play $play): array
    {
        $results = $play->getResults()->toArray();
        usort($results, static fn (PlayResult $a, PlayResult $b): int => [$a->getRank(), self::id($a->getPlayer())] <=> [$b->getRank(), self::id($b->getPlayer())]);

        return array_map(static fn (PlayResult $r): array => [
            'playerId' => self::id($r->getPlayer()),
            'score' => $r->getScore(),
            'rank' => $r->getRank(),
            'winner' => $r->isWinner(),
            'starting' => $r->isStarting(),
            'characterId' => null === $r->getCharacter() ? null : self::id($r->getCharacter()),
        ], $results);
    }

    /** @return list<\App\Entity\PartyGame> */
    private static function sortedLinks(Party $party): array
    {
        $links = $party->getGameLinks()->toArray();
        usort($links, static fn ($a, $b): int => self::id($a->getGame()) <=> self::id($b->getGame()));

        return $links;
    }

    /**
     * @param list<object> $entities
     *
     * @return list<object>
     */
    private static function byId(array $entities): array
    {
        usort($entities, static fn (object $a, object $b): int => self::id($a) <=> self::id($b));

        return $entities;
    }

    /**
     * @param Collection<int, object> $entities
     *
     * @return list<int> ids in ascending order
     */
    private static function ids(Collection $entities): array
    {
        $ids = array_map(self::id(...), $entities->toArray());
        sort($ids);

        return $ids;
    }

    private static function id(object $entity): int
    {
        \assert(method_exists($entity, 'getId'));

        return (int) $entity->getId();
    }
}
