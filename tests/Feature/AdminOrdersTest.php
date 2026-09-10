<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('guests cannot access admin orders', function () {
    $this->get(route('admin.orders'))->assertRedirect(route('login'));
});

test('non admins cannot access admin orders', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.orders'))
        ->assertForbidden();
});

test('admin can list shop orders that need packing', function () {
    $paid = Order::factory()->create([
        'reference' => 'ORDPACKNOW1',
        'customer_name' => 'Amina Wanjiku',
        'status' => Order::STATUS_PAID,
    ]);
    $delivered = Order::factory()->delivered()->create([
        'reference' => 'ORDDONEALRDY',
        'customer_name' => 'Already Done',
    ]);

    $this->actingAs($this->admin);

    Livewire::test('pages::admin.orders')
        ->assertSee('ORDPACKNOW1')
        ->assertSee('Amina Wanjiku')
        ->assertDontSee('ORDDONEALRDY');

    Livewire::test('pages::admin.orders')
        ->set('status', 'all')
        ->assertSee('ORDPACKNOW1')
        ->assertSee('ORDDONEALRDY');

    $this->get(route('admin.orders.show', $paid))
        ->assertOk()
        ->assertSee('ORDPACKNOW1')
        ->assertSee('Amina Wanjiku');
});

test('admin can move a paid order through fulfilment', function () {
    $order = Order::factory()->create([
        'reference' => 'ORDFULFILL1',
        'delivery_method' => Order::DELIVERY_NAIROBI,
        'status' => Order::STATUS_PAID,
    ]);
    OrderItem::factory()->create(['order_id' => $order->id]);

    $this->actingAs($this->admin);

    Livewire::test('pages::admin.order', ['order' => $order])
        ->call('markProcessing')
        ->assertHasNoErrors();

    expect($order->fresh()->status)->toBe(Order::STATUS_PROCESSING);

    Livewire::test('pages::admin.order', ['order' => $order->fresh()])
        ->call('markDispatched')
        ->assertHasNoErrors()
        ->assertSee('Mark delivered');

    expect($order->fresh()->status)->toBe(Order::STATUS_DISPATCHED)
        ->and($order->fresh()->dispatched_at)->not->toBeNull();

    Livewire::test('pages::admin.order', ['order' => $order->fresh()])
        ->call('markDelivered')
        ->assertHasNoErrors();

    expect($order->fresh()->status)->toBe(Order::STATUS_DELIVERED)
        ->and($order->fresh()->delivered_at)->not->toBeNull()
        ->and($order->fresh()->activeShipment()?->status)->toBe(\App\Models\Shipment::STATUS_DELIVERED);
});

test('admin can cancel an order before dispatch and restore stock', function () {
    $product = Product::factory()->create(['stock' => 4, 'price' => 1000]);
    $order = Order::factory()->create([
        'reference' => 'ORDCANCELADM',
        'status' => Order::STATUS_PAID,
    ]);
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 1000,
        'line_total' => 1000,
    ]);

    $this->actingAs($this->admin);

    Livewire::test('pages::admin.order', ['order' => $order])
        ->call('cancelOrder')
        ->assertHasNoErrors();

    expect($order->fresh()->status)->toBe(Order::STATUS_CANCELLED)
        ->and($order->fresh()->cancel_reason)->toBe('Cancelled by UniqBid')
        ->and($product->fresh()->stock)->toBe(5);
});

test('admin cannot mark a cancelled order dispatched', function () {
    $order = Order::factory()->cancelled()->create();
    OrderItem::factory()->create(['order_id' => $order->id]);

    $this->actingAs($this->admin);

    Livewire::test('pages::admin.order', ['order' => $order])
        ->call('markDispatched');

    expect($order->fresh()->status)->toBe(Order::STATUS_CANCELLED);
});

test('admins can filter orders by search delivery method and period', function () {
    $nairobi = Order::factory()->create([
        'reference' => 'ORDNAIROBI01',
        'customer_name' => 'Faith Wambui',
        'payment_reference' => 'MPESANAIR001',
        'status' => Order::STATUS_PAID,
        'delivery_method' => Order::DELIVERY_NAIROBI,
        'created_at' => now(),
    ]);
    $collection = Order::factory()->create([
        'reference' => 'ORDCOLLECT02',
        'customer_name' => 'Peter Otieno',
        'status' => Order::STATUS_PAID,
        'delivery_method' => Order::DELIVERY_COLLECTION,
        'created_at' => now(),
    ]);
    $oldDelivered = Order::factory()->delivered()->create([
        'reference' => 'ORDOLDDELIV3',
        'customer_name' => 'Old Delivered Buyer',
        'delivery_method' => Order::DELIVERY_NAIROBI,
        'created_at' => now()->subDays(40),
    ]);

    $this->actingAs($this->admin);

    Livewire::test('pages::admin.orders')
        ->assertSee('ORDNAIROBI01')
        ->assertSee('ORDCOLLECT02')
        ->assertDontSee('ORDOLDDELIV3')
        ->set('search', 'Faith Wambui')
        ->assertSee('ORDNAIROBI01')
        ->assertDontSee('ORDCOLLECT02')
        ->call('clearFilters')
        ->set('search', 'MPESANAIR001')
        ->assertSee('ORDNAIROBI01')
        ->assertDontSee('ORDCOLLECT02')
        ->call('clearFilters')
        ->set('delivery', Order::DELIVERY_COLLECTION)
        ->assertSee('ORDCOLLECT02')
        ->assertDontSee('ORDNAIROBI01')
        ->call('clearFilters')
        ->set('status', 'all')
        ->assertSee('ORDNAIROBI01')
        ->assertSee('ORDOLDDELIV3')
        ->set('period', 'today')
        ->assertSee('ORDNAIROBI01')
        ->assertDontSee('ORDOLDDELIV3')
        ->call('clearFilters')
        ->assertSet('status', 'attention')
        ->assertDontSee('ORDOLDDELIV3');

    $this->get(route('admin.orders', ['status' => 'all', 'delivery' => Order::DELIVERY_NAIROBI]))
        ->assertSuccessful()
        ->assertSee($nairobi->reference)
        ->assertSee($oldDelivered->reference)
        ->assertDontSee($collection->reference);
});
