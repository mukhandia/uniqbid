<?php

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\AuctionResult;
use App\Models\Bidder;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Services\DarajaB2cService;
use App\Services\RedeemBidService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

function redeemedSale(int $retail = 20, int $salePrice = 7): AuctionResult
{
    $bidder = Bidder::factory()->create([
        'phone' => '254712345678',
        'name' => 'Amina Winner',
    ]);

    $auction = Auction::factory()->create([
        'name' => 'Small redeem',
        'retail_value' => $retail,
        'status' => 'closed',
        'settled_at' => now(),
    ]);

    $bid = AuctionBid::factory()->accepted()->create([
        'auction_id' => $auction->id,
        'bidder_id' => $bidder->id,
        'amount' => $salePrice,
        'reference' => 'BID-PAYOUT'.fake()->unique()->numerify('###'),
    ]);

    return AuctionResult::factory()->create([
        'auction_id' => $auction->id,
        'auction_bid_id' => $bid->id,
        'name' => $auction->name,
        'sale_price' => $salePrice,
        'sale_payment_status' => AuctionResult::PAYMENT_PAID,
        'fulfilment_status' => AuctionResult::FULFILMENT_REDEEMED,
        'redemption_amount' => max(0, $retail - $salePrice),
        'redemption_payout_status' => AuctionResult::REDEMPTION_PAYOUT_PENDING,
        'redemption_requested_at' => now(),
        'redeemed_at' => now(),
    ]);
}

function b2cConfig(): void
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

test('a redeem payout asks safaricom to send the cash difference', function () {
    b2cConfig();
    Http::preventStrayRequests();
    Http::fake([
        '*/oauth/v1/generate*' => Http::response(['access_token' => 'token-1', 'expires_in' => 3599]),
        '*/mpesa/b2c/v3/paymentrequest' => Http::response([
            'ConversationID' => 'AG_redeem_1',
            'OriginatorConversationID' => 'RDMTEST',
            'ResponseCode' => '0',
            'ResponseDescription' => 'Accept the service request successfully.',
        ]),
    ]);

    $result = redeemedSale();
    $attempt = app(RedeemBidService::class)->beginPayout($result);
    app(DarajaB2cService::class)->requestPayout($attempt);

    expect($attempt->fresh()->amount)->toBe(13)
        ->and($attempt->fresh()->checkout_request_id)->toBe('AG_redeem_1')
        ->and($attempt->fresh()->status)->toBe('initiated')
        ->and($result->fresh()->redemption_payout_status)->toBe('pending');

    Http::assertSent(function (Request $request) use ($attempt) {
        if (! str_ends_with($request->url(), '/mpesa/b2c/v3/paymentrequest')) {
            return false;
        }

        return $request['Amount'] === 13
            && $request['PartyA'] === '600000'
            && $request['PartyB'] === '254712345678'
            && $request['CommandID'] === 'BusinessPayment'
            && $request['OriginatorConversationID'] === $attempt->reference
            && $request['ResultURL'] === 'https://example.test/mpesa/b2c/result';
    });
});

test('a successful b2c result marks the redeem payout paid', function () {
    $result = redeemedSale();
    $attempt = app(RedeemBidService::class)->beginPayout($result);

    $this->postJson(route('mpesa.b2c.result'), [
        'Result' => [
            'ResultCode' => 0,
            'ResultDesc' => 'The service request is processed successfully.',
            'OriginatorConversationID' => $attempt->reference,
            'ConversationID' => 'AG_redeem_ok',
            'TransactionID' => 'RDM1234567',
            'ResultParameters' => [
                'ResultParameter' => [
                    ['Key' => 'TransactionReceipt', 'Value' => 'RDM1234567'],
                    ['Key' => 'TransactionAmount', 'Value' => 13],
                ],
            ],
        ],
    ])->assertSuccessful();

    expect($attempt->fresh()->status)->toBe('succeeded')
        ->and($attempt->fresh()->mpesa_receipt)->toBe('RDM1234567')
        ->and($result->fresh()->redemption_payout_status)->toBe('paid')
        ->and(Activity::query()->where('event', 'payout_paid')->where('description', 'redemption payout sent')->exists())->toBeTrue()
        ->and(Activity::query()->where('event', 'payout_failed')->exists())->toBeFalse();
});

test('a rejected b2c result leaves the payout pending so it can be tried again', function () {
    $result = redeemedSale();
    $attempt = app(RedeemBidService::class)->beginPayout($result);

    $this->postJson(route('mpesa.b2c.result'), [
        'Result' => [
            'ResultCode' => 2001,
            'ResultDesc' => 'The initiator information is invalid.',
            'OriginatorConversationID' => $attempt->reference,
            'ConversationID' => 'AG_redeem_bad',
        ],
    ])->assertSuccessful();

    expect($attempt->fresh()->status)->toBe('failed')
        ->and($result->fresh()->redemption_payout_status)->toBe('pending')
        ->and($result->fresh()->fulfilment_status)->toBe('redeemed');

    expect(Activity::query()->where('event', 'payout_failed')->where('description', 'redemption payout not sent')->exists())->toBeTrue()
        ->and(Activity::query()->where('event', 'payout_paid')->exists())->toBeFalse()
        ->and(Activity::query()->where('description', 'successful buy redeemed')->exists())->toBeFalse()
        ->and(Activity::query()->where('description', 'successful-buy payment received')->exists())->toBeFalse();
});

test('an unfinished b2c result keeps the payout open', function () {
    $result = redeemedSale();
    $attempt = app(RedeemBidService::class)->beginPayout($result);

    $this->postJson(route('mpesa.b2c.result'), [
        'Result' => [
            'ResultCode' => 4999,
            'ResultDesc' => 'The transaction is still under processing',
            'OriginatorConversationID' => $attempt->reference,
        ],
    ])->assertSuccessful();

    expect($attempt->fresh()->status)->toBe('initiated')
        ->and($result->fresh()->redemption_payout_status)->toBe('pending');

    expect(Activity::query()->where('event', 'payout_failed')->exists())->toBeFalse()
        ->and(Activity::query()->where('event', 'payout_paid')->exists())->toBeFalse();
});

test('a payout below ten shillings is not sent', function () {
    $result = redeemedSale(retail: 16, salePrice: 10);

    expect(fn () => app(RedeemBidService::class)->beginPayout($result))
        ->toThrow(ValidationException::class);

    expect(PaymentAttempt::query()->count())->toBe(0)
        ->and($result->fresh()->redemption_payout_status)->toBe('pending');
});

test('an account retry opens the unpaid redeem payout', function () {
    $result = redeemedSale();
    $result->load('auctionBid');

    $this->get(route('redeem', ['reference' => $result->auctionBid->reference]))
        ->assertOk()
        ->assertSee('Your redeem request has been sent for review')
        ->assertDontSee('Send KES 13 to M-Pesa');
});

test('a second payout is not created while the first is still open', function () {
    $result = redeemedSale();
    $service = app(RedeemBidService::class);

    $first = $service->beginPayout($result);
    $second = $service->beginPayout($result);

    expect($second->id)->toBe($first->id)
        ->and(PaymentAttempt::query()->count())->toBe(1);
});

test('admin initiate asks safaricom and leaves the payout unpaid until the result', function () {
    b2cConfig();
    Http::preventStrayRequests();
    Http::fake([
        '*/oauth/v1/generate*' => Http::response(['access_token' => 'token-1', 'expires_in' => 3599]),
        '*/mpesa/b2c/v3/paymentrequest' => Http::response([
            'ConversationID' => 'AG_admin_1',
            'OriginatorConversationID' => 'RDMADMIN01',
            'ResponseCode' => '0',
            'ResponseDescription' => 'Accept the service request successfully.',
        ]),
    ]);

    $admin = User::factory()->admin()->create();
    $result = redeemedSale();
    $result->load('auction');

    Livewire::actingAs($admin)
        ->test('pages::admin.auction-bids', ['auction' => $result->auction])
        ->assertSee('Initiate payment')
        ->call('initiateRedeemPayout')
        ->assertSee('This payout is with M-Pesa')
        ->assertSee('checks for Safaricom')
        ->assertDontSee('Initiate payment');

    $attempt = PaymentAttempt::query()->where('provider', 'mpesa_daraja_b2c')->first();

    $this->postJson(route('mpesa.b2c.result'), [
        'Result' => [
            'ResultCode' => 0,
            'ResultDesc' => 'The service request is processed successfully.',
            'OriginatorConversationID' => $attempt->reference,
            'ConversationID' => 'AG_admin_1',
            'TransactionID' => 'RDMADMINOK',
        ],
    ])->assertSuccessful();

    Livewire::actingAs($admin)
        ->test('pages::admin.auction-bids', ['auction' => $result->auction->fresh()])
        ->assertSee('Cash payout sent')
        ->assertDontSee('This payout is with M-Pesa');

    expect($result->fresh()->redemption_payout_status)->toBe('paid')
        ->and($result->fresh()->redemption_requested_at)->toBeNull()
        ->and(Activity::query()->where('event', 'payout_requested')->exists())->toBeTrue()
        ->and(Activity::query()->where('event', 'payout_paid')->exists())->toBeTrue()
        ->and(Activity::query()->where('description', 'successful-buy payment received')->exists())->toBeFalse();
});
