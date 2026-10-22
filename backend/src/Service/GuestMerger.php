<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Exception\DomainException;
use App\Entity\PartyGame;
use App\Entity\PartyInvitation;
use App\Entity\Player;
use App\Entity\PlayResult;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Propojení hosta s členem party: všechny zápisy hosta (výsledky, hráči her) přejdou na hráče člena a host zmizí.
 * Hráč člena (navázaný na účet) zůstává, takže jeho jméno dál sleduje přezdívku.
 */
final class GuestMerger
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** Pozvánky určené tomuto hostovi ztratí vazbu na něj (pozvaný se pak jen připojí); volá se před smazáním hosta. */
    public function forgetInvitations(Player $guest): void
    {
        foreach ($this->em->getRepository(PartyInvitation::class)->findBy(['guest' => $guest]) as $invitation) {
            $invitation->clearGuest();
        }
    }

    public function mergeGuestIntoMember(Player $guest, Player $member): void
    {
        $party = $guest->getParty();
        if ($guest === $member) {
            throw new DomainException('Host a člen jsou stejný hráč.');
        }
        if ($member->getParty() !== $party) {
            throw new DomainException('Hráč patří do jiné party.');
        }
        if (null !== $guest->getUser()) {
            throw new DomainException('Propojit jde jen host bez účtu.');
        }
        if (null === $member->getUser()) {
            throw new DomainException('Host se propojuje s členem party, ne s jiným hostem.');
        }

        $shared = (int) $this->em->createQuery(
            'SELECT COUNT(a.id) FROM '.PlayResult::class.' a JOIN '.PlayResult::class.' b WITH b.play = a.play WHERE a.player = :guest AND b.player = :member',
        )->setParameters(['guest' => $guest, 'member' => $member])->getSingleScalarResult();
        if ($shared > 0) {
            throw new DomainException(sprintf('Host a člen hráli v %d stejných zápisech, takže je nejde sloučit.', $shared));
        }

        $this->em->wrapInTransaction(function () use ($guest, $member, $party): void {
            $this->em->createQuery('UPDATE '.PlayResult::class.' r SET r.player = :to WHERE r.player = :from')->setParameters(['to' => $member, 'from' => $guest])->execute();
            foreach ($this->em->createQuery('SELECT pg FROM '.PartyGame::class.' pg WHERE :guest MEMBER OF pg.players')->setParameter('guest', $guest)->getResult() as $link) {
                $link->getPlayers()->removeElement($guest);
                if (!$link->getPlayers()->contains($member)) {
                    $link->getPlayers()->add($member);
                }
            }
            $this->forgetInvitations($guest);
            $party->getPlayers()->removeElement($guest);
            $this->em->remove($guest);
            $this->em->flush();
            // jméno hosta je teď volné: hráč člena se může jmenovat podle přezdívky (při přidání dostal třeba „Bob 2“)
            $party->syncPlayerName($member->getUser());
            $this->em->flush();
        });
    }
}
