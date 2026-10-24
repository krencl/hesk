<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Exception\DomainException;
use App\Dto\MovePlayRequest;
use App\Dto\PlayRequest;
use App\Entity\Party;
use App\Entity\Play;
use App\Repository\PartyRepository;
use App\Repository\PlayRepository;
use App\Security\PartyVoter;
use App\Service\PlayMover;
use App\Service\PlayService;
use App\Service\SessionAssigner;
use App\Service\Presenter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class PlayController extends ApiController
{
    public function __construct(Presenter $presenter, private readonly PlayService $service, private readonly PlayRepository $plays, private readonly SessionAssigner $sessions, private readonly PlayMover $mover, private readonly PartyRepository $parties)
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

    /**
     * Výchozí hodnoty nového zápisu: číslo seance, do které by zápis v daném čase spadl (jinak další v pořadí),
     * hráči a tagy posledního zápisu této seance (`playerIds` je `null`, když seance ještě neexistuje = první hra dne).
     */
    #[Route('/api/parties/{id}/plays/defaults', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(PartyVoter::WRITE, 'party')]
    public function defaults(Party $party, Request $request): JsonResponse
    {
        $start = $this->parseTime($request->query->get('startedAt'));
        $end = $this->parseTime($request->query->get('endedAt')) ?? $start;
        if (null === $start || null === $end) {
            throw new DomainException('Zadej začátek (startedAt) a konec (endedAt) hry.');
        }

        ['number' => $number, 'session' => $session] = $this->sessions->suggest($party, $start, $end);
        $last = $session ? $this->plays->findLastInSession($session) : null;

        return new JsonResponse([
            'sessionNumber' => $number,
            'playerIds' => null === $last ? null : array_values(array_map(static fn ($r): int => $r->getPlayer()->getId(), $last->getResults()->toArray())),
            'tags' => null === $last ? [] : array_values(array_map(static fn ($t): string => $t->getName(), $last->getTags()->toArray())),
        ]);
    }

    private function parseTime(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_string($value) || '' === $value) {
            return null;
        }
        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            throw new DomainException('Neplatné datum a čas.');
        }
    }

    #[Route('/api/parties/{id}/plays', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(PartyVoter::WRITE, 'party')]
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
        $this->denyAccessUnlessGranted(PartyVoter::WRITE, $play->getParty());

        return new JsonResponse($this->presenter->play($this->service->update($play, $req)));
    }

    #[Route('/api/plays/{id}', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(Play $play): Response
    {
        $this->denyAccessUnlessGranted(PartyVoter::WRITE, $play->getParty());
        $this->service->delete($play);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /** Správce přesune zápis do jiné party, ve které je taky správce (ADR 0028). */
    #[Route('/api/plays/{id}/move', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function move(Play $play, #[MapRequestPayload] MovePlayRequest $req): JsonResponse
    {
        $this->denyAccessUnlessGranted(PartyVoter::ADMIN, $play->getParty());
        $target = $this->parties->find($req->partyId);
        // neexistující a cizí party se nerozlišují, aby šlo zjistit, jaké party existují
        if (null === $target || !$this->isGranted(PartyVoter::ADMIN, $target)) {
            throw new DomainException('Cílová parta neexistuje, nebo v ní nejsi správce.');
        }
        $createdGuests = $this->mover->move($play, $target);

        return new JsonResponse([
            'play' => $this->presenter->play($play),
            'party' => ['id' => $target->getId(), 'name' => $target->getName()],
            'createdGuests' => $createdGuests,
        ]);
    }
}
