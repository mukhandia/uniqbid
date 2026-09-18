<?php

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\AuctionResult;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
});

test('past sales show an avatar when no buyer photo is set', function () {
    AuctionResult::factory()->create([
        'name' => 'Past Phone Sale',
        'buyer_display' => 'Amina W.',
        'buyer_image' => null,
        'sale_price' => 45,
    ]);

    $this->get(route('past-sales'))
        ->assertOk()
        ->assertSee('Amina W.')
        ->assertSee('Secured this item for')
        ->assertSee('>A</div>', false);
});

test('past sales show the buyer photo when admin has uploaded one', function () {
    AuctionResult::factory()->create([
        'name' => 'Past TV Sale',
        'buyer_display' => 'James M.',
        'buyer_image' => 'buyers/james.jpg',
        'sale_price' => 120,
    ]);

    $this->get(route('past-sales'))
        ->assertOk()
        ->assertSee('/storage/buyers/james.jpg')
        ->assertSee('James M.');
});

test('admins can upload a successful-buyer photo after settle', function () {
    $admin = User::factory()->admin()->create();
    $auction = Auction::factory()->create([
        'status' => 'closed',
        'settled_at' => now(),
    ]);
    $bid = AuctionBid::factory()->accepted()->create([
        'auction_id' => $auction->id,
        'amount' => 33,
    ]);
    $result = AuctionResult::factory()->create([
        'auction_id' => $auction->id,
        'auction_bid_id' => $bid->id,
        'sale_price' => 33,
        'buyer_display' => 'Grace N.',
        'buyer_image' => null,
    ]);

    $file = UploadedFile::fake()->image('winner.jpg', 400, 400);

    Livewire::actingAs($admin)
        ->test('pages::admin.auction-bids', ['auction' => $auction])
        ->set('buyerImageUpload', $file)
        ->call('saveBuyerImage')
        ->assertHasNoErrors();

    expect($result->fresh()->buyer_image)->not->toBeNull()
        ->and($result->fresh()->buyerImageUrl())->toStartWith('/storage/');
});
