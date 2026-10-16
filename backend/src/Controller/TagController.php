<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Party;
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
}
