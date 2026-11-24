<?php

use App\Models\MobileLoginCode;
use App\Services\MobileOtpService;
use App\Services\MobileSasaSmsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function mobileSasaConfig(): void
{
    config([
        'mobilesasa.base_url' => 'https://api.mobilesasa.com',
        'mobilesasa.token' => 'mbs_test_token',
        'mobilesasa.sender_id' => 'MOBILESASA',
        'mobilesasa.timeout' => 20,
    ]);
}

test('sign in otp stays on screen when mobilesasa is not connected', function () {
    Livewire::test('pages::auth.mobile-login')
        ->set('phone', '0768003470')
        ->call('requestCode')
        ->assertSet('step', 'code')
        ->assertSee('SMS placeholder')
        ->assertDontSee('Check your phone for the six-digit code');
});

test('sign in otp is sent by mobilesasa and hidden from the page', function () {
    mobileSasaConfig();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.mobilesasa.com/v2/send/message' => Http::response([
            'status' => true,
            'responseCode' => '0200',
            'message' => 'Accepted',
            'messageId' => 'sms-otp-1',
            'cost' => 1,
        ]),
    ]);

    $component = Livewire::test('pages::auth.mobile-login')
        ->set('phone', '0768003470')
        ->call('requestCode')
        ->assertSet('step', 'code')
        ->assertSet('placeholderCode', '')
        ->assertSee('Check your phone for the six-digit code')
        ->assertDontSee('SMS placeholder');

    expect(MobileLoginCode::query()->where('phone', '254768003470')->whereNull('consumed_at')->exists())->toBeTrue();

    Http::assertSent(function ($request): bool {
        $body = $request->data();

        return str_contains($request->url(), '/v2/send/message')
            && ($body['senderID'] ?? null) === 'MOBILESASA'
            && ($body['phone'] ?? null) === '254768003470'
            && str_contains((string) ($body['message'] ?? ''), 'UniqBid: Your code is')
            && str_starts_with((string) ($body['trackingId'] ?? ''), 'OTP-')
            && $request->hasHeader('Authorization', 'Bearer mbs_test_token');
    });

    $sentMessage = (string) collect(Http::recorded())->map(fn ($pair) => $pair[0]->data()['message'] ?? '')->first();
    preg_match('/\b(\d{6})\b/', $sentMessage, $matches);

    $component
        ->set('code', $matches[1])
        ->call('verifyCode')
        ->assertSet('step', 'profile');
});

test('a rejected mobilesasa send clears the unused code and stays on the phone step', function () {
    mobileSasaConfig();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.mobilesasa.com/v2/send/message' => Http::response([
            'status' => false,
            'responseCode' => '0402',
            'message' => 'Insufficient balance.',
        ], 200),
    ]);

    Livewire::test('pages::auth.mobile-login')
        ->set('phone', '0768003470')
        ->call('requestCode')
        ->assertHasErrors('phone')
        ->assertSet('step', 'phone');

    expect(MobileLoginCode::query()->where('phone', '254768003470')->count())->toBe(0)
        ->and(fn () => app(MobileSasaSmsService::class)->send(
            '254768003470',
            'UniqBid: Your sign-in code is 123456. It expires in 10 minutes.',
            'OTP-TESTFAIL01',
        ))->toThrow(ValidationException::class);
});

test('otp issue without credentials still returns a local placeholder code', function () {
    $code = app(MobileOtpService::class)->issue('254768003470');

    expect($code)->toHaveLength(6)
        ->and(MobileLoginCode::query()->where('phone', '254768003470')->exists())->toBeTrue();
});
