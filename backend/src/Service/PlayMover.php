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

    /**
     * Náhled přesunu: co se stane s každým hráčem zápisu, bez jakékoli změny. Stejné pravidlo používá {@see self::move()}.
     *
     * @return list<PlayerMapping> v pořadí výsledků zápisu
     */
    public function plan(Play $play, Party $target): array
    {
        $mappings = [];
        $taken = [];
        $plannedKeys = [];
        foreach (array_values($play->getResults()->toArray()) as $result) {
            $mapping = $this->resolvePlayer($target, $result->getPlayer(), $taken, $plannedKeys);
            if (null !== $mapping->to) {
                $taken[spl_object_id($mapping->to)] = true;
            } else {
                $plannedKeys[NameKey::of($mapping->targetName())] = true;
            }
            $mappings[] = $mapping;
        }

        return $mappings;
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
        $mappings = $this->plan($play, $target);
        $targetLink = $target->addGame($game);

        $created = [];
        foreach (array_values($play->getResults()->toArray()) as $i => $result) {
            $mapping = $mappings[$i];
            $to = $mapping->to;
            if (null === $to) {
                $to = $target->addGuest($mapping->targetName());
                $to->setVisible($mapping->from->isVisible());
                $created[] = $to->getName();
            }
            $result->changePlayer($to);
            // „hráč hry“ (např. Boss u kooperativních her) zůstane hráčem hry i v cílové partě
            if ($sourceLink?->getPlayers()->contains($mapping->from) && !$targetLink->getPlayers()->contains($to)) {
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
     * stejná osoba), jinak nový host. Jméno obsazené členem s jiným účtem (nebo jiným nově zakládaným hostem) dostane číslo
     * („Bob 2“), přejmenovat a propojit ho jde pak ve správě hostů. Jednoho hráče cílové party nelze použít dvakrát
     * (unikát zápis + hráč).
     *
     * @param array<int, true>    $taken       hráči cílové party, kteří už jsou použití
     * @param array<string, true> $plannedKeys {@see NameKey} jmen hostů, které se budou zakládat
     */
    private function resolvePlayer(Party $target, Player $from, array $taken, array $plannedKeys): PlayerMapping
    {
        if (null !== $user = $from->getUser()) {
            $member = $target->findPlayerFor($user);
            if (null !== $member && !isset($taken[spl_object_id($member)])) {
                return new PlayerMapping($from, $member);
            }
        }
        $key = NameKey::of($from->getName());
        foreach ($target->getPlayers() as $candidate) {
            if (null === $candidate->getUser() && NameKey::of($candidate->getName()) === $key && !isset($taken[spl_object_id($candidate)])) {
                return new PlayerMapping($from, $candidate);
            }
        }

        $name = $base = $from->getName();
        for ($i = 2; $target->isPlayerNameTaken($name) || isset($plannedKeys[NameKey::of($name)]); ++$i) {
            $name = $base.' '.$i;
        }

        return new PlayerMapping($from, null, $name);
    }
}
