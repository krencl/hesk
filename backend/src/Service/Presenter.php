<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Game;
use App\Entity\Party;
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
        return $this->user($user) + ['hasNickname' => $user->hasNickname()];
    }

    public function party(Party $party, ?User $viewer = null): array
    {
        $data = ['id' => $party->getId(), 'name' => $party->getName()];
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

    public function game(Game $game): array
    {
        return [
            'id' => $game->getId(),
            'name' => $game->getName(),
            'scoreType' => $game->getScoreType()->value,
            'imageUrl' => $game->getImageUrl(),
            'description' => $game->getDescription(),
            'cheatSheet' => $game->getCheatSheet(),
            'addons' => array_values(array_map(
                static fn ($a): array => ['id' => $a->getId(), 'name' => $a->getName()],
                $game->getAddons()->toArray(),
            )),
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
            'startedAt' => $play->getStartedAt()->format(\DATE_ATOM),
            'endedAt' => $play->getEndedAt()->format(\DATE_ATOM),
            'place' => $play->getPlace()?->getName(),
            'notes' => $play->getNotes(),
            'addons' => array_values(array_map(static fn ($a): array => ['id' => $a->getId(), 'name' => $a->getName()], $play->getAddons()->toArray())),
            'tags' => array_values(array_map(static fn ($t): string => $t->getName(), $play->getTags()->toArray())),
            'results' => array_map(static fn (PlayResult $r): array => [
                'userId' => $r->getUser()->getId(),
                'nickname' => $r->getUser()->getNickname(),
                'score' => $r->getScore(),
                'rank' => $r->getRank(),
                'winner' => $r->isWinner(),
            ], $results),
        ];
    }
}
