<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\PlayRequest;
use App\Entity\Party;
use App\Entity\Play;
use App\Repository\PlayRepository;
use App\Security\PartyVoter;
use App\Service\PlayService;
use App\Service\Presenter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class PlayController extends ApiController
{
    public function __construct(Presenter $presenter, private readonly PlayService $service, private readonly PlayRepository $plays)
    {
        parent::__construct($presenter);
    }

    #[Route('/api/parties/{id}/plays', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(PartyVoter::VIEW, 'party')]
    public function list(Party $party, Request $request): JsonResponse
    {
        $limit = max(1, min(100, $request->query->getInt('limit', 20)));
        $offset = max(0, $request->query->getInt('offset', 0));
        $gameId = $request->query->has('gameId') ? $request->query->getInt('gameId') : null;

        return new JsonResponse(array_map(
            $this->presenter->play(...),
            $this->plays->findPage($party, $limit, $offset, $gameId),
        ));
    }

    #[Route('/api/parties/{id}/plays', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(PartyVoter::VIEW, 'party')]
    public function create(Party $party, #[MapRequestPayload] PlayRequest $req): JsonResponse
    {
        return new JsonResponse($this->presenter->play($this->service->create($party, $req)), Response::HTTP_CREATED);
    }

    #[Route('/api/plays/{id}', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Play $play): JsonResponse
    {
        $this->denyAccessUnlessGranted(PartyVoter::VIEW, $play->getParty());

        return new JsonResponse($this->presenter->play($play));
    }

    #[Route('/api/plays/{id}', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function update(Play $play, #[MapRequestPayload] PlayRequest $req): JsonResponse
    {
        $this->denyAccessUnlessGranted(PartyVoter::VIEW, $play->getParty());

        return new JsonResponse($this->presenter->play($this->service->update($play, $req)));
    }

    #[Route('/api/plays/{id}', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(Play $play): Response
    {
        $this->denyAccessUnlessGranted(PartyVoter::VIEW, $play->getParty());
        $this->service->delete($play);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
