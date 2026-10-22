<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\BggImportRequest;
use App\Entity\Party;
use App\Security\PartyVoter;
use App\Service\Bgg\BggCatalog;
use App\Service\Bgg\BggClient;
use App\Service\Bgg\BggUnavailableException;
use App\Service\Presenter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Hledání hry při zakládání (nejdřív lokální data, potom BoardGameGeek) a převzetí hry z BGG (ADR 0021). */
final class BggController extends ApiController
{
    public const MIN_QUERY_LENGTH = 2;

    public function __construct(Presenter $presenter, private readonly BggCatalog $catalog, private readonly BggClient $client)
    {
        parent::__construct($presenter);
    }

    #[Route('/api/parties/{id}/game-search', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(PartyVoter::VIEW, 'party')]
    public function search(Party $party, Request $request): JsonResponse
    {
        $query = trim((string) $request->query->get('q'));
        if (mb_strlen($query) < self::MIN_QUERY_LENGTH) {
            return new JsonResponse(['results' => [], 'bgg' => BggCatalog::BGG_SKIPPED]);
        }

        return new JsonResponse($this->catalog->search($party, $query));
    }

    /** Vrací údaje pro předvyplnění formuláře hry: `{bggId, name, bggImageUrl}`. */
    #[Route('/api/parties/{id}/bgg/import', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(PartyVoter::VIEW, 'party')]
    public function import(Party $party, #[MapRequestPayload] BggImportRequest $req): JsonResponse
    {
        try {
            return new JsonResponse($this->catalog->prepare($req->bggId));
        } catch (BggUnavailableException $e) {
            throw new ServiceUnavailableHttpException(null, 'BoardGameGeek teď není dostupné. Založ hru jako vlastní.', $e);
        }
    }

    /**
     * Obrázek z BGG CDN přes náš origin, aby ho ořezový editor mohl načíst do canvasu bez CORS (ADR 0021).
     * Jen adresy z domény BGG CDN.
     */
    #[Route('/api/bgg/image', methods: ['GET'])]
    public function image(Request $request): Response
    {
        try {
            $image = $this->client->fetchImage((string) $request->query->get('url'));
        } catch (BggUnavailableException $e) {
            throw new ServiceUnavailableHttpException(null, 'Obrázek z BoardGameGeek se nepodařilo načíst.', $e);
        }

        return new Response($image['content'], Response::HTTP_OK, [
            'Content-Type' => $image['type'],
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
