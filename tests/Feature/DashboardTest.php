<?php

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\AuctionResult;
use App\Models\CustomerControl;
use App\Models\Order;
use App\Models\Product;
use App\Models\SupportTicket;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('customers are redirected from the dashboard to my account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('account'));
});

test('unverified customers are redirected from the dashboard to my account', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('account'));
});

test('admins see operational snapshot queues and recent activity', function () {
    $admin = User::factory()->admin()->create();

    $liveSale = Auction::factory()->create([
        'name' => 'Live Weekend Gadget Sale',
        'status' => 'live',
        'ends_at' => now()->addHours(2),
        'is_featured' => true,
    ]);
    $unsettledSale = Auction::factory()->create([
        'name' => 'Ended Camera Sale',
        'status' => 'live',
        'ends_at' => now()->subHour(),
        'settled_at' => null,
    ]);
    AuctionBid::factory()->accepted()->create([
        'auction_id' => $liveSale->id,
        'paid_at' => now(),
    ]);

    $settledAuction = Auction::factory()->create([
        'name' => 'Paid Collection Sale',
        'status' => 'closed',
        'ends_at' => now()->subDay(),
        'settled_at' => now()->subDay(),
    ]);
    AuctionResult::factory()->create([
        'auction_id' => $settledAuction->id,
        'name' => 'Paid Collection Sale',
        'sale_payment_status' => 'awaiting_payment',
        'fulfilment_status' => 'pending',
        'fulfilment_due_at' => now()->subDay(),
        'buyer_display' => 'Amina W.',
    ]);

    $order = Order::factory()->create([
        'reference' => 'ORDDASHPACK1',
        'customer_name' => 'James Mwangi',
        'status' => Order::STATUS_PAID,
        'total' => 4200,
        'paid_at' => now(),
    ]);
    SupportTicket::factory()->create([
        'name' => 'Need help today',
        'subject' => 'Where is my order?',
    ]);
    $pendingCustomer = User::factory()->create([
        'name' => 'Grace Njeri',
        'phone' => '254799988877',
    ]);
    CustomerControl::query()->create([
        'user_id' => $pendingCustomer->id,
        'verification_status' => 'requested',
        'verification_requested_at' => now(),
    ]);
    Product::factory()->create([
        'name' => 'Plenty in shop',
        'stock' => 2,
        'reserved_stock' => 0,
    ]);
    Product::factory()->create([
        'name' => 'Empty cardholder',
        'stock' => 0,
        'reserved_stock' => 0,
    ]);
    Product::factory()->create([
        'name' => 'Reserved gold watch',
        'stock' => 100,
        'reserved_stock' => 4,
    ]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Dashboard')
        ->assertSee('Live sales')
        ->assertSee('open right now')
        ->assertSee('Sales to settle')
        ->assertSee('countdown has ended')
        ->assertSee('Participation fees today')
        ->assertSee('Fees paid today to place a unique bid')
        ->assertSee('Shop orders to pack')
        ->assertSee('Live Weekend Gadget Sale')
        ->assertSee('Ended Camera Sale')
        ->assertSee('Sales to settle')
        ->assertSee('Paid Collection Sale')
        ->assertSee('Successful buys awaiting payment')
        ->assertSee('ORDDASHPACK1')
        ->assertSee('Paid: preparing')
        ->assertSee('fee paid')
        ->assertSee('awaiting payment')
        ->assertSee('James Mwangi')
        ->assertSee('Where is my order?')
        ->assertSee('Grace Njeri')
        ->assertSee('Out of stock')
        ->assertSee('Empty cardholder')
        ->assertSee('Reserved for live sales')
        ->assertSee('Reserved gold watch')
        ->assertSee('4 reserved · 100 in stock')
        ->assertDontSee('Plenty in shop')
        ->assertSee('Orders')
        ->assertDontSee('placeholder-pattern', false);

    Livewire::actingAs($admin)
        ->test('pages::admin.dashboard')
        ->assertSee($liveSale->name)
        ->assertSee($unsettledSale->name)
        ->assertSee($order->reference);
});
