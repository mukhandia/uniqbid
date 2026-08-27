<?php

namespace App\Support;

use Illuminate\Support\Str;
use Illuminate\Support\Uri;

class MapEmbedUrl
{
    /**
     * @var list<string>
     */
    private const ALLOWED_HOSTS = [
        'google.com',
        'www.google.com',
        'maps.google.com',
        'maps.app.goo.gl',
        'goo.gl',
        'www.goo.gl',
        'openstreetmap.org',
        'www.openstreetmap.org',
    ];

    public static function embed(?string $shareUrl, ?string $address = null): ?string
    {
        $fromShare = self::fromShare($shareUrl);

        if ($fromShare !== null) {
            return $fromShare;
        }

        $address = trim((string) $address);

        if ($address === '') {
            return null;
        }

        return 'https://www.google.com/maps?'.http_build_query([
            'q' => $address,
            'output' => 'embed',
        ]);
    }

    public static function isAllowedShare(?string $value): bool
    {
        $url = self::extractUrl($value);

        if ($url === null) {
            return true;
        }

        return self::parsedAllowlistedHttps($url) !== null;
    }

    public static function fromShare(?string $value): ?string
    {
        $url = self::extractUrl($value);

        if ($url === null) {
            return null;
        }

        $parts = self::parsedAllowlistedHttps($url);

        if ($parts === null) {
            return null;
        }

        [$uri, $host, $path, $query, $fragment] = $parts;

        if (
            Str::contains($path, 'maps/embed')
            || ($query['output'] ?? null) === 'embed'
            || Str::contains($path, 'export/embed.html')
        ) {
            return (string) $uri;
        }

        if (Str::contains($host, 'openstreetmap.org')) {
            return self::openStreetMapEmbed($fragment, $query);
        }

        if (preg_match('/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)(?:,(\d+)z)?/', (string) $uri, $matches) === 1) {
            $zoom = filled($matches[3] ?? null) ? (int) $matches[3] : 16;

            return 'https://www.google.com/maps?'.http_build_query([
                'q' => $matches[1].','.$matches[2],
                'z' => $zoom,
                'output' => 'embed',
            ]);
        }

        if (filled($query['q'] ?? null)) {
            return 'https://www.google.com/maps?'.http_build_query([
                'q' => $query['q'],
                'output' => 'embed',
            ]);
        }

        $place = Str::after($path, 'maps/place/');

        if ($place !== $path && $place !== '') {
            $name = Str::before($place, '/');

            return 'https://www.google.com/maps?'.http_build_query([
                'q' => urldecode(str_replace('+', ' ', $name)),
                'output' => 'embed',
            ]);
        }

        return null;
    }

    private static function extractUrl(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/\bsrc\s*=\s*[\'"]([^\'"]+)[\'"]/i', $value, $matches) === 1) {
            $value = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
        }

        return $value;
    }

    /**
     * @return array{0: Uri, 1: string, 2: string, 3: array<string, string>, 4: string}|null
     */
    private static function parsedAllowlistedHttps(string $url): ?array
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $uri = Uri::of($url);

        if ($uri->scheme() !== 'https') {
            return null;
        }

        $host = Str::lower((string) $uri->host());

        if (! in_array($host, self::ALLOWED_HOSTS, true)) {
            return null;
        }

        /** @var array<string, string> $query */
        $query = $uri->query()->all();

        return [$uri, $host, $uri->path(), $query, (string) $uri->fragment()];
    }

    /**
     * @param  array<string, string>  $query
     */
    private static function openStreetMapEmbed(string $fragment, array $query): ?string
    {
        $lat = $query['mlat'] ?? null;
        $lon = $query['mlon'] ?? null;
        $zoom = 16;

        if (preg_match('#map=(\d+)/(-?\d+(?:\.\d+)?)/(-?\d+(?:\.\d+)?)#', $fragment, $matches) === 1) {
            $zoom = (int) $matches[1];
            $lat ??= $matches[2];
            $lon ??= $matches[3];
        }

        if ($lat === null || $lon === null) {
            return null;
        }

        $pad = 0.008;

        return 'https://www.openstreetmap.org/export/embed.html?'.http_build_query([
            'bbox' => ($lon - $pad).','.($lat - $pad).','.($lon + $pad).','.($lat + $pad),
            'layer' => 'mapnik',
            'marker' => $lat.','.$lon,
        ]);
    }
}
