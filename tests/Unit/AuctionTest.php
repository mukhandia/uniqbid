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

test('auction helpers expose primary image and end timestamp', function () {
    $auction = Auction::factory()->create([
        'ends_at' => now()->addHours(2),
    ]);

    expect($auction->primaryImageUrl())->not->toBeEmpty()
        ->and($auction->endsAtMs())->toBe((int) $auction->ends_at->getTimestampMs())
        ->and($auction->getRouteKeyName())->toBe('slug');
});
