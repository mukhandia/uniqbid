<?php

use App\Models\Auction;
use App\Models\Product;
use App\Models\SupportTicket;
use Database\Seeders\UniqBidDemoSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(UniqBidDemoSeeder::class);
});

test('home page renders featured auctions and shop highlights', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('The lowest')
        ->assertSee('unique bid')
        ->assertSee('iPhone 15 Pro');
});

test('auctions index lists live auctions', function () {
    $this->get(route('auctions.index'))
        ->assertOk()
        ->assertSee('Live Auctions')
        ->assertSee('Samsung 55');
});

test('auction detail page is reachable by slug', function () {
    $auction = Auction::query()->where('slug', 'iphone-15-pro')->firstOrFail();

    $this->get(route('auctions.show', $auction))
        ->assertOk()
        ->assertSee($auction->name)
        ->assertSee('Place your bid');
});

test('shop index and product detail work', function () {
    $product = Product::query()->where('slug', 'p1')->firstOrFail();

    $this->get(route('shop.index'))
        ->assertOk()
        ->assertSee('Leather Cardholder');

    $this->get(route('shop.show', $product))
        ->assertOk()
        ->assertSee($product->name)
        ->assertSee('Add to cart');
});

test('static public pages are reachable', function () {
    foreach (['results', 'how-it-works', 'legal', 'contact', 'account', 'cart'] as $route) {
        $this->get(route($route))->assertOk();
    }
});

test('contact form creates a support ticket', function () {
    Livewire::test('pages::contact')
        ->set('name', 'James Mwangi')
        ->set('contact', '0712345678')
        ->set('subject', 'Bid help')
        ->set('message', 'I need help understanding my bid status please.')
        ->call('submit')
        ->assertSet('ticketRef', fn ($ref) => str_starts_with((string) $ref, 'TKT'));

    expect(SupportTicket::query()->count())->toBe(1);
});
