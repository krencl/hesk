<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\PartyExportFilter;
use App\Entity\Party;
use App\Security\PartyVoter;
use App\Service\PartyExporter;
use App\Service\Presenter;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class PartyExportController extends ApiController
{
    public function __construct(Presenter $presenter, private readonly PartyExporter $exporter)
    {
        parent::__construct($presenter);
    }

    /** Party data as a downloadable JSON file (admin only); `?from=&to=` (date-times) or `?fromSession=&toSession=` limit the plays, ADR 0033. */
    #[Route('/api/parties/{id}/export', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(PartyVoter::ADMIN, 'party')]
    public function __invoke(Party $party, Request $request): JsonResponse
    {
        $now = new \DateTimeImmutable();
        $response = new JsonResponse($this->exporter->export($party, PartyExportFilter::fromQuery($request->query->all()), $now));
        $response->setEncodingOptions(\JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION);
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, 'hesk-export-'.$now->format('Y-m-d').'.json'));

        return $response;
    }
}
