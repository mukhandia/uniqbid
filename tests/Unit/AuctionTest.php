<?php

use App\Models\Auction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('live scope only includes open auctions that have not ended', function () {
    Auction::factory()->create([
        'name' => 'Live Now',
        'status' => 'live',
        'ends_at' => now()->addHour(),
    ]);
    Auction::factory()->create([
        'name' => 'Ended Live Flag',
        'status' => 'live',
        'ends_at' => now()->subMinute(),
    ]);
    Auction::factory()->create([
        'name' => 'Closed',
        'status' => 'closed',
        'ends_at' => now()->addHour(),
    ]);

    expect(Auction::query()->live()->pluck('name')->all())
        ->toBe(['Live Now']);
});

test('live on auction channel scope excludes orphan and shop-only sales', function () {
    Auction::factory()->onAuctionChannel()->create([
        'name' => 'Catalogue Sale',
        'status' => 'live',
        'ends_at' => now()->addHour(),
    ]);
    Auction::factory()->create([
        'name' => 'Orphan Sale',
        'status' => 'live',
        'ends_at' => now()->addHour(),
        'product_id' => null,
    ]);

    expect(Auction::query()->liveOnAuctionChannel()->pluck('name')->all())
        ->toBe(['Catalogue Sale']);
});

test('featured scope filters featured auctions', function () {
    Auction::factory()->create(['name' => 'Featured One', 'is_featured' => true]);
    Auction::factory()->create(['name' => 'Not Featured', 'is_featured' => false]);

    expect(Auction::query()->featured()->pluck('name')->all())
        ->toBe(['Featured One']);
});

test('admin status scope matches live ending soon and needs settle queues', function () {
    Auction::factory()->create([
        'name' => 'Open Long',
        'status' => 'live',
        'ends_at' => now()->addDay(),
        'is_featured' => false,
    ]);
    Auction::factory()->create([
        'name' => 'Open Soon',
        'status' => 'live',
        'ends_at' => now()->addHours(2),
        'is_featured' => true,
    ]);
    Auction::factory()->create([
        'name' => 'Ready To Settle',
        'status' => 'live',
        'ends_at' => now()->subMinute(),
        'settled_at' => null,
        'is_featured' => false,
    ]);

    expect(Auction::query()->adminStatus('live')->pluck('name')->all())
        ->toEqualCanonicalizing(['Open Long', 'Open Soon'])
        ->and(Auction::query()->adminStatus('ending_soon')->pluck('name')->all())
        ->toBe(['Open Soon'])
        ->and(Auction::query()->adminStatus('needs_settle')->pluck('name')->all())
        ->toBe(['Ready To Settle'])
        ->and(Auction::query()->adminStatus('homepage')->pluck('name')->all())
        ->toBe(['Open Soon']);
});

test('making a sale the homepage hero unfeatures other sales', function () {
    $previous = Auction::factory()->create(['name' => 'Previous Hero', 'is_featured' => true]);
    $next = Auction::factory()->create(['name' => 'New Hero', 'is_featured' => false]);

    $next->makeHomepageHero();

    expect($next->fresh()->is_featured)->toBeTrue()
        ->and($previous->fresh()->is_featured)->toBeFalse()
        ->and(Auction::query()->featured()->count())->toBe(1);
});

test('removing a sale from the homepage hero clears its featured flag', function () {
    $auction = Auction::factory()->create(['is_featured' => true]);

    $auction->removeFromHomepageHero();

    expect($auction->fresh()->is_featured)->toBeFalse();
});

test('ensure homepage hero keeps only one live featured sale and promotes a replacement', function () {
    $endedHero = Auction::factory()->onAuctionChannel()->create([
        'name' => 'Ended Hero',
        'status' => 'closed',
        'ends_at' => now()->subHour(),
        'is_featured' => true,
    ]);
    $duplicateHero = Auction::factory()->onAuctionChannel()->create([
        'name' => 'Duplicate Live Hero',
        'status' => 'live',
        'ends_at' => now()->addHours(5),
        'display_order' => 20,
        'is_featured' => true,
    ]);
    $preferred = Auction::factory()->onAuctionChannel()->create([
        'name' => 'Preferred Live',
        'status' => 'live',
        'ends_at' => now()->addHours(2),
        'display_order' => 10,
        'is_featured' => true,
    ]);

    $hero = Auction::ensureHomepageHero();

    expect($hero?->is($preferred))->toBeTrue()
        ->and($preferred->fresh()->is_featured)->toBeTrue()
        ->and($duplicateHero->fresh()->is_featured)->toBeFalse()
        ->and($endedHero->fresh()->is_featured)->toBeFalse()
        ->and(Auction::query()->liveOnAuctionChannel()->featured()->count())->toBe(1);
});

test('when the homepage hero leaves live status another live sale is promoted', function () {
    $hero = Auction::factory()->onAuctionChannel()->create([
        'status' => 'live',
        'ends_at' => now()->addHour(),
        'is_featured' => true,
    ]);
    $other = Auction::factory()->onAuctionChannel()->create([
        'status' => 'live',
        'ends_at' => now()->addHours(3),
        'is_featured' => false,
    ]);

    $hero->forceFill([
        'status' => 'closed',
        'ends_at' => now()->subMinute(),
        'settled_at' => now(),
    ])->save();

    Auction::ensureHomepageHero();

    expect($hero->fresh()->is_featured)->toBeFalse()
        ->and($other->fresh()->is_featured)->toBeTrue();
});

test('removing homepage hero promotes a different live sale when available', function () {
    $hero = Auction::factory()->onAuctionChannel()->create([
        'status' => 'live',
        'ends_at' => now()->addHour(),
        'display_order' => 10,
        'is_featured' => true,
    ]);
    $other = Auction::factory()->onAuctionChannel()->create([
        'status' => 'live',
        'ends_at' => now()->addHours(2),
        'display_order' => 20,
        'is_featured' => false,
    ]);

    $hero->removeFromHomepageHero();

    expect($hero->fresh()->is_featured)->toBeFalse()
        ->and($other->fresh()->is_featured)->toBeTrue();
});

test('auction helpers expose primary image and end timestamp', function () {
    $auction = Auction::factory()->create([
        'ends_at' => now()->addHours(2),
    ]);

    expect($auction->primaryImageUrl())->not->toBeEmpty()
        ->and($auction->endsAtMs())->toBe((int) $auction->ends_at->getTimestampMs())
        ->and($auction->getRouteKeyName())->toBe('slug');
});
