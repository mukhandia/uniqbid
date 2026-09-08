<?php

use App\Models\Auction;
use Livewire\Livewire;

test('auctions index filters by category', function () {
    Auction::factory()->onAuctionChannel()->create([
        'name' => 'Phone Deal',
        'category' => 'Electronics',
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);
    Auction::factory()->onAuctionChannel()->create([
        'name' => 'Sofa Set',
        'category' => 'Home',
        'status' => 'live',
        'ends_at' => now()->addDays(2),
    ]);

    Livewire::test('pages::auctions.index')
        ->assertSee('Phone Deal')
        ->assertSee('Sofa Set')
        ->set('category', 'Electronics')
        ->assertSee('Phone Deal')
        ->assertDontSee('Sofa Set');
});

test('auctions index sorts by popular bids', function () {
    Auction::factory()->onAuctionChannel()->create([
        'name' => 'Quiet Auction',
        'bids_count' => 10,
        'status' => 'live',
        'ends_at' => now()->addDays(3),
    ]);
    Auction::factory()->onAuctionChannel()->create([
        'name' => 'Hot Auction',
        'bids_count' => 900,
        'status' => 'live',
        'ends_at' => now()->addDays(4),
    ]);

    $html = Livewire::test('pages::auctions.index')
        ->set('sort', 'popular')
        ->html();

    expect(strpos($html, 'Hot Auction'))->toBeLessThan(strpos($html, 'Quiet Auction'));
});

test('auctions index sorts by highest product value', function () {
    Auction::factory()->onAuctionChannel()->create([
        'name' => 'Lower Value',
        'retail_value' => 20000,
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);
    Auction::factory()->onAuctionChannel()->create([
        'name' => 'Higher Value',
        'retail_value' => 180000,
        'status' => 'live',
        'ends_at' => now()->addDays(2),
    ]);

    $html = Livewire::test('pages::auctions.index')
        ->set('sort', 'value')
        ->html();

    expect(strpos($html, 'Higher Value'))->toBeLessThan(strpos($html, 'Lower Value'));
});

test('auctions index hides closed auctions', function () {
    Auction::factory()->onAuctionChannel()->create([
        'name' => 'Still Open',
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);
    Auction::factory()->onAuctionChannel()->create([
        'name' => 'Already Closed',
        'status' => 'closed',
        'ends_at' => now()->subHour(),
    ]);

    Livewire::test('pages::auctions.index')
        ->assertSee('Still Open')
        ->assertDontSee('Already Closed');
});

test('auctions index hides live sales that are not on the auction channel', function () {
    Auction::factory()->onAuctionChannel()->create([
        'name' => 'Auction Channel Sale',
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);
    Auction::factory()->create([
        'name' => 'Orphan Live Sale',
        'status' => 'live',
        'ends_at' => now()->addDay(),
        'product_id' => null,
    ]);

    Livewire::test('pages::auctions.index')
        ->assertSee('Auction Channel Sale')
        ->assertDontSee('Orphan Live Sale');
});
