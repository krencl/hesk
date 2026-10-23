<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Game;
use App\Entity\Party;
use App\Entity\PartyGame;
use App\Entity\Play;
use App\Entity\PlayResult;
use App\Entity\User;

/** Převod entit na pole pro JSON odpovědi API. */
final class Presenter
{
    public function user(User $user): array
    {
        return ['id' => $user->getId(), 'nickname' => $user->getNickname(), 'email' => $user->getEmail()];
    }

    public function me(User $user): array
    {
        return $this->user($user) + ['hasNickname' => $user->hasNickname(), 'roles' => $user->getRoles()];
    }

    public function party(Party $party, ?User $viewer = null): array
    {
        $data = ['id' => $party->getId(), 'name' => $party->getName(), 'shortName' => $party->getShortName()];
        if ($viewer) {
            $data['role'] = $party->findMember($viewer)?->getRole()->value;
        }

        return $data;
    }

    public function partyDetail(Party $party, User $viewer): array
    {
        return $this->party($party, $viewer) + ['members' => array_values(array_map(
            fn ($m): array => ['userId' => $m->getUser()->getId(), 'nickname' => $m->getUser()->getNickname(), 'role' => $m->getRole()->value],
            $party->getMembers()->toArray(),
        ))];
    }

    public function player(\App\Entity\Player $player): array
    {
        return ['id' => $player->getId(), 'name' => $player->getName(), 'hasAccount' => null !== $player->getUser(), 'visible' => $player->isVisible()];
    }

    /** Sdílená hra (vč. tagů); `$link` (zařazení do party) přidá vlastní hráče hry v té partě. */
    public function game(Game $game, ?PartyGame $link = null): array
    {
        return [
            'id' => $game->getId(),
            'name' => $game->getName(),
            'scoreType' => $game->getScoreType()->value,
            'imageUrl' => $game->getImageUrl(),
            'bggId' => $game->getBggId(),
            'description' => $game->getDescription(),
            'cheatSheet' => $game->getCheatSheet(),
            'shortName' => $game->getShortName(),
            'addons' => array_values(array_map(
                static fn ($a): array => ['id' => $a->getId(), 'name' => $a->getName()],
                $game->getAddons()->toArray(),
            )),
            'characters' => array_values(array_map(
                static fn ($c): array => ['id' => $c->getId(), 'name' => $c->getName()],
                $game->getCharacters()->toArray(),
            )),
            'players' => array_values(array_map(
                static fn ($p): array => ['id' => $p->getId(), 'name' => $p->getName()],
                null === $link ? [] : $link->getPlayers()->toArray(),
            )),
            'tags' => $this->gameTagNames($game),
        ];
    }

    /** @return list<string> */
    private function gameTagNames(Game $game): array
    {
        $names = array_map(static fn ($t): string => $t->getName(), $game->getTags()->toArray());
        usort($names, static fn (string $a, string $b): int => strcasecmp($a, $b));

        return $names;
    }

    /**
     * Hra včetně statistik pro seznam/detail: poslední zápis (nebo null) a počet zápisů.
     *
     * @return array<string, mixed>
     */
    public function gameWithStats(Game $game, ?PartyGame $link, ?Play $lastPlay, int $playCount): array
    {
        $winners = [];
        $winnerScore = null;
        if (null !== $lastPlay) {
            foreach ($lastPlay->getResults() as $r) {
                if ($r->isWinner()) {
                    $winners[] = $r->getPlayer()->getName();
                    $winnerScore ??= $r->getScore();
                }
            }
            sort($winners);
        }

        return $this->game($game, $link) + [
            'playCount' => $playCount,
            'lastPlay' => null === $lastPlay ? null : [
                'id' => $lastPlay->getId(),
                'startedAt' => $lastPlay->getStartedAt()->format(\DATE_ATOM),
                'sessionNumber' => $lastPlay->getSession()->getNumber(),
                'winners' => $winners,
                'winnerScore' => $winnerScore,
            ],
        ];
    }

    public function play(Play $play): array
    {
        $results = $play->getResults()->toArray();
        usort($results, static fn (PlayResult $a, PlayResult $b): int => $a->getRank() <=> $b->getRank());

        return [
            'id' => $play->getId(),
            'game' => ['id' => $play->getGame()->getId(), 'name' => $play->getGame()->getName(), 'imageUrl' => $play->getGame()->getImageUrl(), 'scoreType' => $play->getGame()->getScoreType()->value],
            'sessionId' => $play->getSession()->getId(),
            'sessionNumber' => $play->getSession()->getNumber(),
            'startedAt' => $play->getStartedAt()->format(\DATE_ATOM),
            'endedAt' => $play->getEndedAt()->format(\DATE_ATOM),
            'notes' => $play->getNotes(),
            'addons' => array_values(array_map(static fn ($a): array => ['id' => $a->getId(), 'name' => $a->getName()], $play->getAddons()->toArray())),
            'tags' => array_values(array_map(static fn ($t): string => $t->getName(), $play->getTags()->toArray())),
            'results' => array_map(static fn (PlayResult $r): array => [
                'playerId' => $r->getPlayer()->getId(),
                'name' => $r->getPlayer()->getName(),
                'score' => $r->getScore(),
                'rank' => $r->getRank(),
                'winner' => $r->isWinner(),
                'starting' => $r->isStarting(),
                'character' => null === $r->getCharacter() ? null : ['id' => $r->getCharacter()->getId(), 'name' => $r->getCharacter()->getName()],
            ], $results),
        ];
    }
}
