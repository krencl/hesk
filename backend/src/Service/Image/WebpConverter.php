<?php

declare(strict_types=1);

namespace App\Service\Image;

/** Převádí nahraný obrázek (JPEG/PNG) na WebP. */
interface WebpConverter
{
    /**
     * @param int $imageType jedna z konstant `IMAGETYPE_JPEG` / `IMAGETYPE_PNG`
     *
     * @return string obsah WebP souboru
     */
    public function convert(string $path, int $imageType): string;
}
