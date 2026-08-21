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

test('auction countdown uses days hours and minutes without seconds', function () {
    $auction = Auction::query()->where('slug', 'iphone-15-pro')->firstOrFail();

    $this->get(route('auctions.show', $auction))
        ->assertOk()
        ->assertSee("\${d}d : \${String(h).padStart(2, '0')}h : \${String(m).padStart(2, '0')}m", false)
        ->assertDontSee("padStart(2, '0')}s", false);

    $this->get(route('auctions.index'))
        ->assertOk()
        ->assertSee("\${d}d : \${String(h).padStart(2, '0')}h : \${String(m).padStart(2, '0')}m", false)
        ->assertDontSee("padStart(2, '0')}s", false);
});

test('shop index and product detail work', function () {
    $product = Product::query()->orderBy('id')->firstOrFail();

    $this->get(route('shop.index'))
        ->assertOk()
        ->assertSee($product->name);

    $this->get(route('shop.show', $product))
        ->assertOk()
        ->assertSee($product->name)
        ->assertSee('Add to cart');
});

test('static public pages are reachable', function () {
    foreach (['past-sales', 'how-it-works', 'testimonials', 'legal', 'contact', 'account', 'cart', 'orders.track'] as $route) {
        $this->get(route($route))->assertOk();
    }
});

test('past sales page uses sale language without win tone', function () {
    $this->get(route('past-sales'))
        ->assertOk()
        ->assertSee('Past sales')
        ->assertDontSee('Past winners')
        ->assertDontSee('Winner');
});

test('how it works uses successful buy language', function () {
    $this->get(route('how-it-works'))
        ->assertOk()
        ->assertSee('Pay participation fee')
        ->assertSee('Successful buy')
        ->assertSee('Bid amount')
        ->assertDontSee('>Win<', false);
});
test('testimonials page shows placeholder customer stories', function () {
    $this->get(route('testimonials'))
        ->assertOk()
        ->assertSee('What people say about UniqBid')
        ->assertSee('Amina Wanjiku')
        ->assertSee('Browse live auctions');
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
