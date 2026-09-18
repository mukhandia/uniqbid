<?php

use App\Models\Auction;
use App\Models\Product;
use App\Models\SupportTicket;
use Database\Seeders\ContentSeeder;
use Database\Seeders\UniqBidDemoSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(UniqBidDemoSeeder::class);
    $this->seed(ContentSeeder::class);
});

test('home page renders featured auctions and shop highlights', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('The lowest')
        ->assertSee('unique amount')
        ->assertSee('Motorbike')
        ->assertSee('Featured in the shop')
        ->assertSee('Classic leather-strap dress watch')
        ->assertSee('aria-label="Previous"', false)
        ->assertSee('aria-label="Next"', false)
        ->assertDontSee('scrollbar-width:thin', false)
        ->assertDontSee('Accessories, hampers & gifts');
});

test('home hero prefers the featured live sale over the soonest closing sale', function () {
    Auction::query()->update(['is_featured' => false]);

    Auction::factory()->onAuctionChannel()->create([
        'name' => 'Soonest Closing Sale',
        'status' => 'live',
        'ends_at' => now()->addHour(),
        'is_featured' => false,
    ]);
    Auction::factory()->onAuctionChannel()->create([
        'name' => 'Chosen Homepage Hero',
        'status' => 'live',
        'ends_at' => now()->addDays(3),
        'is_featured' => true,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Chosen Homepage Hero')
        ->assertSee('View Chosen Homepage Hero')
        ->assertDontSee('View Soonest Closing Sale');
});

test('home shop strip only lists featured products', function () {
    Product::query()->update(['is_featured' => false]);

    Product::factory()->create([
        'name' => 'Homepage Shop Star',
        'is_featured' => true,
    ]);
    Product::factory()->create([
        'name' => 'Hidden Shop Filler',
        'is_featured' => false,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Homepage Shop Star')
        ->assertSee('Featured in the shop')
        ->assertDontSee('Hidden Shop Filler');
});

test('home page keeps licence number in the footer only', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Licence No. AUC/2024/00847')
        ->assertDontSee('Licensed Kenyan Auctioneer · Est. 2024');
});

test('auctions index lists live sales', function () {
    $this->get(route('auctions.index'))
        ->assertOk()
        ->assertSee('Live Sales/Auctions')
        ->assertSee('Samsung S24');
});

test('auction detail page does not show live sale statistics', function () {
    $auction = Auction::query()->where('slug', 'motorbike-sale')->firstOrFail();

    $this->get(route('auctions.show', $auction))
        ->assertOk()
        ->assertDontSee('bids placed');
});

test('auction detail page is reachable by slug', function () {
    $auction = Auction::query()->where('slug', 'motorbike-sale')->firstOrFail();

    $this->get(route('auctions.show', $auction))
        ->assertOk()
        ->assertSee($auction->name)
        ->assertSee('Place your amount');
});

test('auction pages poll the server every thirty seconds for countdown labels', function () {
    $auction = Auction::query()->where('slug', 'motorbike-sale')->firstOrFail();

    $response = $this->get(route('auctions.show', $auction));

    $response
        ->assertOk()
        ->assertSee('wire:poll.10s.visible="refreshLiveSnapshot"', false)
        ->assertDontSee('wire:ignore', false);

    expect($response->getContent())->toMatch('/\d+d : \d{2}h : \d{2}m/');

    $this->get(route('auctions.index'))
        ->assertOk()
        ->assertSee('wire:poll.', false)
        ->assertSee('.visible', false);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('wire:poll.', false)
        ->assertSee('.visible', false);
});

test('shop index and product detail work', function () {
    $product = Product::query()->inShop()->orderBy('id')->firstOrFail();

    $this->get(route('shop.index'))
        ->assertOk()
        ->assertSee($product->name);

    $this->get(route('shop.show', $product))
        ->assertOk()
        ->assertSee($product->name)
        ->assertSee('Add to cart')
        ->assertSee('Delivery fee charges apply outside Nairobi');
});

test('static public pages are reachable', function () {
    foreach (['past-sales', 'how-it-works', 'testimonials', 'faqs', 'legal', 'contact', 'account', 'cart', 'orders.track'] as $route) {
        $this->get(route($route))->assertOk();
    }

    $this->get(route('legal'))
        ->assertSee('Delivery fee charges apply outside Nairobi')
        ->assertDontSee('KES 350');
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
test('testimonials page shows customer stories', function () {
    $this->get(route('testimonials'))
        ->assertOk()
        ->assertSee('What people say about UniqBid')
        ->assertSee('Amina Wanjiku')
        ->assertSee('Browse live sales');
});

test('faqs page shows seeded questions', function () {
    $this->get(route('faqs'))
        ->assertOk()
        ->assertSee('Frequently asked questions')
        ->assertSee('What is a lowest unique bid?');
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
