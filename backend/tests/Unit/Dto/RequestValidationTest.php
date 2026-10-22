<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto;

use App\Dto\BggImportRequest;
use App\Dto\CreatePartyRequest;
use App\Dto\DevLoginRequest;
use App\Dto\GameRequest;
use App\Dto\LinkMemberRequest;
use App\Dto\NamedItemRequest;
use App\Dto\NicknameRequest;
use App\Dto\PlayRequest;
use App\Dto\PlayerNameRequest;
use App\Dto\PlayerRequest;
use App\Dto\UpdatePartyRequest;
use App\Service\CoverStorage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** Validace vstupů API (to, co kernel udělá před kontrolerem a vrátí jako 422), bez HTTP a databáze. */
final class RequestValidationTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }

    private function invalid(object $dto): bool
    {
        return \count($this->validator->validate($dto)) > 0;
    }

    private function d(string $s): \DateTimeImmutable
    {
        return new \DateTimeImmutable($s);
    }

    public function testPartyNameAndShortNameAreRequiredAndLimited(): void
    {
        self::assertFalse($this->invalid(new UpdatePartyRequest('Pátek večer', 'PV')));
        self::assertFalse($this->invalid(new UpdatePartyRequest(' Pátek ', ' PV ')), 'mezery se ořezávají před kontrolou');
        foreach ([new UpdatePartyRequest('Pátek', '  '), new UpdatePartyRequest('Pátek', ''), new UpdatePartyRequest('Pátek', 'ABCD'), new UpdatePartyRequest(' ', 'PV'), new UpdatePartyRequest(str_repeat('x', 101), 'PV')] as $dto) {
            self::assertTrue($this->invalid($dto));
        }
        self::assertFalse($this->invalid(new CreatePartyRequest('Pátek')));
        self::assertTrue($this->invalid(new CreatePartyRequest('   ')));
    }

    public function testPlayNeedsPlayersAndEndAfterStart(): void
    {
        $player = [new PlayerRequest(1, 1.0)];
        $valid = new PlayRequest(1, $this->d('2026-03-01T18:00:00+00:00'), $this->d('2026-03-01T19:00:00+00:00'), $player);
        self::assertFalse($this->invalid($valid));
        self::assertFalse($this->invalid(new PlayRequest(1, $this->d('2026-03-01T18:00:00+00:00'), $this->d('2026-03-01T18:00:00+00:00'), $player)), 'začátek = konec je v pořádku');

        self::assertTrue($this->invalid(new PlayRequest(1, $this->d('2026-03-01T18:00:00+00:00'), $this->d('2026-03-01T19:00:00+00:00'), [])), 'žádný hráč');
        self::assertTrue($this->invalid(new PlayRequest(1, $this->d('2026-03-01T19:00:00+00:00'), $this->d('2026-03-01T18:00:00+00:00'), $player)), 'konec před začátkem');
        self::assertTrue($this->invalid(new PlayRequest(1, $this->d('2026-03-01T18:00:00+00:00'), $this->d('2026-03-01T19:00:00+00:00'), $player, sessionNumber: 0)), 'číslo seance musí být kladné');
        self::assertTrue($this->invalid(new PlayRequest(1, $this->d('2026-03-01T18:00:00+00:00'), $this->d('2026-03-01T19:00:00+00:00'), $player, tags: [''])));
        self::assertTrue($this->invalid(new PlayRequest(1, $this->d('2026-03-01T18:00:00+00:00'), $this->d('2026-03-01T19:00:00+00:00'), $player, tags: [str_repeat('x', 101)])));
    }

    /** @return iterable<string, array{?string, bool}> */
    public static function imageUrls(): iterable
    {
        yield 'nahraný WebP' => ['/uploads/covers/'.'a94a8fe5ccb19ba61c4c0873d391e987982fbbd3'.'.webp', false];
        yield 'bez obrázku' => [null, false];
        yield 'externí adresa' => ['https://example.com/a.jpg', true];
        yield 'stará statika' => ['/static/covers/a.jpg', true];
        yield 'krátký název souboru' => ['/uploads/covers/a.webp', true];
        yield 'jiná přípona' => ['/uploads/covers/a94a8fe5ccb19ba61c4c0873d391e987982fbbd3.jpg', true];
        yield 'javascript' => ['javascript:alert(1)', true];
        yield 'protokolově relativní' => ['//evil.example/a.jpg', true];
        yield 'data URL' => ['data:text/html;base64,AAAA', true];
        yield 'jen název' => ['obrazek.jpg', true];
    }

    #[DataProvider('imageUrls')]
    public function testGameImageMustBeAnUploadedWebpAddress(?string $url, bool $invalid): void
    {
        self::assertSame($invalid, $this->invalid(new GameRequest(name: 'Hra', imageUrl: $url)));
        if (!$invalid && null !== $url) {
            self::assertMatchesRegularExpression(CoverStorage::URL_PATTERN, $url);
        }
    }

    public function testGameFieldsAreLimited(): void
    {
        self::assertFalse($this->invalid(new GameRequest(name: 'Catan', bggId: 13)));
        self::assertFalse($this->invalid(new GameRequest(name: 'Vlastní', bggId: null)));
        self::assertTrue($this->invalid(new GameRequest(name: 'Zlá', bggId: -1)));
        self::assertTrue($this->invalid(new GameRequest(name: 'Zlá', bggId: 0)));
        self::assertTrue($this->invalid(new GameRequest(name: '')));
        self::assertTrue($this->invalid(new GameRequest(name: str_repeat('x', 151))));
        self::assertTrue($this->invalid(new GameRequest(name: 'Hra', shortName: str_repeat('x', 51))));
        self::assertTrue($this->invalid(new GameRequest(name: 'Hra', addons: [new NamedItemRequest('')])), 'položky se validují taky');
        self::assertTrue($this->invalid(new GameRequest(name: 'Hra', characters: [new NamedItemRequest(str_repeat('x', 151))])));
    }

    public function testSmallRequests(): void
    {
        self::assertTrue($this->invalid(new BggImportRequest(0)));
        self::assertFalse($this->invalid(new BggImportRequest(13)));
        self::assertTrue($this->invalid(new NicknameRequest('X')), 'přezdívka má aspoň 2 znaky');
        self::assertTrue($this->invalid(new NicknameRequest('')));
        self::assertFalse($this->invalid(new NicknameRequest('Xena')));
        self::assertTrue($this->invalid(new DevLoginRequest('není e-mail')));
        self::assertFalse($this->invalid(new DevLoginRequest('x@example.com')));
        self::assertTrue($this->invalid(new PlayerNameRequest('   ')), 'jméno hosta nesmí být prázdné');
        self::assertFalse($this->invalid(new PlayerNameRequest('Host')));
        self::assertTrue($this->invalid(new LinkMemberRequest(0)));
        self::assertFalse($this->invalid(new LinkMemberRequest(5)));
    }
}
