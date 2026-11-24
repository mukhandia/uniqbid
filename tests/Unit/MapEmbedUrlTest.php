<?php

use App\Support\MapEmbedUrl;
use Tests\TestCase;

uses(TestCase::class);

test('converts a google maps place url with coordinates into an embed url', function () {
    $url = 'https://www.google.com/maps/place/Westlands/@-1.267,36.811,17z';

    expect(MapEmbedUrl::fromShare($url))
        ->toContain('output=embed')
        ->toContain('-1.267')
        ->toContain('36.811');
});

test('keeps an existing google maps embed url', function () {
    $url = 'https://www.google.com/maps/embed?pb=!1m18!1m12';

    expect(MapEmbedUrl::fromShare($url))->toBe($url);
});

test('extracts an iframe src from pasted embed html', function () {
    $html = '<iframe src="https://www.google.com/maps?q=Westlands&output=embed" width="600" height="450"></iframe>';

    expect(MapEmbedUrl::fromShare($html))
        ->toContain('google.com/maps')
        ->toContain('output=embed');
});

test('converts an openstreetmap share url into an embed url', function () {
    $url = 'https://www.openstreetmap.org/#map=18/-1.268/36.811';

    expect(MapEmbedUrl::fromShare($url))
        ->toContain('openstreetmap.org/export/embed.html')
        ->toContain('marker=-1.268%2C36.811');
});

test('rejects urls that are not google maps or openstreetmap', function () {
    expect(MapEmbedUrl::isAllowedShare('https://evil.example/map'))->toBeFalse()
        ->and(MapEmbedUrl::fromShare('https://evil.example/map'))->toBeNull();
});

test('falls back to an address search embed when the share url cannot be converted', function () {
    $embed = MapEmbedUrl::embed('https://maps.app.goo.gl/abc123', 'Westlands Business Centre, Nairobi');

    expect($embed)
        ->toContain('google.com/maps')
        ->toContain('Westlands')
        ->toContain('output=embed');
});
