<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\OrderService;
use Livewire\Livewire;

test('checkout places a persisted order, decrements stock, and clears the cart', function () {
    $product = Product::factory()->create([
        'name' => 'Rose-gold bracelet watch',
        'price' => 4950,
        'stock' => 5,
    ]);

    app(CartService::class)->add($product->id, 2);

    Livewire::test('pages::checkout')
        ->set('name', 'Amina Wanjiku')
        ->set('phone', '0712345678')
        ->set('delivery', Order::DELIVERY_COLLECTION)
        ->set('address', '')
        ->call('placeOrder')
        ->assertHasNoErrors()
        ->assertSet('status', 'success')
        ->assertSet('orderRef', fn ($ref) => str_starts_with((string) $ref, 'ORD'));

    expect(app(CartService::class)->count())->toBe(0);

    $order = Order::query()->latest('id')->first();

    expect($order)->not->toBeNull()
        ->and($order->total)->toBe(9900)
        ->and($order->delivery_fee)->toBe(0)
        ->and($order->items)->toHaveCount(1)
        ->and($product->fresh()->stock)->toBe(3);
});

test('checkout adds upcountry delivery fee', function () {
    $product = Product::factory()->create(['price' => 2000, 'stock' => 4]);
    app(CartService::class)->add($product->id, 1);

    Livewire::test('pages::checkout')
        ->set('name', 'James Mwangi')
        ->set('phone', '0798765432')
        ->set('delivery', Order::DELIVERY_UPCOUNTRY)
        ->set('address', 'Kisumu CBD')
        ->call('placeOrder')
        ->assertHasNoErrors()
        ->assertSet('status', 'success');

    $order = Order::query()->latest('id')->first();

    expect($order->delivery_fee)->toBe(OrderService::UPCOUNTRY_DELIVERY_FEE)
        ->and($order->total)->toBe(2000 + OrderService::UPCOUNTRY_DELIVERY_FEE);
});

test('order receipt allows cancel before dispatch and restores stock', function () {
    $product = Product::factory()->create(['stock' => 4, 'price' => 1000]);
    app(CartService::class)->add($product->id, 1);

    $order = app(OrderService::class)->placeFromCart([
        'name' => 'Grace Wanjiru',
        'phone' => '0711223344',
        'delivery_method' => Order::DELIVERY_NAIROBI,
        'delivery_address' => 'Westlands',
        'user' => null,
    ]);

    expect($product->fresh()->stock)->toBe(3);

    Livewire::test('pages::orders.show', ['order' => $order])
        ->call('cancelOrder')
        ->assertHasNoErrors()
        ->assertSee('Cancelled');

    expect($order->fresh()->status)->toBe(Order::STATUS_CANCELLED)
        ->and($product->fresh()->stock)->toBe(4);
});

test('dispatched orders cannot be cancelled', function () {
    $order = Order::factory()->dispatched()->create();
    OrderItem::factory()->create(['order_id' => $order->id]);

    Livewire::test('pages::orders.show', ['order' => $order])
        ->call('cancelOrder');

    expect($order->fresh()->status)->toBe(Order::STATUS_DISPATCHED);
});

test('order tracking finds an order by reference', function () {
    $order = Order::factory()->create(['reference' => 'ORD250821TEST1']);

    Livewire::test('pages::orders.track')
        ->set('reference', 'ord250821test1')
        ->call('track')
        ->assertRedirect(route('orders.show', $order));
});

test('authenticated checkout links the order to the user', function () {
    $user = User::factory()->create([
        'phone' => '254711223344',
        'is_admin' => false,
    ]);
    $product = Product::factory()->create(['stock' => 3, 'price' => 1500]);
    app(CartService::class)->add($product->id, 1);

    $this->actingAs($user);

    Livewire::test('pages::checkout')
        ->set('name', $user->name)
        ->set('phone', '0711223344')
        ->set('delivery', Order::DELIVERY_COLLECTION)
        ->call('placeOrder')
        ->assertSet('status', 'success');

    expect(Order::query()->latest('id')->first()->user_id)->toBe($user->id);
});
