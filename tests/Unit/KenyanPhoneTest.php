<?php

use App\Services\AuctionBidService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

test('normalizes Kenyan 07 and 01 mobile numbers', function (string $input, string $expected) {
    expect(app(AuctionBidService::class)->normalizeKenyanPhone($input))->toBe($expected);
})->with([
    'local 07' => ['0712345678', '254712345678'],
    'local 01' => ['0112345678', '254112345678'],
    'spaced 01' => ['0112 345 678', '254112345678'],
    'plus 254 07' => ['+254 712 345 678', '254712345678'],
    'plus 254 01' => ['+254 112 345 678', '254112345678'],
    'stored 01' => ['254112345678', '254112345678'],
    '2540 01 concatenation' => ['2540112345678', '254112345678'],
    '2540 07 concatenation' => ['2540712345678', '254712345678'],
]);

test('rejects numbers that are not Kenyan 07 or 01 mobiles', function (string $input) {
    expect(fn () => app(AuctionBidService::class)->normalizeKenyanPhone($input))
        ->toThrow(ValidationException::class);
})->with([
    'landline' => ['0201234567'],
    'too short' => ['071234567'],
    'too long' => ['07123456789'],
    'empty' => [''],
]);
