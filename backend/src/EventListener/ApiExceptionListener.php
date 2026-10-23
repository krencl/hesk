<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Domain\Exception\DomainException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/** Chyby pod /api vrací vždy jako JSON ve tvaru {error, violations?}. */
#[AsEventListener]
final class ApiExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
            return;
        }
        $e = $event->getThrowable();
        $payload = ['error' => $e->getMessage()];
        $status = Response::HTTP_INTERNAL_SERVER_ERROR;

        if ($e instanceof DomainException) {
            $status = Response::HTTP_UNPROCESSABLE_ENTITY;
        } elseif ($e instanceof UniqueConstraintViolationException) {
            // pojistka: duplicitu názvu, kterou nezachytila doménová kontrola (např. jiná kolace DB), nevracíme jako 500
            $status = Response::HTTP_CONFLICT;
            $payload = ['error' => 'Záznam se stejným názvem už existuje.'];
        } elseif ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $previous = $e->getPrevious();
            if ($previous instanceof ValidationFailedException) {
                $payload['error'] = 'Validation failed';
                foreach ($previous->getViolations() as $v) {
                    $payload['violations'][] = ['field' => $v->getPropertyPath(), 'message' => $v->getMessage()];
                }
            }
        } else {
            return; // neočekávané chyby nechá na Symfony (v prod loguje a skryje detail)
        }

        $event->setResponse(new JsonResponse($payload, $status));
    }
}
