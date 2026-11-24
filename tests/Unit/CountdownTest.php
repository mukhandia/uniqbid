<?php

use App\Support\Countdown;
use Illuminate\Support\Carbon;

test('countdown label formats hours minutes and seconds without days', function () {
    $endsAt = Carbon::parse('2026-09-01 15:04:00');
    $now = Carbon::parse('2026-08-30 10:34:00');

    expect(Countdown::label($endsAt, $now))->toBe('52h : 30m : 00s');
});

test('countdown label includes remaining seconds under one hour', function () {
    $endsAt = Carbon::parse('2026-08-30 10:35:45');
    $now = Carbon::parse('2026-08-30 10:34:00');

    expect(Countdown::label($endsAt, $now))->toBe('0h : 01m : 45s');
});

test('countdown label returns closed when the sale has ended', function () {
    $endsAt = Carbon::parse('2026-08-01 12:00:00');
    $now = Carbon::parse('2026-08-02 12:00:00');

    expect(Countdown::label($endsAt, $now))->toBe('Closed');
});
