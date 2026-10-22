<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Domain\Exception\DomainException;
use App\Service\CoverStorage;
use App\Tests\Support\FakeWebpConverter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Ukládání obrázků her: jen souborový systém v dočasném adresáři, převod na WebP dělá náhradní konvertor. */
final class CoverStorageTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/hesk-covers-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/covers/*') ?: [] as $file) {
            unlink($file);
        }
        @rmdir($this->dir.'/covers');
        @rmdir($this->dir);
    }

    private function storage(): CoverStorage
    {
        return new CoverStorage($this->dir, new FakeWebpConverter());
    }

    private function upload(string $content, int $error = \UPLOAD_ERR_OK): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'cover');
        file_put_contents($path, $content);

        return new UploadedFile($path, 'cover', null, $error, true);
    }

    /** Hlavička WebP (RIFF + VP8X) zadaného rozměru; server obrázek jen ověřuje podle hlavičky, nedekóduje ho. */
    private static function webp(int $width, int $height): string
    {
        $vp8x = pack('C4', 0, 0, 0, 0).substr(pack('V', $width - 1), 0, 3).substr(pack('V', $height - 1), 0, 3);

        return 'RIFF'.pack('V', 4 + 8 + \strlen($vp8x)).'WEBPVP8X'.pack('V', \strlen($vp8x)).$vp8x;
    }

    /** Jednobarevné PNG zadaného rozměru bez závislosti na GD. */
    private static function png(int $width, int $height): string
    {
        $chunk = static fn (string $type, string $data): string => pack('N', \strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $rows = str_repeat("\0".str_repeat("\x20\x60\xa0", $width), $height);

        return "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0)).$chunk('IDAT', (string) gzcompress($rows)).$chunk('IEND', '');
    }

    public function testSquareWebpIsStoredUnderItsContentHash(): void
    {
        $content = self::webp(200, 200);

        $url = $this->storage()->store($this->upload($content));

        self::assertMatchesRegularExpression(CoverStorage::URL_PATTERN, $url);
        self::assertSame('/uploads/covers/'.sha1($content).'.webp', $url);
        self::assertSame($content, file_get_contents($this->dir.'/covers/'.basename($url)));
        self::assertSame($url, $this->storage()->store($this->upload($content)), 'stejný obrázek = stejná adresa');
        self::assertCount(1, glob($this->dir.'/covers/*'));
    }

    public function testPngAndJpegAreConvertedToWebp(): void
    {
        $url = $this->storage()->store($this->upload(self::png(200, 200)));

        self::assertMatchesRegularExpression(CoverStorage::URL_PATTERN, $url);
        self::assertStringStartsWith('RIFF', (string) file_get_contents($this->dir.'/covers/'.basename($url)));
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidFiles(): iterable
    {
        yield 'obdélník' => [self::webp(200, 100), 'čtvercový'];
        yield 'příliš malý' => [self::webp(50, 50), '100 až 2048'];
        yield 'příliš velký' => [self::webp(4096, 4096), '100 až 2048'];
        yield 'obdélníkové PNG' => [self::png(200, 100), 'čtvercový'];
        yield 'není obrázek' => ['<?php echo 1;', 'WebP, JPEG nebo PNG'];
        yield 'GIF' => ['GIF89a'.str_repeat("\0", 20), 'WebP, JPEG nebo PNG'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidFiles')]
    public function testInvalidImagesAreRejected(string $content, string $message): void
    {
        try {
            $this->storage()->store($this->upload($content));
            self::fail('Obrázek se musí odmítnout.');
        } catch (DomainException $e) {
            self::assertStringContainsString($message, $e->getMessage());
        }
        self::assertDirectoryDoesNotExist($this->dir.'/covers');
    }

    public function testMissingOrBrokenUploadIsRejected(): void
    {
        foreach ([null, $this->upload(self::webp(200, 200), \UPLOAD_ERR_INI_SIZE), $this->upload(self::webp(200, 200).str_repeat('x', CoverStorage::MAX_BYTES))] as $file) {
            try {
                $this->storage()->store($file);
                self::fail('Nahrání se musí odmítnout.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
