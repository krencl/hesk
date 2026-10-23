<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Party;
use App\Repository\GameSessionRepository;
use App\Security\PartyVoter;
use App\Service\Presenter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class SessionController extends ApiController
{
    public function __construct(Presenter $presenter, private readonly GameSessionRepository $sessions)
    {
        parent::__construct($presenter);
    }

    /** Seance party od nejnovější (podle čísla). */
    #[Route('/api/parties/{id}/sessions', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(PartyVoter::VIEW, 'party')]
    public function list(Party $party): JsonResponse
    {
        $sessions = $this->sessions->findBy(['party' => $party], ['number' => 'DESC']);

        return new JsonResponse(array_map($this->presenter->session(...), $sessions));
    }
}
