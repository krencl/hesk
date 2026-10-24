<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Exception\DomainException;
use App\Domain\NameKey;
use App\Entity\Party;
use App\Entity\Play;
use App\Entity\PlayTag;
use App\Entity\Player;
use App\Repository\PlayRepository;
use App\Repository\PlayTagRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Přesun zápisu do jiné party (ADR 0028): hra se do cílové party zařadí, hráči se převedou na hráče cílové party
 * (člen podle účtu, host podle jména, jinak vznikne nový host), tagy se založí v cílové partě a zápis se zařadí do její seance.
 */
final class PlayMover
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SessionAssigner $sessions,
        private readonly PlayRepository $plays,
        private readonly PlayTagRepository $tags,
    ) {
    }

    /** @return list<string> jména hostů, kteří kvůli přesunu v cílové partě vznikli */
    public function move(Play $play, Party $target): array
    {
        if ($play->getParty() === $target) {
            throw new DomainException('Zápis už v této partě je.');
        }

        return $this->em->wrapInTransaction(fn (): array => $this->doMove($play, $target));
    }

    /** @return list<string> */
    private function doMove(Play $play, Party $target): array
    {
        $source = $play->getParty();
        $game = $play->getGame();
        $sourceLink = $source->findGameLink($game);
        $targetLink = $target->addGame($game);

        $created = [];
        $taken = [];
        foreach ($play->getResults() as $result) {
            $from = $result->getPlayer();
            $to = $this->resolvePlayer($target, $from, $taken, $created);
            $taken[spl_object_id($to)] = true;
            $result->changePlayer($to);
            // „hráč hry“ (např. Boss u kooperativních her) zůstane hráčem hry i v cílové partě
            if ($sourceLink?->getPlayers()->contains($from) && !$targetLink->getPlayers()->contains($to)) {
                $targetLink->getPlayers()->add($to);
            }
        }

        $play->setTags($this->tags->findOrCreate($target, array_map(static fn (PlayTag $t): string => $t->getName(), $play->getTags()->toArray())));

        // původní seanci přepočítáme bez přesouvaného zápisu (prázdná zanikne), zápis zařadíme do seance cílové party
        $oldSession = $play->getSession();
        $others = array_filter($this->plays->findBySession($oldSession), static fn (Play $p): bool => $p !== $play);
        $emptied = $this->sessions->recalculate($oldSession, $others);
        $newSession = $this->sessions->assign($target, $play->getStartedAt(), $play->getEndedAt());
        $this->em->persist($newSession);
        $play->moveTo($target, $newSession);
        $this->em->flush();
        if ($emptied) {
            $this->em->remove($oldSession);
            $this->em->flush();
        }

        return $created;
    }

    /**
     * Hráč cílové party odpovídající hráči zdrojové party: člen s tímtéž účtem, jinak host téhož jména (předpokládá se
     * stejná osoba), jinak nový host. Jméno obsazené členem s jiným účtem dostane číslo („Bob 2“), přejmenovat a propojit
     * ho jde pak ve správě hostů. Jednoho hráče cílové party nelze použít dvakrát (unikát zápis + hráč).
     *
     * @param array<int, true> $taken
     * @param list<string>     $created
     */
    private function resolvePlayer(Party $target, Player $from, array $taken, array &$created): Player
    {
        if (null !== $user = $from->getUser()) {
            $member = $target->findPlayerFor($user);
            if (null !== $member && !isset($taken[spl_object_id($member)])) {
                return $member;
            }
        }
        $key = NameKey::of($from->getName());
        foreach ($target->getPlayers() as $candidate) {
            if (null === $candidate->getUser() && NameKey::of($candidate->getName()) === $key && !isset($taken[spl_object_id($candidate)])) {
                return $candidate;
            }
        }

        $guest = $target->addGuest($target->uniquePlayerName($from->getName()));
        $guest->setVisible($from->isVisible());
        $created[] = $guest->getName();

        return $guest;
    }
}
