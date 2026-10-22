<?php

use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Services\CartService;
use App\Services\DarajaStkService;
use App\Services\OrderService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function shopCart(int $price = 100, int $stock = 5, int $qty = 2): Product
{
    $product = Product::factory()->create([
        'name' => 'Pencil',
        'price' => $price,
        'stock' => $stock,
        'reserved_stock' => 0,
    ]);

    app(CartService::class)->add($product->id, $qty);

    return $product;
}

function placeAwaitingShopOrder(): array
{
    $product = shopCart();

    $order = app(OrderService::class)->placeFromCart([
        'name' => 'Amina Wanjiku',
        'phone' => '0712345678',
        'delivery_method' => Order::DELIVERY_COLLECTION,
        'await_mpesa' => true,
    ]);

    $attempt = $order->paymentAttempts()->first();

    return compact('product', 'order', 'attempt');
}

test('checkout asks for mpesa instead of a demo payment', function () {
    shopCart(price: 100, stock: 2, qty: 1);

    $this->get(route('checkout'))
        ->assertSuccessful()
        ->assertSee('Pay with M-Pesa')
        ->assertDontSee('Milestone 2.6')
        ->assertDontSee('demo M-Pesa');
});

test('an mpesa checkout holds stock until safaricom accepts the prompt', function () {
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
            'MerchantRequestID' => 'm-shop',
            'CheckoutRequestID' => 'ws_CO_shop',
            'ResponseCode' => '0',
            'ResponseDescription' => 'Success. Request accepted for processing',
        ]),
    ]);

    ['product' => $product, 'order' => $order, 'attempt' => $attempt] = placeAwaitingShopOrder();

    expect($order->status)->toBe(Order::STATUS_AWAITING_PAYMENT)
        ->and($order->paid_at)->toBeNull()
        ->and($order->total)->toBe(200)
        ->and($product->fresh()->stock)->toBe(5)
        ->and($product->fresh()->reserved_stock)->toBe(2)
        ->and(app(CartService::class)->count())->toBe(2)
        ->and($attempt)->not->toBeNull()
        ->and($attempt->auction_bid_id)->toBeNull()
        ->and($attempt->amount)->toBe(200)
        ->and($attempt->request_payload['purpose'])->toBe('shop_checkout')
        ->and($attempt->request_payload['phone'])->toBe('254712345678');

    app(DarajaStkService::class)->requestPayment($attempt);

    expect($attempt->fresh()->checkout_request_id)->toBe('ws_CO_shop')
        ->and($order->fresh()->status)->toBe(Order::STATUS_AWAITING_PAYMENT);

    Http::assertSent(function (Request $request) {
        if (! str_ends_with($request->url(), '/mpesa/stkpush/v1/processrequest')) {
            return false;
        }

        return $request['Amount'] === 200
            && $request['PhoneNumber'] === '254712345678'
            && $request['TransactionDesc'] === 'Shop order';
    });
});

test('a successful shop callback marks the order paid and takes the reserved stock', function () {
    ['product' => $product, 'order' => $order, 'attempt' => $attempt] = placeAwaitingShopOrder();
    $attempt->update(['checkout_request_id' => 'ws_CO_shop_ok']);

    $this->postJson(route('mpesa.stk.callback'), [
        'Body' => [
            'stkCallback' => [
                'CheckoutRequestID' => 'ws_CO_shop_ok',
                'ResultCode' => 0,
                'ResultDesc' => 'The service request is processed successfully.',
                'CallbackMetadata' => [
                    'Item' => [
                        ['Name' => 'Amount', 'Value' => 200],
                        ['Name' => 'MpesaReceiptNumber', 'Value' => 'SHOP123456'],
                    ],
                ],
            ],
        ],
    ])->assertSuccessful();

    expect($attempt->fresh()->status)->toBe('succeeded')
        ->and($attempt->fresh()->mpesa_receipt)->toBe('SHOP123456')
        ->and($order->fresh()->status)->toBe(Order::STATUS_PAID)
        ->and($order->fresh()->payment_reference)->toBe('SHOP123456')
        ->and($product->fresh()->stock)->toBe(3)
        ->and($product->fresh()->reserved_stock)->toBe(0);
});

test('a cancelled shop callback releases the reserved stock and leaves the sale unpaid', function () {
    ['product' => $product, 'order' => $order, 'attempt' => $attempt] = placeAwaitingShopOrder();
    $attempt->update(['checkout_request_id' => 'ws_CO_shop_cancel']);

    $this->postJson(route('mpesa.stk.callback'), [
        'Body' => [
            'stkCallback' => [
                'CheckoutRequestID' => 'ws_CO_shop_cancel',
                'ResultCode' => 1032,
                'ResultDesc' => 'Request cancelled by user',
            ],
        ],
    ])->assertSuccessful();

    expect($attempt->fresh()->status)->toBe('failed')
        ->and($order->fresh()->status)->toBe(Order::STATUS_CANCELLED)
        ->and($order->fresh()->paid_at)->toBeNull()
        ->and($product->fresh()->stock)->toBe(5)
        ->and($product->fresh()->reserved_stock)->toBe(0)
        ->and(PaymentAttempt::query()->whereNotNull('auction_bid_id')->count())->toBe(0);
});

test('an unfinished shop callback keeps the order and the stock hold', function () {
    ['product' => $product, 'order' => $order, 'attempt' => $attempt] = placeAwaitingShopOrder();
    $attempt->update(['checkout_request_id' => 'ws_CO_shop_open']);

    $this->postJson(route('mpesa.stk.callback'), [
        'Body' => [
            'stkCallback' => [
                'CheckoutRequestID' => 'ws_CO_shop_open',
                'ResultCode' => 4999,
                'ResultDesc' => 'The transaction is still under processing',
            ],
        ],
    ])->assertSuccessful();

    expect($attempt->fresh()->status)->toBe('initiated')
        ->and($order->fresh()->status)->toBe(Order::STATUS_AWAITING_PAYMENT)
        ->and($product->fresh()->stock)->toBe(5)
        ->and($product->fresh()->reserved_stock)->toBe(2);
});

test('a late shop success after a failed prompt still completes the order', function () {
    ['product' => $product, 'order' => $order, 'attempt' => $attempt] = placeAwaitingShopOrder();
    $attempt->update(['checkout_request_id' => 'ws_CO_shop_late']);

    $this->postJson(route('mpesa.stk.callback'), [
        'Body' => [
            'stkCallback' => [
                'CheckoutRequestID' => 'ws_CO_shop_late',
                'ResultCode' => 1032,
                'ResultDesc' => 'Request cancelled by user',
            ],
        ],
    ])->assertSuccessful();

    $this->postJson(route('mpesa.stk.callback'), [
        'Body' => [
            'stkCallback' => [
                'CheckoutRequestID' => 'ws_CO_shop_late',
                'ResultCode' => 0,
                'ResultDesc' => 'The service request is processed successfully.',
                'CallbackMetadata' => [
                    'Item' => [
                        ['Name' => 'MpesaReceiptNumber', 'Value' => 'SHOPLATE01'],
                    ],
                ],
            ],
        ],
    ])->assertSuccessful();

    expect($attempt->fresh()->status)->toBe('succeeded')
        ->and($order->fresh()->status)->toBe(Order::STATUS_PAID)
        ->and($product->fresh()->stock)->toBe(3)
        ->and($product->fresh()->reserved_stock)->toBe(0);
});
