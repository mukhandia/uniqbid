<?php

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\AuctionResult;
use App\Models\Bidder;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Services\DarajaStkService;
use App\Services\RedeemBidService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

function winningSale(): array
{
    $user = User::factory()->create([
        'phone' => '254712345678',
        'phone_verified_at' => now(),
        'name' => 'Amina Winner',
    ]);

    $bidder = Bidder::factory()->create([
        'user_id' => $user->id,
        'phone' => $user->phone,
        'name' => $user->name,
    ]);

    $auction = Auction::factory()->create([
        'name' => 'Football Jersey',
        'status' => 'closed',
        'settled_at' => now(),
        'bids_count' => 3,
    ]);

    $bid = AuctionBid::factory()->accepted()->create([
        'auction_id' => $auction->id,
        'bidder_id' => $bidder->id,
        'amount' => 20,
        'reference' => 'BID-WINNER001',
    ]);

    $result = AuctionResult::factory()->create([
        'auction_id' => $auction->id,
        'auction_bid_id' => $bid->id,
        'name' => $auction->name,
        'sale_price' => 20,
        'sale_payment_status' => AuctionResult::PAYMENT_AWAITING,
        'fulfilment_status' => AuctionResult::FULFILMENT_PENDING,
    ]);

    return compact('user', 'bidder', 'auction', 'bid', 'result');
}

test('the winner sees a mpesa button for the unpaid winning amount', function () {
    ['user' => $user] = winningSale();

    $this->actingAs($user)
        ->get(route('account'))
        ->assertOk()
        ->assertSee('Pay a winning amount')
        ->assertSee('Pay with M-Pesa')
        ->assertSee('KES 20')
        ->assertSee('0712345678');
});

test('a signed-out guest account does not ask for a winning amount', function () {
    $this->get(route('account'))
        ->assertOk()
        ->assertSee('Sign in with mobile')
        ->assertDontSee('Pay a winning amount');
});

test('paying the winning amount asks safaricom for the sale price', function () {
    config([
        'mpesa.base_url' => 'https://sandbox.safaricom.co.ke',
        'mpesa.consumer_key' => 'key',
        'mpesa.consumer_secret' => 'secret',
        'mpesa.passkey' => 'passkey',
        'mpesa.shortcode' => '174379',
        'mpesa.callback_url' => 'https://example.test/mpesa/stk/callback',
        'mpesa.timeout' => 20,
    ]);

    Http::preventStrayRequests();
    Http::fake([
        '*/oauth/v1/generate*' => Http::response(['access_token' => 'token-1', 'expires_in' => 3599]),
        '*/mpesa/stkpush/v1/processrequest' => Http::response([
            'MerchantRequestID' => 'm-sale',
            'CheckoutRequestID' => 'ws_CO_sale',
            'ResponseCode' => '0',
            'ResponseDescription' => 'Success. Request accepted for processing',
        ]),
    ]);

    ['result' => $result, 'bid' => $bid, 'auction' => $auction] = winningSale();

    $attempt = app(RedeemBidService::class)->initiateSalePayment($result, '0712345678');
    app(DarajaStkService::class)->requestPayment($attempt);

    expect($attempt->fresh()->amount)->toBe(20)
        ->and($attempt->fresh()->checkout_request_id)->toBe('ws_CO_sale')
        ->and($bid->fresh()->status)->toBe('accepted')
        ->and($auction->fresh()->bids_count)->toBe(3);

    Http::assertSent(function (Request $request) {
        if (! str_ends_with($request->url(), '/mpesa/stkpush/v1/processrequest')) {
            return false;
        }

        return $request['Amount'] === 20
            && $request['PhoneNumber'] === '254712345678'
            && $request['TransactionDesc'] === 'Winning buy';
    });
});

test('a successful callback records the winning amount without changing the bid', function () {
    ['result' => $result, 'bid' => $bid, 'auction' => $auction] = winningSale();

    $attempt = app(RedeemBidService::class)->initiateSalePayment($result, '254712345678');
    $attempt->update(['checkout_request_id' => 'ws_CO_sale']);

    $this->postJson(route('mpesa.stk.callback'), [
        'Body' => [
            'stkCallback' => [
                'CheckoutRequestID' => 'ws_CO_sale',
                'ResultCode' => 0,
                'ResultDesc' => 'The service request is processed successfully.',
                'CallbackMetadata' => [
                    'Item' => [
                        ['Name' => 'Amount', 'Value' => 20],
                        ['Name' => 'MpesaReceiptNumber', 'Value' => 'SALE123456'],
                    ],
                ],
            ],
        ],
    ])->assertSuccessful();

    expect($attempt->fresh()->status)->toBe('succeeded')
        ->and($attempt->fresh()->mpesa_receipt)->toBe('SALE123456')
        ->and($result->fresh()->sale_payment_status)->toBe('paid')
        ->and($bid->fresh()->status)->toBe('accepted')
        ->and($auction->fresh()->bids_count)->toBe(3);
});

test('a cancelled winning-amount callback leaves the sale unpaid and the bid accepted', function () {
    ['result' => $result, 'bid' => $bid] = winningSale();

    $attempt = app(RedeemBidService::class)->initiateSalePayment($result, '254712345678');
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
        ->and($result->fresh()->sale_payment_status)->toBe('awaiting_payment')
        ->and($bid->fresh()->status)->toBe('accepted');
});

test('an unfinished winning-amount callback stays open', function () {
    ['result' => $result] = winningSale();

    $attempt = app(RedeemBidService::class)->initiateSalePayment($result, '254712345678');
    $attempt->update(['checkout_request_id' => 'ws_CO_open']);

    $this->postJson(route('mpesa.stk.callback'), [
        'Body' => [
            'stkCallback' => [
                'CheckoutRequestID' => 'ws_CO_open',
                'ResultCode' => 4999,
                'ResultDesc' => 'The transaction is still under processing',
            ],
        ],
    ])->assertSuccessful();

    expect($attempt->fresh()->status)->toBe('initiated')
        ->and($result->fresh()->sale_payment_status)->toBe('awaiting_payment');
});

test('another customer cannot pay someone elses winning amount', function () {
    ['result' => $result] = winningSale();
    $other = User::factory()->create(['phone' => '254700000001', 'phone_verified_at' => now()]);
    Bidder::factory()->create(['user_id' => $other->id, 'phone' => $other->phone]);

    Livewire::actingAs($other)
        ->test('pages::account')
        ->call('payWinningAmount', $result->id)
        ->assertForbidden();

    expect(PaymentAttempt::query()->count())->toBe(0);
});

test('the account page starts one winning-amount payment', function () {
    ['user' => $user, 'result' => $result] = winningSale();

    Livewire::actingAs($user)
        ->test('pages::account')
        ->call('payWinningAmount', $result->id)
        ->assertSet('salePaymentState', 'pending')
        ->call('payWinningAmount', $result->id)
        ->assertHasNoErrors();

    expect(PaymentAttempt::query()->count())->toBe(1)
        ->and(PaymentAttempt::query()->first()->amount)->toBe(20)
        ->and(PaymentAttempt::query()->first()->request_payload['purpose'])->toBe('successful_buy');
});

test('an unpaid redeem can be paid as the winning amount instead', function () {
    ['result' => $result] = winningSale();
    $result->auction->update(['retail_value' => 40]);

    $redeemed = app(RedeemBidService::class)->redeem($result->fresh());

    expect(Activity::query()->where('description', 'redemption recorded, cash payout not sent')->exists())->toBeTrue()
        ->and(Activity::query()->where('description', 'successful buy redeemed')->exists())->toBeFalse()
        ->and(Activity::query()->where('event', 'payout_paid')->exists())->toBeFalse();

    $attempt = app(RedeemBidService::class)->initiateSalePayment($redeemed, '0712345678');

    expect(Activity::query()->where('description', 'successful-buy payment received')->exists())->toBeFalse();

    $paid = app(RedeemBidService::class)->confirmSalePayment($attempt);

    expect($paid->sale_payment_status)->toBe(AuctionResult::PAYMENT_PAID)
        ->and($paid->fulfilment_status)->toBe(AuctionResult::FULFILMENT_PENDING)
        ->and($paid->redemption_payout_status)->toBeNull()
        ->and(Activity::query()->where('description', 'successful-buy payment received')->count())->toBe(1);
});

test('a winning amount cannot start while the cash payout is still with mpesa', function () {
    ['result' => $result] = winningSale();
    $result->auction->update(['retail_value' => 40]);
    $redeemed = app(RedeemBidService::class)->redeem($result->fresh());
    $redeemed = app(RedeemBidService::class)->submitForReview($redeemed);
    app(RedeemBidService::class)->beginPayout($redeemed);

    expect(fn () => app(RedeemBidService::class)->initiateSalePayment($redeemed, '0712345678'))
        ->toThrow(ValidationException::class);

    expect(PaymentAttempt::query()->where('provider', 'mpesa_daraja_stk_push')->count())->toBe(0);
});
