<?php

use App\Support\DarajaStkResult;

test('stk result codes only close a payment when the outcome is settled', function (int $code, string $outcome) {
    expect(DarajaStkResult::classify($code))->toBe($outcome);
})->with([
    'paid' => [0, DarajaStkResult::SUCCESS],
    'insufficient balance' => [1, DarajaStkResult::FAILED],
    'expired' => [1019, DarajaStkResult::FAILED],
    'prompt not sent' => [1025, DarajaStkResult::FAILED],
    'prompt not sent alias' => [9999, DarajaStkResult::FAILED],
    'cancelled' => [1032, DarajaStkResult::FAILED],
    'phone did not respond' => [1037, DarajaStkResult::FAILED],
    'pin rejected' => [2001, DarajaStkResult::FAILED],
    'still processing' => [4999, DarajaStkResult::PENDING],
    'another session' => [1001, DarajaStkResult::PENDING],
    'internal failure' => [17, DarajaStkResult::PENDING],
    'system busy' => [26, DarajaStkResult::PENDING],
    'unknown code' => [4242, DarajaStkResult::PENDING],
]);
