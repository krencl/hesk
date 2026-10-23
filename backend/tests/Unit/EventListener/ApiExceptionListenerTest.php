<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Domain\Exception\DomainException;
use App\EventListener\ApiExceptionListener;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/** Převod výjimek na JSON odpovědi API (kódy, které dřív ověřovaly funkční testy). */
final class ApiExceptionListenerTest extends TestCase
{
    private function handle(\Throwable $e, string $path = '/api/parties'): ExceptionEvent
    {
        $event = new ExceptionEvent($this->createStub(HttpKernelInterface::class), Request::create($path), HttpKernelInterface::MAIN_REQUEST, $e);
        (new ApiExceptionListener())($event);

        return $event;
    }

    public function testDomainErrorIs422WithTheMessage(): void
    {
        $response = $this->handle(new DomainException('Hráč nepatří do této party.'))->getResponse();

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(['error' => 'Hráč nepatří do této party.'], json_decode((string) $response->getContent(), true));
    }

    public function testUniqueConstraintViolationIs409WithoutLeakingSql(): void
    {
        $driver = new class('Duplicate entry \'Honza\' for key \'player.UNIQ\'') extends \Exception implements DriverException {
            public function getSQLState(): ?string
            {
                return '23000';
            }
        };

        $response = $this->handle(new UniqueConstraintViolationException($driver, null))->getResponse();

        self::assertSame(409, $response->getStatusCode());
        self::assertSame(['error' => 'Záznam se stejným názvem už existuje.'], json_decode((string) $response->getContent(), true));
    }

    /** @return iterable<string, array{\Throwable, int}> */
    public static function httpErrors(): iterable
    {
        yield 'nenalezeno' => [new NotFoundHttpException('Pozvánka neexistuje nebo vypršela.'), 404];
        yield 'zakázáno' => [new AccessDeniedHttpException('Access Denied.'), 403];
        yield 'služba nedostupná' => [new ServiceUnavailableHttpException(null, 'BoardGameGeek teď není dostupné.'), 503];
        yield 'jiný HTTP kód' => [new HttpException(409, 'Konflikt'), 409];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('httpErrors')]
    public function testHttpExceptionsKeepTheirStatusCode(\Throwable $e, int $status): void
    {
        $response = $this->handle($e)->getResponse();

        self::assertSame($status, $response->getStatusCode());
        self::assertSame($e->getMessage(), json_decode((string) $response->getContent(), true)['error']);
    }

    public function testValidationErrorsListTheViolations(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('Tato hodnota nesmí být prázdná.', null, [], null, 'name', ''),
            new ConstraintViolation('Zápis musí obsahovat alespoň jednoho hráče.', null, [], null, 'players', []),
        ]);
        $e = new HttpException(422, 'Unprocessable', new ValidationFailedException(null, $violations));

        $response = $this->handle($e)->getResponse();
        $payload = json_decode((string) $response->getContent(), true);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('Validation failed', $payload['error']);
        self::assertSame([['field' => 'name', 'message' => 'Tato hodnota nesmí být prázdná.'], ['field' => 'players', 'message' => 'Zápis musí obsahovat alespoň jednoho hráče.']], $payload['violations']);
    }

    public function testUnexpectedErrorsAndNonApiPathsAreLeftToSymfony(): void
    {
        self::assertNull($this->handle(new \RuntimeException('boom'))->getResponse());
        self::assertNull($this->handle(new DomainException('x'), '/neco')->getResponse());
        self::assertNull($this->handle(new NotFoundHttpException(), '/')->getResponse());
    }
}
