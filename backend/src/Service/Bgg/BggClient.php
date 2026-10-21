<?php

declare(strict_types=1);

namespace App\Service\Bgg;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Klient BGG XML API2 (ADR 0021). API vyžaduje registrovanou aplikaci a `Authorization: Bearer` token
 * (`BGG_API_TOKEN`), požadavky musí mířit na `boardgamegeek.com` bez `www`. Bez tokenu je klient „nenastavený“
 * a každé volání končí `BggUnavailableException`.
 */
class BggClient
{
    public const BASE_URL = 'https://boardgamegeek.com/xmlapi2';
    public const MAX_RESULTS = 20;
    public const MAX_IMAGE_BYTES = 10 * 1024 * 1024;
    /** Obrázky servíruje jen tato doména (a její subdomény, např. `cf.geekdo-images.com`). */
    private const IMAGE_HOST = 'geekdo-images.com';

    public function __construct(
        private readonly HttpClientInterface $http,
        #[Autowire('%env(default::BGG_API_TOKEN)%')]
        private readonly ?string $token = null,
    ) {
    }

    public function isConfigured(): bool
    {
        return null !== $this->token && '' !== trim($this->token);
    }

    /**
     * Hledání deskových her (bez rozšíření) podle názvu; shody na začátku názvu jsou první.
     *
     * @return list<BggItem>
     */
    public function search(string $query): array
    {
        $items = [];
        foreach ($this->xpath($this->request('/search', ['query' => $query, 'type' => 'boardgame']), '/items/item') as $node) {
            $item = $this->parseItem($node);
            if (null !== $item) {
                $items[] = $item;
            }
        }

        $needle = mb_strtolower($query);
        $rank = static fn (BggItem $i): int => match (true) {
            mb_strtolower($i->name) === $needle => 0,
            str_starts_with(mb_strtolower($i->name), $needle) => 1,
            default => 2,
        };
        // stabilní řazení: při stejné shodě zůstává pořadí z BGG
        $indexed = array_map(null, $items, array_keys($items));
        usort($indexed, static fn (array $a, array $b): int => [$rank($a[0]), $a[1]] <=> [$rank($b[0]), $b[1]]);

        return \array_slice(array_map(static fn (array $pair): BggItem => $pair[0], $indexed), 0, self::MAX_RESULTS);
    }

    /** Detail hry včetně adresy obrázku; null, když hra na BGG neexistuje. */
    public function thing(int $id): ?BggItem
    {
        foreach ($this->xpath($this->request('/thing', ['id' => $id]), '/items/item') as $node) {
            return $this->parseItem($node);
        }

        return null;
    }

    /**
     * Stáhne obrázek z BGG CDN; prohlížeč ho přes proxy `GET /api/bgg/image` načítá do ořezového editoru ze stejného
     * originu, takže canvas nezávisí na CORS hlavičkách CDN.
     *
     * @return array{content: string, type: string} `type` je `image/jpeg`, `image/png` nebo `image/webp`
     */
    public function fetchImage(string $url): array
    {
        $parts = parse_url($url);
        $host = \is_array($parts) ? strtolower((string) ($parts['host'] ?? '')) : '';
        if ('https' !== ($parts['scheme'] ?? null) || isset($parts['user']) || isset($parts['port']) || (self::IMAGE_HOST !== $host && !str_ends_with($host, '.'.self::IMAGE_HOST))) {
            throw new BggUnavailableException('Obrázek není z BGG CDN.');
        }

        try {
            $response = $this->http->request('GET', $url, [
                'max_redirects' => 0,
                'timeout' => 10,
                'max_duration' => 30,
                'on_progress' => static function (int $downloaded): void {
                    if ($downloaded > self::MAX_IMAGE_BYTES) {
                        throw new BggUnavailableException('Obrázek je příliš velký.');
                    }
                },
            ]);
            if (200 !== $response->getStatusCode()) {
                throw new BggUnavailableException('Obrázek se nepodařilo stáhnout.');
            }
            $content = $response->getContent();
        } catch (ExceptionInterface $e) {
            throw new BggUnavailableException('Obrázek se nepodařilo stáhnout.', 0, $e);
        }

        // typ se určuje podle obsahu, ne podle hlavičky protistrany
        $type = match (@getimagesizefromstring($content)[2] ?? null) {
            \IMAGETYPE_JPEG => 'image/jpeg',
            \IMAGETYPE_PNG => 'image/png',
            \IMAGETYPE_WEBP => 'image/webp',
            default => throw new BggUnavailableException('Z BGG nepřišel obrázek.'),
        };

        return ['content' => $content, 'type' => $type];
    }

    /** @param array<string, scalar> $query */
    private function request(string $endpoint, array $query): \DOMDocument
    {
        if (!$this->isConfigured()) {
            throw new BggUnavailableException('Přístup k BGG není nastavený (chybí BGG_API_TOKEN).');
        }
        try {
            $response = $this->http->request('GET', self::BASE_URL.$endpoint, [
                'query' => $query,
                'headers' => ['Authorization' => 'Bearer '.$this->token, 'Accept' => 'application/xml'],
                'timeout' => 10,
                'max_duration' => 20,
            ]);
            $status = $response->getStatusCode();
            if (200 !== $status) {
                throw new BggUnavailableException(match (true) {
                    \in_array($status, [401, 403], true) => 'BGG odmítlo přístupový token.',
                    429 === $status => 'BGG je teď přetížené, zkus to za chvíli.',
                    default => "BGG vrátilo chybu $status.",
                });
            }
            $body = $response->getContent();
        } catch (ExceptionInterface $e) {
            throw new BggUnavailableException('BGG se nepodařilo zavolat.', 0, $e);
        }

        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($body, \LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            throw new BggUnavailableException('BGG vrátilo nečitelnou odpověď.');
        }

        return $doc;
    }

    /** @return list<\DOMElement> */
    private function xpath(\DOMDocument $doc, string $expression): array
    {
        $nodes = [];
        foreach ((new \DOMXPath($doc))->query($expression) ?: [] as $node) {
            if ($node instanceof \DOMElement) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    private function parseItem(\DOMElement $node): ?BggItem
    {
        $id = (int) $node->getAttribute('id');
        $xpath = new \DOMXPath($node->ownerDocument);
        $name = $xpath->evaluate('string(name[@type="primary"]/@value)', $node);
        if ($id <= 0 || !\is_string($name) || '' === trim($name)) {
            return null;
        }
        $year = (int) $xpath->evaluate('string(yearpublished/@value)', $node);
        $image = trim((string) $xpath->evaluate('string(image)', $node));

        return new BggItem($id, trim($name), $year > 0 ? $year : null, '' !== $image ? $image : null);
    }
}
