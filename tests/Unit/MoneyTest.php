<?php

use App\Support\Money;

test('formats kenya shilling amounts without decimals', function () {
    expect(Money::kes(0))->toBe('KES 0')
        ->and(Money::kes(22))->toBe('KES 22')
        ->and(Money::kes(1500))->toBe('KES 1,500')
        ->and(Money::kes(250000))->toBe('KES 250,000');
});

test('casts float amounts to integers before formatting', function () {
    expect(Money::kes(99.9))->toBe('KES 99');
});
