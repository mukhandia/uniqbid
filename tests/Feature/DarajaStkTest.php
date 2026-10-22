<?php

use App\Models\Auction;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Services\AuctionBidService;
use App\Services\DarajaStkService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function darajaAuction(): Auction
{
    return Auction::factory()->create([
        'min_bid' => 10,
        'max_bid' => 1000,
        'bid_increment' => 5,
        'bid_fee' => 15,
        'bids_count' => 0,
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);
}

function configureDaraja(): void
{
    config([
        'mpesa.base_url' => 'https://sandbox.safaricom.co.ke',
        'mpesa.consumer_key' => 'key',
        'mpesa.consumer_secret' => 'secret',
        'mpesa.passkey' => 'passkey',
        'mpesa.shortcode' => '174379',
        'mpesa.callback_url' => 'https://example.test/mpesa/stk/callback',
        'mpesa.timeout' => 20,
    ]);
}

function participationAttempt(Auction $auction): PaymentAttempt
{
    return app(AuctionBidService::class)->initiate($auction, 15, [
        'name' => 'Amina Wanjiku',
        'phone' => '0712345678',
    ]);
}

test('stk push sends the participation fee to the bidders phone', function () {
    configureDaraja();
    Http::preventStrayRequests();
    Http::fake([
        '*/oauth/v1/generate*' => Http::response([
            'access_token' => 'token-1',
            'expires_in' => 3599,
        ]),
        '*/mpesa/stkpush/v1/processrequest' => Http::response([
            'MerchantRequestID' => 'm-1',
            'CheckoutRequestID' => 'ws_CO_123',
            'ResponseCode' => '0',
            'ResponseDescription' => 'Success. Request accepted for processing',
            'CustomerMessage' => 'Success. Request accepted for processing',
        ]),
    ]);

    $attempt = participationAttempt(darajaAuction());

    app(DarajaStkService::class)->requestPayment($attempt);

    $attempt->refresh();

    expect($attempt->checkout_request_id)->toBe('ws_CO_123')
        ->and($attempt->merchant_request_id)->toBe('m-1')
        ->and($attempt->status)->toBe('initiated');

    Http::assertSent(function (Request $request) use ($attempt) {
        if (! str_ends_with($request->url(), '/mpesa/stkpush/v1/processrequest')) {
            return false;
        }

        return $request['Amount'] === $attempt->amount
            && $request['PhoneNumber'] === '254712345678'
            && $request['PartyA'] === '254712345678'
            && $request['PartyB'] === '174379'
            && $request['BusinessShortCode'] === '174379'
            && $request['CallBackURL'] === 'https://example.test/mpesa/stk/callback'
            && $request['TransactionType'] === 'CustomerPayBillOnline'
            && $request['TransactionDesc'] === 'Participation';
    });
});

test('a rejected stk prompt fails the participation fee', function () {
    configureDaraja();
    Http::preventStrayRequests();
    Http::fake([
        '*/oauth/v1/generate*' => Http::response([
            'access_token' => 'token-1',
            'expires_in' => 3599,
        ]),
        '*/mpesa/stkpush/v1/processrequest' => Http::response([
            'errorCode' => '400.002.02',
            'errorMessage' => 'Bad Request - Invalid PhoneNumber',
        ], 400),
    ]);

    $attempt = participationAttempt(darajaAuction());

    expect(fn () => app(DarajaStkService::class)->requestPayment($attempt))
        ->toThrow(ValidationException::class);

    expect($attempt->fresh()->status)->toBe('failed')
        ->and($attempt->fresh()->auctionBid->status)->toBe('failed');
});

test('an stk success callback accepts the entry once', function () {
    $auction = darajaAuction();
    $attempt = participationAttempt($auction);
    $attempt->update(['checkout_request_id' => 'ws_CO_123']);

    $payload = [
        'Body' => [
            'stkCallback' => [
                'MerchantRequestID' => 'm-1',
                'CheckoutRequestID' => 'ws_CO_123',
                'ResultCode' => 0,
                'ResultDesc' => 'The service request is processed successfully.',
                'CallbackMetadata' => [
                    'Item' => [
                        ['Name' => 'Amount', 'Value' => 15],
                        ['Name' => 'MpesaReceiptNumber', 'Value' => 'ABC123XYZ'],
                        ['Name' => 'PhoneNumber', 'Value' => 254712345678],
                    ],
                ],
            ],
        ],
    ];

    $this->postJson(route('mpesa.stk.callback'), $payload)
        ->assertSuccessful()
        ->assertJson(['ResultCode' => 0]);

    $this->postJson(route('mpesa.stk.callback'), $payload)->assertSuccessful();

    $attempt->refresh();

    expect($attempt->status)->toBe('succeeded')
        ->and($attempt->mpesa_receipt)->toBe('ABC123XYZ')
        ->and($attempt->auctionBid->status)->toBe('accepted')
        ->and($auction->fresh()->bids_count)->toBe(1)
        ->and(User::query()->where('phone', '254712345678')->count())->toBe(1);
});

test('an stk cancellation callback fails the entry', function () {
    $attempt = participationAttempt(darajaAuction());
    $attempt->update(['checkout_request_id' => 'ws_CO_cancel']);

    $this->postJson(route('mpesa.stk.callback'), [
        'Body' => [
            'stkCallback' => [
                'CheckoutRequestID' => 'ws_CO_cancel',
                'ResultCode' => 1032,
                'ResultDesc' => 'Request cancelled by user',
            ],
        ],
    ])->assertSuccessful();

    expect($attempt->fresh()->status)->toBe('failed')
        ->and($attempt->fresh()->failure_reason)->toBe('Request cancelled by user')
        ->and($attempt->fresh()->auctionBid->status)->toBe('failed')
        ->and(User::query()->where('phone', '254712345678')->exists())->toBeFalse();
});

test('an unknown stk callback is accepted without changing a payment', function () {
    $this->postJson(route('mpesa.stk.callback'), [
        'Body' => [
            'stkCallback' => [
                'CheckoutRequestID' => 'missing',
                'ResultCode' => 0,
                'ResultDesc' => 'The service request is processed successfully.',
            ],
        ],
    ])->assertSuccessful();

    expect(PaymentAttempt::query()->count())->toBe(0);
});

test('the sale page completes when safaricom has already confirmed the fee', function () {
    $auction = darajaAuction();

    $component = Livewire::test('pages::auctions.show', ['auction' => $auction])
        ->set('phone', '0712345678')
        ->call('continueWithPhone')
        ->set('name', 'Amina Wanjiku')
        ->set('amount', '15')
        ->set('rulesConsent', true)
        ->call('submitBid')
        ->assertSet('formState', 'pending')
        ->assertSet('awaitsMpesa', false);

    $attempt = PaymentAttempt::query()->firstOrFail();
    app(AuctionBidService::class)->markSucceeded($attempt);

    $component->call('refreshPayment')
        ->assertSet('formState', 'success')
        ->assertSet('accountCreated', true);

    $this->assertAuthenticated();
});
