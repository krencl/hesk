<?php

declare(strict_types=1);

namespace App\Service\Image;

/**
 * Převod přes rozšíření GD. Vyžaduje PHP s GD zkompilovaným s podporou JPEG, PNG a WebP
 * (Debian/Ubuntu `php-gd` ji má; v Dockeru `docker-php-ext-configure gd --with-jpeg --with-webp`).
 */
final class GdWebpConverter implements WebpConverter
{
    public const QUALITY = 85;

    public function convert(string $path, int $imageType): string
    {
        if (!\function_exists('imagewebp') || !\function_exists('imagecreatefromjpeg') || !\function_exists('imagecreatefrompng')) {
            throw new \RuntimeException('Převod obrázků na WebP vyžaduje PHP rozšíření GD s podporou JPEG, PNG a WebP.');
        }
        $source = match ($imageType) {
            \IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            \IMAGETYPE_PNG => @imagecreatefrompng($path),
            default => throw new \InvalidArgumentException('Nepodporovaný typ obrázku.'),
        };
        if (false === $source) {
            throw new \RuntimeException('Obrázek se nepodařilo načíst.');
        }

        // průhledné části (PNG) by se ve WebP ukázaly jako černé/průhledné; podklad je bílý jako u ořezu v prohlížeči
        $width = imagesx($source);
        $height = imagesy($source);
        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);

        ob_start();
        $ok = imagewebp($canvas, null, self::QUALITY);
        $webp = (string) ob_get_clean();
        if (!$ok || '' === $webp) {
            throw new \RuntimeException('Obrázek se nepodařilo převést na WebP.');
        }

        return $webp;
    }
}
