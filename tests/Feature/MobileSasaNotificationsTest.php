<?php

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\Bidder;
use App\Models\BidderMessage;
use App\Models\Order;
use App\Models\User;
use App\Services\NotificationPlaceholderService;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

function notifySmsConfig(): void
{
    config([
        'mobilesasa.base_url' => 'https://api.mobilesasa.com',
        'mobilesasa.token' => 'mbs_test_token',
        'mobilesasa.sender_id' => 'MOBILESASA',
        'mobilesasa.timeout' => 20,
    ]);
}

test('participation fee notices are sent when mobilesasa is connected', function () {
    notifySmsConfig();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.mobilesasa.com/v2/send/message' => Http::response([
            'status' => true,
            'responseCode' => '0200',
            'message' => 'Accepted',
            'messageId' => 'sms-bid-1',
            'cost' => 1,
        ]),
    ]);

    $bid = AuctionBid::factory()->accepted()->create([
        'reference' => 'BID-SMSFEE01',
        'amount' => 40,
        'bidder_id' => Bidder::factory()->create(['phone' => '254768003470'])->id,
        'auction_id' => Auction::factory()->create(['name' => 'Pencil Test'])->id,
    ]);

    app(NotificationPlaceholderService::class)->paymentSucceeded($bid);
    app(NotificationPlaceholderService::class)->paymentFailed($bid);

    Http::assertSentCount(2);
    Http::assertSent(fn ($request): bool => str_contains((string) $request->data()['message'], 'Payment received')
        && ($request->data()['phone'] ?? null) === '254768003470');
    Http::assertSent(fn ($request): bool => str_contains((string) $request->data()['message'], 'was not completed'));
});

test('shop order notices are sent when mobilesasa is connected', function () {
    notifySmsConfig();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.mobilesasa.com/v2/send/message' => Http::response([
            'status' => true,
            'responseCode' => '0200',
            'message' => 'Accepted',
            'messageId' => 'sms-order-1',
            'cost' => 1,
        ]),
    ]);

    $order = Order::factory()->create([
        'customer_phone' => '254768003470',
        'reference' => 'ORDSMS001',
        'total' => 15,
        'delivery_method' => Order::DELIVERY_COLLECTION,
    ]);

    $notifications = app(NotificationPlaceholderService::class);
    $notifications->notifyOrderPlaced($order);
    $notifications->notifyOrderCancelled($order);
    $notifications->notifyOrderDispatched($order);
    $notifications->notifyOrderDelivered($order);

    Http::assertSentCount(4);
    Http::assertSent(fn ($request): bool => str_contains((string) $request->data()['message'], 'ORDSMS001 confirmed'));
    Http::assertSent(fn ($request): bool => str_contains((string) $request->data()['message'], 'cancelled before dispatch'));
    Http::assertSent(fn ($request): bool => str_contains((string) $request->data()['message'], 'ready for collection'));
    Http::assertSent(fn ($request): bool => str_contains((string) $request->data()['message'], 'was collected'));
});

test('customer notices only log when mobilesasa is not connected', function () {
    Http::fake();

    $bid = AuctionBid::factory()->accepted()->create([
        'bidder_id' => Bidder::factory()->create(['phone' => '254768003470'])->id,
    ]);

    app(NotificationPlaceholderService::class)->paymentSucceeded($bid);

    Http::assertNothingSent();
});

test('admin bidder messages are sent when mobilesasa is connected', function () {
    notifySmsConfig();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.mobilesasa.com/v2/send/message' => Http::response([
            'status' => true,
            'responseCode' => '0200',
            'message' => 'Accepted',
            'messageId' => 'sms-msg-1',
            'cost' => 1,
        ]),
    ]);

    $admin = User::factory()->admin()->create(['name' => 'Ops Admin']);
    $auction = Auction::factory()->create(['status' => 'live', 'ends_at' => now()->addDay()]);
    $bidder = Bidder::factory()->create([
        'name' => 'Grace Njeri',
        'phone' => '254768003470',
    ]);
    AuctionBid::factory()->accepted()->create([
        'auction_id' => $auction->id,
        'bidder_id' => $bidder->id,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.auction-bids', ['auction' => $auction])
        ->call('openMessage', $bidder->id)
        ->set('messageBody', 'Hello Grace, your item is ready for pickup.')
        ->call('sendMessage')
        ->assertHasNoErrors()
        ->assertSee('Delivered');

    $message = BidderMessage::query()->sole();

    expect($message->status)->toBe(BidderMessage::STATUS_DELIVERED)
        ->and($message->delivered_at)->not->toBeNull();

    Http::assertSent(fn ($request): bool => ($request->data()['phone'] ?? null) === '254768003470'
        && ($request->data()['message'] ?? null) === 'Hello Grace, your item is ready for pickup.'
        && ($request->data()['trackingId'] ?? null) === 'MSG-'.$message->id);
});

test('a failed admin bidder sms is stored as failed', function () {
    notifySmsConfig();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.mobilesasa.com/v2/send/message' => Http::response([
            'status' => false,
            'responseCode' => '0402',
            'message' => 'Insufficient balance.',
        ], 200),
    ]);

    $admin = User::factory()->admin()->create();
    $auction = Auction::factory()->create(['status' => 'live', 'ends_at' => now()->addDay()]);
    $bidder = Bidder::factory()->create(['phone' => '254768003470']);
    AuctionBid::factory()->accepted()->create([
        'auction_id' => $auction->id,
        'bidder_id' => $bidder->id,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.auction-bids', ['auction' => $auction])
        ->call('openMessage', $bidder->id)
        ->set('messageBody', 'Please call the office.')
        ->call('sendMessage')
        ->assertSee('Failed')
        ->assertSee('Insufficient balance.');

    expect(BidderMessage::query()->sole()->status)->toBe(BidderMessage::STATUS_FAILED);
});
