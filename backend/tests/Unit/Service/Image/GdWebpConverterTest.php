<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Image;

use App\Service\Image\GdWebpConverter;
use PHPUnit\Framework\TestCase;

/** Běží jen tam, kde má PHP GD s JPEG, PNG a WebP (produkční image); jinde se přeskočí. */
final class GdWebpConverterTest extends TestCase
{
    protected function setUp(): void
    {
        foreach (['imagewebp', 'imagecreatefromjpeg', 'imagecreatefrompng'] as $function) {
            if (!\function_exists($function)) {
                self::markTestSkipped("GD bez podpory ($function) – převod na WebP se tu nedá ověřit.");
            }
        }
    }

    /** @return iterable<string, array{int}> */
    public static function formats(): iterable
    {
        yield 'jpeg' => [\IMAGETYPE_JPEG];
        yield 'png' => [\IMAGETYPE_PNG];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('formats')]
    public function testConvertsToAWebpOfTheSameSize(int $type): void
    {
        $image = imagecreatetruecolor(120, 120);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 200, 50, 50));
        $path = tempnam(sys_get_temp_dir(), 'gd');
        \IMAGETYPE_JPEG === $type ? imagejpeg($image, $path) : imagepng($image, $path);

        try {
            $webp = (new GdWebpConverter())->convert($path, $type);
            file_put_contents($path, $webp);
            $info = getimagesize($path);
            self::assertSame(\IMAGETYPE_WEBP, $info[2]);
            self::assertSame([120, 120], [$info[0], $info[1]]);
        } finally {
            @unlink($path);
        }
    }

    public function testTransparentPngGetsAWhiteBackground(): void
    {
        $image = imagecreatetruecolor(100, 100);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 0, 0, 0, 127));
        $path = tempnam(sys_get_temp_dir(), 'gd');
        imagepng($image, $path);

        try {
            file_put_contents($path, (new GdWebpConverter())->convert($path, \IMAGETYPE_PNG));
            $pixel = imagecolorat(imagecreatefromwebp($path), 50, 50);
            self::assertSame([255, 255, 255], [($pixel >> 16) & 0xFF, ($pixel >> 8) & 0xFF, $pixel & 0xFF]);
        } finally {
            @unlink($path);
        }
    }
}
