<?php

use App\Models\AuctionResult;
use Livewire\Livewire;

test('results path redirects to past sales', function () {
    $this->get('/results')->assertRedirect('/past-sales');
});

test('past sales can be searched by item name', function () {
    AuctionResult::factory()->create([
        'name' => 'iPhone 14 Pro',
        'buyer_display' => 'Amina W.',
        'sold_on' => now()->subDays(5),
    ]);
    AuctionResult::factory()->create([
        'name' => 'Samsung TV',
        'buyer_display' => 'James M.',
        'sold_on' => now()->subDays(10),
    ]);

    Livewire::test('pages::results')
        ->assertSee('iPhone 14 Pro')
        ->assertSee('Samsung TV')
        ->set('q', 'iPhone')
        ->assertSee('iPhone 14 Pro')
        ->assertDontSee('Samsung TV');
});

test('past sales can be searched by buyer display', function () {
    AuctionResult::factory()->create([
        'name' => 'Coffee Maker',
        'buyer_display' => 'Njeri K.',
        'sold_on' => now()->subDays(3),
    ]);
    AuctionResult::factory()->create([
        'name' => 'Blender',
        'buyer_display' => 'Otieno O.',
        'sold_on' => now()->subDays(4),
    ]);

    Livewire::test('pages::results')
        ->set('q', 'Njeri')
        ->assertSee('Coffee Maker')
        ->assertDontSee('Blender');
});

test('past sales can be filtered by month', function () {
    AuctionResult::factory()->create([
        'name' => 'January Sale Item',
        'sold_on' => now()->startOfYear()->addDays(2),
    ]);
    AuctionResult::factory()->create([
        'name' => 'March Sale Item',
        'sold_on' => now()->startOfYear()->addMonths(2)->addDays(2),
    ]);

    $month = now()->startOfYear()->format('Y-m');

    Livewire::test('pages::results')
        ->set('month', $month)
        ->assertSee('January Sale Item')
        ->assertDontSee('March Sale Item');
});

test('past sales shows empty state when nothing matches', function () {
    AuctionResult::factory()->create([
        'name' => 'Known Item',
        'sold_on' => now()->subDay(),
    ]);

    Livewire::test('pages::results')
        ->set('q', 'does-not-exist-xyz')
        ->assertSee('No sales match your search');
});
