<?php

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\Bidder;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Services\ParticipationRefundService;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

function voidedFeeBid(int $fee = 15, int $amount = 400, string $phone = '254708374149', ?Bidder $bidder = null): AuctionBid
{
    $auction = Auction::factory()->create([
        'status' => 'voided',
        'bid_fee' => $fee,
        'settled_at' => now(),
    ]);

    return feeBidOn($auction, $fee, $amount, $phone, $bidder);
}

function feeBidOn(Auction $auction, int $fee, int $amount, string $phone, ?Bidder $bidder = null): AuctionBid
{
    $bidder ??= Bidder::factory()->create([
        'phone' => $phone,
        'user_id' => User::factory()->create([
            'phone' => $phone,
            'phone_verified_at' => now(),
            'is_admin' => false,
        ])->id,
    ]);

    $bid = AuctionBid::factory()->accepted()->create([
        'auction_id' => $auction->id,
        'bidder_id' => $bidder->id,
        'amount' => $amount,
    ]);

    $bid->paymentAttempts()->create([
        'provider' => 'mpesa_daraja_stk_push',
        'reference' => 'PAY'.str()->upper(str()->random(8)),
        'amount' => $fee,
        'status' => 'succeeded',
        'request_payload' => [
            'phone' => $phone,
            'amount' => $fee,
        ],
    ]);

    return $bid->fresh(['auction', 'bidder.user']);
}

function refundB2cConfig(): void
{
    config([
        'mpesa.base_url' => 'https://sandbox.safaricom.co.ke',
        'mpesa.consumer_key' => 'key',
        'mpesa.consumer_secret' => 'secret',
        'mpesa.initiator_name' => 'testapi',
        'mpesa.security_credential' => 'credential',
        'mpesa.b2c_shortcode' => '600000',
        'mpesa.b2c_result_url' => 'https://example.test/mpesa/b2c/result',
        'mpesa.b2c_timeout_url' => 'https://example.test/mpesa/b2c/timeout',
        'mpesa.timeout' => 20,
    ]);
}

test('staff cannot send a participation refund when mpesa is not connected', function () {
    $bid = voidedFeeBid();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::admin.auction-bids', ['auction' => $bid->auction])
        ->assertSee('Refund to customer')
        ->call('initiateParticipationRefund', $bid->id)
        ->assertSee('Refund to customer');

    expect(PaymentAttempt::query()->where('provider', 'mpesa_daraja_b2c')->count())->toBe(0);
});

test('a participation refund below kes 10 is refused before mpesa is called', function () {
    refundB2cConfig();
    $bid = voidedFeeBid(5);

    expect(fn () => app(ParticipationRefundService::class)->beginRefund($bid))
        ->toThrow(ValidationException::class);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::admin.auction-bids', ['auction' => $bid->auction])
        ->assertSee('This fee is below the M-Pesa minimum of KES 10, so it cannot be sent.')
        ->assertDontSee('Refund to customer');

    expect(PaymentAttempt::query()->where('provider', 'mpesa_daraja_b2c')->count())->toBe(0);
});

test('a live sale and an unpaid bid have no refund button', function () {
    refundB2cConfig();
    $live = Auction::factory()->create([
        'status' => 'live',
        'bid_fee' => 15,
        'ends_at' => now()->addDay(),
    ]);
    $paid = feeBidOn($live, 15, 400, '254708374149');
    $voided = Auction::factory()->create([
        'status' => 'voided',
        'bid_fee' => 15,
        'settled_at' => now(),
    ]);
    $unpaid = AuctionBid::factory()->create([
        'auction_id' => $voided->id,
        'amount' => 80,
        'status' => 'pending_payment',
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::admin.auction-bids', ['auction' => $live])
        ->assertDontSee('Refund to customer')
        ->call('initiateParticipationRefund', $paid->id);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::admin.auction-bids', ['auction' => $voided])
        ->assertDontSee('Refund to customer');

    expect(PaymentAttempt::query()->where('provider', 'mpesa_daraja_b2c')->count())->toBe(0)
        ->and($unpaid->fresh()->status)->toBe('pending_payment');
});

test('one bidder with two paid fees gets two refunds of the fee not the bid amount', function () {
    refundB2cConfig();
    Http::preventStrayRequests();
    Http::fake([
        '*/oauth/v1/generate*' => Http::response(['access_token' => 'token-1', 'expires_in' => 3599]),
        '*/mpesa/b2c/v3/paymentrequest' => Http::sequence()
            ->push([
                'ConversationID' => 'AG_refund_pair_1',
                'OriginatorConversationID' => 'RFDPAIR01',
                'ResponseCode' => '0',
                'ResponseDescription' => 'Accept the service request successfully.',
            ])
            ->push([
                'ConversationID' => 'AG_refund_pair_2',
                'OriginatorConversationID' => 'RFDPAIR02',
                'ResponseCode' => '0',
                'ResponseDescription' => 'Accept the service request successfully.',
            ]),
    ]);
    $auction = Auction::factory()->create([
        'status' => 'voided',
        'bid_fee' => 15,
        'settled_at' => now(),
    ]);
    $first = feeBidOn($auction, 15, 100, '254708374149');
    $second = feeBidOn($auction, 20, 250, '254708374149', $first->bidder);
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.auction-bids', ['auction' => $auction])
        ->call('initiateParticipationRefund', $first->id)
        ->call('initiateParticipationRefund', $second->id);

    $amounts = PaymentAttempt::query()
        ->where('provider', 'mpesa_daraja_b2c')
        ->orderBy('id')
        ->pluck('amount')
        ->all();

    expect($amounts)->toBe([15, 20]);
});

test('admin initiate asks safaricom and the customer follows the refund result', function () {
    refundB2cConfig();
    Http::preventStrayRequests();
    Http::fake([
        '*/oauth/v1/generate*' => Http::response(['access_token' => 'token-1', 'expires_in' => 3599]),
        '*/mpesa/b2c/v3/paymentrequest' => Http::response([
            'ConversationID' => 'AG_refund_1',
            'OriginatorConversationID' => 'RFDADMIN01',
            'ResponseCode' => '0',
            'ResponseDescription' => 'Accept the service request successfully.',
        ]),
    ]);

    $admin = User::factory()->admin()->create();
    $bid = voidedFeeBid();

    Livewire::actingAs($bid->bidder->user)
        ->test('pages::account')
        ->set('section', 'history')
        ->assertSee('Awaiting Refund')
        ->assertDontSee('Not successful');

    Livewire::actingAs($admin)
        ->test('pages::admin.auction-bids', ['auction' => $bid->auction])
        ->assertSee('Refund to customer')
        ->call('initiateParticipationRefund', $bid->id)
        ->assertSee('This refund is with M-Pesa')
        ->assertDontSee('Refund to customer');

    $attempt = PaymentAttempt::query()->where('provider', 'mpesa_daraja_b2c')->first();

    expect($attempt->amount)->toBe(15)
        ->and($attempt->request_payload['purpose'])->toBe('participation_refund')
        ->and($attempt->request_payload['phone'])->toBe('254708374149')
        ->and($bid->fresh()->status)->toBe('accepted')
        ->and(Activity::query()->where('event', 'participation_refund_requested')->exists())->toBeTrue()
        ->and(Activity::query()->where('event', 'participation_refund_paid')->exists())->toBeFalse()
        ->and(Activity::query()->where('description', 'redemption payout sent')->exists())->toBeFalse();

    Http::assertSent(function ($request): bool {
        $body = $request->data();

        return str_contains($request->url(), 'paymentrequest')
            && ($body['PartyB'] ?? null) === '254708374149'
            && ($body['Amount'] ?? null) === 15
            && ($body['Occassion'] ?? null) === 'Refund';
    });

    Livewire::actingAs($bid->bidder->user)
        ->test('pages::account')
        ->set('section', 'history')
        ->assertSee('Refund is with M-Pesa')
        ->assertDontSee('Refund successful');

    $this->postJson(route('mpesa.b2c.result'), [
        'Result' => [
            'ResultCode' => 0,
            'ResultDesc' => 'The service request is processed successfully.',
            'OriginatorConversationID' => $attempt->reference,
            'ConversationID' => 'AG_refund_1',
            'TransactionID' => 'RFDADMINOK',
        ],
    ])->assertSuccessful();

    expect($attempt->fresh()->status)->toBe('succeeded')
        ->and($bid->fresh()->status)->toBe('accepted')
        ->and(Activity::query()->where('event', 'participation_refund_paid')->exists())->toBeTrue()
        ->and(Activity::query()->where('description', 'seller payout sent')->exists())->toBeFalse()
        ->and(Activity::query()->where('description', 'redemption payout sent')->exists())->toBeFalse();

    Livewire::actingAs($admin)
        ->test('pages::admin.auction-bids', ['auction' => $bid->auction])
        ->assertSee('Refund sent.')
        ->assertSee('RFDADMINOK')
        ->assertDontSee('Refund to customer');

    Livewire::actingAs($bid->bidder->user)
        ->test('pages::account')
        ->set('section', 'history')
        ->assertSee('Refund successful.')
        ->assertDontSee('Refund is with M-Pesa')
        ->assertDontSee('Not successful');
});

test('a finished refund failure can still be completed by a later success', function () {
    refundB2cConfig();
    Http::preventStrayRequests();
    Http::fake([
        '*/oauth/v1/generate*' => Http::response(['access_token' => 'token-1', 'expires_in' => 3599]),
        '*/mpesa/b2c/v3/paymentrequest' => Http::response([
            'ConversationID' => 'AG_refund_2',
            'OriginatorConversationID' => 'RFDFAIL01',
            'ResponseCode' => '0',
            'ResponseDescription' => 'Accept the service request successfully.',
        ]),
    ]);

    $admin = User::factory()->admin()->create();
    $bid = voidedFeeBid();

    Livewire::actingAs($admin)
        ->test('pages::admin.auction-bids', ['auction' => $bid->auction])
        ->call('initiateParticipationRefund', $bid->id);

    $attempt = PaymentAttempt::query()->where('provider', 'mpesa_daraja_b2c')->first();

    $this->postJson(route('mpesa.b2c.result'), [
        'Result' => [
            'ResultCode' => 2040,
            'ResultDesc' => 'Credit Party customer type cannot be supported.',
            'OriginatorConversationID' => $attempt->reference,
            'ConversationID' => 'AG_refund_2',
        ],
    ])->assertSuccessful();

    Livewire::actingAs($admin)
        ->test('pages::admin.auction-bids', ['auction' => $bid->auction])
        ->assertSee('Refund to customer')
        ->assertSee('Credit Party customer type cannot be supported.');

    Livewire::actingAs($bid->bidder->user)
        ->test('pages::account')
        ->set('section', 'history')
        ->assertSee('Refund failed.')
        ->assertDontSee('Credit Party customer type cannot be supported.');

    expect(Activity::query()->where('event', 'participation_refund_failed')->exists())->toBeTrue();

    $this->postJson(route('mpesa.b2c.result'), [
        'Result' => [
            'ResultCode' => 0,
            'ResultDesc' => 'The service request is processed successfully.',
            'OriginatorConversationID' => $attempt->reference,
            'ConversationID' => 'AG_refund_2',
            'TransactionID' => 'RFDLATEOK',
        ],
    ])->assertSuccessful();

    Livewire::actingAs($bid->bidder->user)
        ->test('pages::account')
        ->set('section', 'history')
        ->assertSee('Refund successful.')
        ->assertDontSee('Refund failed.');
});
