<?php

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\Bidder;
use App\Models\BidderMessage;
use App\Models\User;
use App\Services\AuctionSettlementService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['name' => 'Ops Admin']);
    $this->auction = Auction::factory()->create([
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);
    $this->bidder = Bidder::factory()->create([
        'name' => 'Grace Njeri',
        'phone' => '254755555555',
    ]);
    AuctionBid::factory()->accepted()->create([
        'auction_id' => $this->auction->id,
        'bidder_id' => $this->bidder->id,
        'amount' => 45,
    ]);
});

test('each bidder on a sale has a message button', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.auction-bids', ['auction' => $this->auction])
        ->assertSee('data-test="message-bidder-'.$this->bidder->id.'"', false)
        ->assertSee('Message Grace Njeri');
});

test('admin can message a bidder on the sale and it is saved as pending', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.auction-bids', ['auction' => $this->auction])
        ->call('openMessage', $this->bidder->id)
        ->assertSet('messageBidderId', $this->bidder->id)
        ->assertSee('No messages sent to this bidder yet.')
        ->set('messageBody', 'Hello Grace, your item is ready for pickup.')
        ->call('sendMessage')
        ->assertHasNoErrors()
        ->assertSet('messageBody', '')
        ->assertSee('Hello Grace, your item is ready for pickup.')
        ->assertSee('Pending')
        ->assertSee('by Ops Admin');

    $message = BidderMessage::query()->sole();

    expect($message->auction_id)->toBe($this->auction->id)
        ->and($message->bidder_id)->toBe($this->bidder->id)
        ->and($message->phone)->toBe('254755555555')
        ->and($message->sent_by)->toBe($this->admin->id)
        ->and($message->status)->toBe(BidderMessage::STATUS_PENDING);
});

test('message history shows each status', function () {
    BidderMessage::factory()->create([
        'auction_id' => $this->auction->id,
        'bidder_id' => $this->bidder->id,
        'body' => 'First note',
    ]);
    BidderMessage::factory()->delivered()->create([
        'auction_id' => $this->auction->id,
        'bidder_id' => $this->bidder->id,
        'body' => 'Second note',
    ]);
    BidderMessage::factory()->failed()->create([
        'auction_id' => $this->auction->id,
        'bidder_id' => $this->bidder->id,
        'body' => 'Third note',
    ]);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.auction-bids', ['auction' => $this->auction])
        ->call('openMessage', $this->bidder->id)
        ->assertSeeInOrder(['Third note', 'Failed', 'Second note', 'Delivered', 'First note', 'Pending']);
});

test('an empty or too long message is rejected', function (string $body) {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.auction-bids', ['auction' => $this->auction])
        ->call('openMessage', $this->bidder->id)
        ->set('messageBody', $body)
        ->call('sendMessage')
        ->assertHasErrors(['messageBody']);

    expect(BidderMessage::query()->count())->toBe(0);
})->with([
    'empty' => [''],
    'too long' => [str_repeat('a', BidderMessage::MAX_LENGTH + 1)],
]);

test('admin cannot message a bidder who has not bid on the sale', function () {
    $stranger = Bidder::factory()->create();

    $component = Livewire::actingAs($this->admin)
        ->test('pages::admin.auction-bids', ['auction' => $this->auction])
        ->set('messageBidderId', $stranger->id)
        ->set('messageBody', 'Hello there');

    expect(fn () => $component->call('sendMessage'))->toThrow(ModelNotFoundException::class);

    expect(BidderMessage::query()->count())->toBe(0);
});

test('bidding and settling a sale does not create bidder messages', function () {
    $this->auction->forceFill(['ends_at' => now()->subMinute()])->save();

    app(AuctionSettlementService::class)->settle($this->auction);

    expect(BidderMessage::query()->count())->toBe(0);
});
