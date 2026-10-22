<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Exception\DomainException;
use App\Service\Image\WebpConverter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Ukládá obrázky her nahrané z prohlížeče (ADR 0020). Klient je ořízne na čtverec a pošle jako WebP (nebo JPEG, když
 * prohlížeč WebP nekóduje, např. Safari na iPhonu). Server ověří, že jde o čtvercový obrázek rozumné velikosti,
 * JPEG/PNG převede na WebP a uloží ho pod názvem podle obsahu (stejný soubor = stejná adresa). Interně existuje jen
 * WebP, takže adresa obrázku vždy odpovídá `URL_PATTERN`.
 */
final class CoverStorage
{
    public const URL_PREFIX = '/uploads/covers/';
    public const MAX_BYTES = 2 * 1024 * 1024;
    public const MIN_SIDE = 100;
    public const MAX_SIDE = 2048;

    /** Adresa uloženého obrázku; jediná hodnota, kterou smí mít `Game::imageUrl`. */
    public const URL_PATTERN = '~^/uploads/covers/[0-9a-f]{40}\.webp$~';

    public function __construct(
        #[Autowire('%app.uploads_dir%')]
        private readonly string $uploadsDir,
        private readonly WebpConverter $converter,
    ) {
    }

    /** @return string adresa uloženého obrázku (začíná lomítkem, hodí se rovnou do `Game::imageUrl`) */
    public function store(?UploadedFile $file): string
    {
        if (null === $file) {
            throw new DomainException('Chybí soubor s obrázkem.');
        }
        if (!$file->isValid() || $file->getSize() > self::MAX_BYTES) {
            throw new DomainException('Obrázek se nepodařilo nahrát (maximum jsou 2 MB).');
        }
        $info = @getimagesize($file->getPathname());
        if (false === $info || !\in_array($info[2], [\IMAGETYPE_WEBP, \IMAGETYPE_JPEG, \IMAGETYPE_PNG], true)) {
            throw new DomainException('Obrázek musí být ve formátu WebP, JPEG nebo PNG.');
        }
        [$width, $height] = $info;
        if ($width !== $height) {
            throw new DomainException('Obrázek musí být čtvercový.');
        }
        if ($width < self::MIN_SIDE || $width > self::MAX_SIDE) {
            throw new DomainException(sprintf('Obrázek musí mít %d až %d px.', self::MIN_SIDE, self::MAX_SIDE));
        }

        $content = \IMAGETYPE_WEBP === $info[2] ? (string) file_get_contents($file->getPathname()) : $this->converter->convert($file->getPathname(), $info[2]);
        $name = sha1($content).'.webp';
        $dir = rtrim($this->uploadsDir, '/').'/covers';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Adresář pro obrázky nejde vytvořit.');
        }
        if (!is_file("$dir/$name")) {
            file_put_contents("$dir/$name", $content);
        }

        return self::URL_PREFIX.$name;
    }
}
