<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Party;
use App\Entity\Place;
use App\Entity\Tag;
use App\Security\PartyVoter;
use App\Service\Presenter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Dosud použitá místa a tagy party (našeptávání ve formuláři zápisu; nové se zakládají při uložení zápisu). */
final class LookupController extends ApiController
{
    public function __construct(Presenter $presenter, private readonly EntityManagerInterface $em)
    {
        parent::__construct($presenter);
    }

    #[Route('/api/parties/{id}/places', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(PartyVoter::VIEW, 'party')]
    public function places(Party $party): JsonResponse
    {
        return new JsonResponse($this->names(Place::class, $party));
    }

    #[Route('/api/parties/{id}/tags', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(PartyVoter::VIEW, 'party')]
    public function tags(Party $party): JsonResponse
    {
        return new JsonResponse($this->names(Tag::class, $party));
    }

    /**
     * @param class-string<Place|Tag> $class
     *
     * @return list<string>
     */
    private function names(string $class, Party $party): array
    {
        $names = array_map(
            static fn (array $row): string => $row['name'],
            $this->em->createQueryBuilder()->select('e.name')->from($class, 'e')->where('e.party = :party')->setParameter('party', $party)->getQuery()->getArrayResult(),
        );
        usort($names, static fn (string $a, string $b): int => strcasecmp($a, $b));

        return $names;
    }
}
