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
    app(CartService::class)->add($product->id, 2);

    expect(fn () => app(OrderService::class)->placeFromCart([
        'name' => 'Test User',
        'phone' => '0712345678',
        'delivery_method' => Order::DELIVERY_NAIROBI,
        'delivery_address' => 'Nairobi',
    ]))->toThrow(ValidationException::class);
});

test('delivery fee is free for nairobi and collection', function () {
    $service = app(OrderService::class);

    expect($service->deliveryFee(Order::DELIVERY_NAIROBI))->toBe(0)
        ->and($service->deliveryFee(Order::DELIVERY_COLLECTION))->toBe(0)
        ->and($service->deliveryFee(Order::DELIVERY_UPCOUNTRY))->toBe(350);
});
