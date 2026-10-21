<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Exception\DomainException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Ukládá obrázky her nahrané z prohlížeče (ADR 0020). Klient je ořízne na čtverec a zakóduje jako WebP, server jen
 * ověří, že jde opravdu o čtvercový WebP rozumné velikosti, a uloží ho pod názvem podle obsahu (stejný soubor = stejná
 * adresa). Interně existuje jen WebP, takže adresa obrázku vždy odpovídá `URL_PATTERN`.
 */
final class CoverStorage
{
    public const URL_PREFIX = '/uploads/covers/';
    public const MAX_BYTES = 2 * 1024 * 1024;
    public const MIN_SIDE = 100;
    public const MAX_SIDE = 2048;

    /** Adresa uloženého obrázku; jediná hodnota, kterou smí mít `Game::imageUrl`. */
    public const URL_PATTERN = '~^/uploads/covers/[0-9a-f]{40}\.webp$~';

    public function __construct(#[Autowire('%app.uploads_dir%')] private readonly string $uploadsDir)
    {
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
        if (false === $info || \IMAGETYPE_WEBP !== $info[2]) {
            throw new DomainException('Obrázek musí být ve formátu WebP.');
        }
        [$width, $height] = $info;
        if ($width !== $height) {
            throw new DomainException('Obrázek musí být čtvercový.');
        }
        if ($width < self::MIN_SIDE || $width > self::MAX_SIDE) {
            throw new DomainException(sprintf('Obrázek musí mít %d až %d px.', self::MIN_SIDE, self::MAX_SIDE));
        }

        $name = sha1_file($file->getPathname()).'.webp';
        $dir = rtrim($this->uploadsDir, '/').'/covers';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Adresář pro obrázky nejde vytvořit.');
        }
        if (!is_file("$dir/$name")) {
            $file->move($dir, $name);
        }

        return self::URL_PREFIX.$name;
    }
}
