<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Service\Image\WebpConverter;

/** Náhrada GD v testech (dev kontejner nemá GD s WebP): vrátí deterministický „WebP“ podle obsahu zdroje. */
final class FakeWebpConverter implements WebpConverter
{
    public function convert(string $path, int $imageType): string
    {
        return 'RIFF'.pack('V', 4).'WEBPfake-'.sha1_file($path);
    }
}
