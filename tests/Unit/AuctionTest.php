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

test('auction helpers expose primary image and end timestamp', function () {
    $auction = Auction::factory()->create([
        'ends_at' => now()->addHours(2),
    ]);

    expect($auction->primaryImageUrl())->not->toBeEmpty()
        ->and($auction->endsAtMs())->toBe((int) $auction->ends_at->getTimestampMs())
        ->and($auction->getRouteKeyName())->toBe('slug');
});
