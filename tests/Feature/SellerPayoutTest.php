<?php

use App\Models\OrderItem;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\User;
use App\Services\GuestSellerPayoutService;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

function sellerPayoutItem(int $amount = 18, string $phone = '254711000111'): OrderItem
{
    $seller = User::factory()->create([
        'is_admin' => false,
        'phone' => $phone,
        'name' => 'Guest Seller',
    ]);

    return OrderItem::factory()->create([
        'seller_user_id' => $seller->id,
        'product_name' => 'Guest Pencil',
        'seller_payout_amount' => $amount,
        'seller_payout_status' => OrderItem::PAYOUT_PENDING,
        'commission_amount' => 2,
    ]);
}

function sellerB2cConfig(): void
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

test('staff cannot mark a seller payout paid when mpesa is not connected', function () {
    $item = sellerPayoutItem();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::admin.guest-listings')
        ->assertSee('Initiate payment')
        ->call('initiateSellerPayout', $item->id)
        ->assertSee('Initiate payment');

    expect($item->fresh()->seller_payout_status)->toBe(OrderItem::PAYOUT_PENDING)
        ->and(PaymentAttempt::query()->count())->toBe(0);
});

test('a seller payout below kes 10 is refused before mpesa is called', function () {
    sellerB2cConfig();
    $item = sellerPayoutItem(9);

    expect(fn () => app(GuestSellerPayoutService::class)->beginPayout($item))
        ->toThrow(ValidationException::class);

    expect(PaymentAttempt::query()->count())->toBe(0)
        ->and($item->fresh()->seller_payout_status)->toBe(OrderItem::PAYOUT_PENDING);
});

test('admin initiate asks safaricom and leaves the seller payout unpaid until the result', function () {
    sellerB2cConfig();
    Http::preventStrayRequests();
    Http::fake([
        '*/oauth/v1/generate*' => Http::response(['access_token' => 'token-1', 'expires_in' => 3599]),
        '*/mpesa/b2c/v3/paymentrequest' => Http::response([
            'ConversationID' => 'AG_seller_1',
            'OriginatorConversationID' => 'SELADMIN01',
            'ResponseCode' => '0',
            'ResponseDescription' => 'Accept the service request successfully.',
        ]),
    ]);

    $admin = User::factory()->admin()->create();
    $item = sellerPayoutItem(18, '254708374149');

    Livewire::actingAs($admin)
        ->test('pages::admin.guest-listings')
        ->assertSee('Initiate payment')
        ->call('initiateSellerPayout', $item->id)
        ->assertSee('This payout is with M-Pesa')
        ->assertDontSee('Initiate payment');

    $attempt = PaymentAttempt::query()->where('provider', 'mpesa_daraja_b2c')->first();

    expect($item->fresh()->seller_payout_status)->toBe(OrderItem::PAYOUT_PENDING)
        ->and($attempt->amount)->toBe(18)
        ->and($attempt->request_payload['purpose'])->toBe('seller_payout')
        ->and($attempt->request_payload['phone'])->toBe('254708374149')
        ->and(Activity::query()->where('event', 'seller_payout_requested')->exists())->toBeTrue()
        ->and(Activity::query()->where('event', 'seller_payout_paid')->exists())->toBeFalse();

    Http::assertSent(function ($request): bool {
        $body = $request->data();

        return str_contains($request->url(), 'paymentrequest')
            && ($body['PartyB'] ?? null) === '254708374149'
            && ($body['Amount'] ?? null) === 18
            && ($body['Occassion'] ?? null) === 'Seller';
    });

    $this->postJson(route('mpesa.b2c.result'), [
        'Result' => [
            'ResultCode' => 0,
            'ResultDesc' => 'The service request is processed successfully.',
            'OriginatorConversationID' => $attempt->reference,
            'ConversationID' => 'AG_seller_1',
            'TransactionID' => 'SELADMINOK',
        ],
    ])->assertSuccessful();

    expect($item->fresh()->seller_payout_status)->toBe(OrderItem::PAYOUT_PAID)
        ->and(Activity::query()->where('event', 'seller_payout_paid')->exists())->toBeTrue()
        ->and(Activity::query()->where('description', 'redemption payout sent')->exists())->toBeFalse();

    Livewire::actingAs($admin)
        ->test('pages::admin.guest-listings')
        ->assertSee('Approved seller payouts')
        ->assertSee('SELADMINOK')
        ->assertDontSee('Initiate payment');

    $item->product->forceFill([
        'seller_user_id' => $item->seller_user_id,
        'approval_status' => Product::APPROVAL_APPROVED,
        'channel' => Product::CHANNEL_SHOP,
        'name' => 'Guest Pencil',
    ])->save();

    Livewire::actingAs($item->seller)
        ->test('pages::account')
        ->set('section', 'listings')
        ->assertSee('was sent to your M-Pesa')
        ->assertDontSee('Payout pending');
});

test('a finished seller payout failure stays unpaid and can be sent again', function () {
    sellerB2cConfig();
    Http::preventStrayRequests();
    Http::fake([
        '*/oauth/v1/generate*' => Http::response(['access_token' => 'token-1', 'expires_in' => 3599]),
        '*/mpesa/b2c/v3/paymentrequest' => Http::response([
            'ConversationID' => 'AG_seller_2',
            'OriginatorConversationID' => 'SELFAIL01',
            'ResponseCode' => '0',
            'ResponseDescription' => 'Accept the service request successfully.',
        ]),
    ]);

    $admin = User::factory()->admin()->create();
    $item = sellerPayoutItem();

    Livewire::actingAs($admin)
        ->test('pages::admin.guest-listings')
        ->call('initiateSellerPayout', $item->id);

    $attempt = PaymentAttempt::query()->first();

    $this->postJson(route('mpesa.b2c.result'), [
        'Result' => [
            'ResultCode' => 2040,
            'ResultDesc' => 'Credit Party customer type cannot be supported.',
            'OriginatorConversationID' => $attempt->reference,
            'ConversationID' => 'AG_seller_2',
        ],
    ])->assertSuccessful();

    Livewire::actingAs($admin)
        ->test('pages::admin.guest-listings')
        ->assertSee('Initiate payment')
        ->assertSee('Credit Party customer type cannot be supported.');

    expect($item->fresh()->seller_payout_status)->toBe(OrderItem::PAYOUT_PENDING)
        ->and($attempt->fresh()->status)->toBe('failed')
        ->and(Activity::query()->where('event', 'seller_payout_failed')->exists())->toBeTrue();
});
