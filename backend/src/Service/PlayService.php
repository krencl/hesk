<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Exception\DomainException;
use App\Domain\PlayerInput;
use App\Domain\ResultCalculator;
use App\Dto\PlayRequest;
use App\Entity\Game;
use App\Entity\Party;
use App\Entity\Play;
use App\Entity\PlayResult;
use App\Entity\Tag;
use App\Entity\Player;
use App\Repository\PlayRepository;
use Doctrine\ORM\EntityManagerInterface;

/** Vytváření, úprava a mazání zápisů včetně přiřazení do seancí. */
final class PlayService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ResultCalculator $calculator,
        private readonly SessionAssigner $sessions,
        private readonly PlayRepository $plays,
    ) {
    }

    public function create(Party $party, PlayRequest $req): Play
    {
        $game = $this->resolveGame($party, $req);
        $session = $this->sessions->assign($party, $req->startedAt, $req->endedAt, $req->sessionNumber);
        $play = new Play($party, $game, $req->startedAt, $req->endedAt, $session);
        $this->em->persist($session);
        $this->fill($play, $party, $game, $req);
        $this->em->persist($play);
        $this->em->flush();

        return $play;
    }

    public function update(Play $play, PlayRequest $req): Play
    {
        return $this->em->wrapInTransaction(fn (): Play => $this->doUpdate($play, $req));
    }

    private function doUpdate(Play $play, PlayRequest $req): Play
    {
        $party = $play->getParty();
        $game = $this->resolveGame($party, $req);
        $oldSession = $play->getSession();

        $play->setTimes($req->startedAt, $req->endedAt);
        $this->fill($play, $party, $game, $req);
        $this->em->flush();

        // Seance přepočítáme z jejích zápisů (bez upravovaného) a upravený zápis zařadíme znovu.
        $others = array_filter($this->plays->findBySession($oldSession), static fn (Play $p): bool => $p !== $play);
        $emptied = $this->sessions->recalculate($oldSession, $others);
        $newSession = $this->sessions->assign($party, $req->startedAt, $req->endedAt, $req->sessionNumber);
        $this->em->persist($newSession);
        if ($emptied && $newSession === $oldSession) {
            // prázdná seance se znovu použije (stejné číslo) – hranice musí odpovídat jen novému zápisu
            $newSession->resetBounds($req->startedAt, $req->endedAt);
        }
        $play->setSession($newSession);
        $this->em->flush();
        if ($emptied && $oldSession !== $newSession) {
            $this->em->remove($oldSession);
            $this->em->flush();
        }

        return $play;
    }

    public function delete(Play $play): void
    {
        $session = $play->getSession();
        $this->em->remove($play);
        $this->em->flush();
        $remaining = $this->plays->findBySession($session);
        if ($this->sessions->recalculate($session, $remaining)) {
            $this->em->remove($session);
        }
        $this->em->flush();
    }

    private function resolveGame(Party $party, PlayRequest $req): Game
    {
        $game = $this->em->find(Game::class, $req->gameId);
        if (!$game || $game->getParty() !== $party) {
            throw new DomainException('Hra neexistuje v této partě.');
        }

        return $game;
    }

    private function fill(Play $play, Party $party, Game $game, PlayRequest $req): void
    {
        $outcomes = $this->calculator->calculate(
            $game->getScoreType(),
            array_map(static fn ($p): PlayerInput => new PlayerInput($p->playerId, $p->score, $p->rank, $p->winner, $p->starting, $p->characterId), $req->players),
        );

        $results = [];
        foreach ($outcomes as $o) {
            $player = $this->em->find(Player::class, $o->playerId);
            if (!$player || $player->getParty() !== $party) {
                throw new DomainException('Hráč nepatří do této party.');
            }
            $character = null;
            if (null !== $o->characterId) {
                $character = $game->getCharacters()->filter(static fn ($c): bool => $c->getId() === $o->characterId)->first() ?: null;
                if (!$character) {
                    throw new DomainException('Postava nepatří k vybrané hře.');
                }
            }
            $results[] = new PlayResult($play, $player, $o->score, $o->rank, $o->winner, $o->starting, $character);
        }

        $addons = [];
        foreach (array_unique($req->addonIds) as $addonId) {
            $match = $game->getAddons()->filter(static fn ($a): bool => $a->getId() === $addonId)->first();
            if (!$match) {
                throw new DomainException('Rozšíření nepatří k vybrané hře.');
            }
            $addons[] = $match;
        }

        // Při úpravě se staré výsledky musí smazat dřív, než se vloží nové (unikát play_id + player_id);
        // proběhne až po úspěšné validaci výše, a celá úprava běží v transakci.
        if (null !== $play->getId() && !$play->getResults()->isEmpty()) {
            foreach ($play->getResults() as $old) {
                $this->em->remove($old);
            }
            $play->replaceResults([]);
            $this->em->flush();
        }

        $play->setGame($game);
        $play->replaceResults($results);
        $play->setAddons($addons);
        $play->setTags(array_map(fn (string $n): Tag => $this->findOrCreateTag($party, $n), $this->cleanNames($req->tags)));
        $play->setNotes($req->notes);
    }

    /** @return list<string> */
    private function cleanNames(array $names): array
    {
        return array_values(array_unique(array_filter(array_map('trim', $names), static fn (string $n): bool => '' !== $n)));
    }

    private function findOrCreateTag(Party $party, string $name): Tag
    {
        $tag = $this->em->getRepository(Tag::class)->findOneBy(['party' => $party, 'name' => $name]);
        if (!$tag) {
            $tag = new Tag($party, $name);
            $this->em->persist($tag);
        }

        return $tag;
    }
}
