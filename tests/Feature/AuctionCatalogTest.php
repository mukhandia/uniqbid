<?php

use App\Models\Auction;
use Livewire\Livewire;

test('auctions index filters by category', function () {
    Auction::factory()->create([
        'name' => 'Phone Deal',
        'category' => 'Electronics',
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);
    Auction::factory()->create([
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
    Auction::factory()->create([
        'name' => 'Quiet Auction',
        'bids_count' => 10,
        'status' => 'live',
        'ends_at' => now()->addDays(3),
    ]);
    Auction::factory()->create([
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

test('auctions index sorts by lowest bid fee', function () {
    Auction::factory()->create([
        'name' => 'Expensive Fee',
        'bid_fee' => 100,
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);
    Auction::factory()->create([
        'name' => 'Cheap Fee',
        'bid_fee' => 20,
        'status' => 'live',
        'ends_at' => now()->addDays(2),
    ]);

    $html = Livewire::test('pages::auctions.index')
        ->set('sort', 'fee')
        ->html();

    expect(strpos($html, 'Cheap Fee'))->toBeLessThan(strpos($html, 'Expensive Fee'));
});

test('auctions index hides closed auctions', function () {
    Auction::factory()->create([
        'name' => 'Still Open',
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);
    Auction::factory()->create([
        'name' => 'Already Closed',
        'status' => 'closed',
        'ends_at' => now()->subHour(),
    ]);

    Livewire::test('pages::auctions.index')
        ->assertSee('Still Open')
        ->assertDontSee('Already Closed');
});
