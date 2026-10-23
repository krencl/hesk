<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\GameTag;
use App\Entity\Party;
use App\Entity\PartyGame;
use App\Entity\Tag;
use App\Security\PartyVoter;
use App\Service\Presenter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Dosud použité tagy party (našeptávání ve formuláři zápisu; nové se zakládají při uložení zápisu). */
final class TagController extends ApiController
{
    public function __construct(Presenter $presenter, private readonly EntityManagerInterface $em)
    {
        parent::__construct($presenter);
    }

    #[Route('/api/parties/{id}/tags', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(PartyVoter::VIEW, 'party')]
    public function list(Party $party): JsonResponse
    {
        $names = array_map(
            static fn (array $row): string => $row['name'],
            $this->em->createQueryBuilder()->select('t.name')->from(Tag::class, 't')->where('t.party = :party')->setParameter('party', $party)->getQuery()->getArrayResult(),
        );
        usort($names, static fn (string $a, string $b): int => strcasecmp($a, $b));

        return new JsonResponse($names);
    }

    /** Tagy her, které má aspoň jedna hra zařazená v partě (filtr statistik). */
    #[Route('/api/parties/{id}/game-tags', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(PartyVoter::VIEW, 'party')]
    public function partyGameTags(Party $party): JsonResponse
    {
        return new JsonResponse($this->sorted($this->em->createQuery(
            'SELECT DISTINCT t.name FROM '.PartyGame::class.' pg JOIN pg.game g JOIN g.tags t WHERE pg.party = :party',
        )->setParameter('party', $party)->getArrayResult()));
    }

    /** Všechny tagy her v katalogu (našeptávání ve formuláři hry). */
    #[Route('/api/game-tags', methods: ['GET'])]
    public function gameTags(): JsonResponse
    {
        return new JsonResponse($this->sorted($this->em->createQuery('SELECT t.name FROM '.GameTag::class.' t')->getArrayResult()));
    }

    /**
     * @param list<array{name: string}> $rows
     *
     * @return list<string>
     */
    private function sorted(array $rows): array
    {
        $names = array_map(static fn (array $row): string => $row['name'], $rows);
        usort($names, static fn (string $a, string $b): int => strcasecmp($a, $b));

        return $names;
    }
}
