<?php

use App\Support\PlaceholderImage;

test('builds an svg data url placeholder', function () {
    $url = PlaceholderImage::make('UniqBid');

    expect($url)->toStartWith('data:image/svg+xml;utf8,')
        ->and(urldecode($url))->toContain('UniqBid')
        ->and(urldecode($url))->toContain('#1a3a6b');
});

test('escapes labels in placeholder svg', function () {
    $url = PlaceholderImage::make('A & B <script>');

    expect(urldecode($url))
        ->toContain('A &amp; B &lt;script&gt;')
        ->not->toContain('<script>');
});
