<?php

use App\Support\Countdown;
use Illuminate\Support\Carbon;

test('countdown label formats days hours and minutes without seconds', function () {
    $endsAt = Carbon::parse('2026-09-01 15:04:00');
    $now = Carbon::parse('2026-08-30 10:34:00');

    expect(Countdown::label($endsAt, $now))->toBe('2d : 04h : 30m');
});

test('countdown label returns closed when the sale has ended', function () {
    $endsAt = Carbon::parse('2026-08-01 12:00:00');
    $now = Carbon::parse('2026-08-02 12:00:00');

    expect(Countdown::label($endsAt, $now))->toBe('Closed');
});
