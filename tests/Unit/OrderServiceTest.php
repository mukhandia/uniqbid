<?php

use App\Models\Order;
use App\Models\Product;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('order service refuses empty carts', function () {
    $service = app(OrderService::class);

    expect(fn () => $service->placeFromCart([
        'name' => 'Test User',
        'phone' => '0712345678',
        'delivery_method' => Order::DELIVERY_COLLECTION,
    ]))->toThrow(ValidationException::class);
});

test('order service refuses quantities above stock', function () {
    $product = Product::factory()->create(['stock' => 1, 'price' => 1000]);

    // CartService clamps adds; simulate a stale session line above stock.
    session(['uniqbid.cart' => [['product_id' => $product->id, 'qty' => 2]]]);

    expect(fn () => app(OrderService::class)->placeFromCart([
        'name' => 'Test User',
        'phone' => '0712345678',
        'delivery_method' => Order::DELIVERY_NAIROBI,
        'delivery_address' => 'Nairobi',
    ]))->toThrow(ValidationException::class);
});

test('delivery is not charged at checkout for any method', function () {
    $service = app(OrderService::class);
    $product = Product::factory()->create(['stock' => 3, 'price' => 1000]);

    foreach ([Order::DELIVERY_NAIROBI, Order::DELIVERY_COLLECTION, Order::DELIVERY_UPCOUNTRY] as $method) {
        app(CartService::class)->add($product->id, 1);

        $order = $service->placeFromCart([
            'name' => 'Test User',
            'phone' => '0712345678',
            'delivery_method' => $method,
            'delivery_address' => 'Nairobi',
        ]);

        expect($order->delivery_fee)->toBe(0)
            ->and($order->total)->toBe(1000);
    }
});

test('order service moves a paid order through dispatch and delivery', function () {
    $service = app(OrderService::class);
    $order = Order::factory()->create(['status' => Order::STATUS_PAID]);

    $processing = $service->markProcessing($order);
    expect($processing->status)->toBe(Order::STATUS_PROCESSING);

    $dispatched = $service->markDispatched($processing);
    expect($dispatched->status)->toBe(Order::STATUS_DISPATCHED)
        ->and($dispatched->dispatched_at)->not->toBeNull();

    $delivered = $service->markDelivered($dispatched);
    expect($delivered->status)->toBe(Order::STATUS_DELIVERED)
        ->and($delivered->delivered_at)->not->toBeNull();
});

test('order service refuses delivery before dispatch', function () {
    $order = Order::factory()->create(['status' => Order::STATUS_PAID]);

    expect(fn () => app(OrderService::class)->markDelivered($order))
        ->toThrow(ValidationException::class);
});
