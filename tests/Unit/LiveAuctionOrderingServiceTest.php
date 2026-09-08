<?php

use App\Models\Auction;
use App\Services\LiveAuctionOrderingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('auto shuffle changes live auction order when the time bucket advances', function () {
    Carbon::setTestNow('2026-09-08 10:00:00');

    $auctions = collect([
        Auction::factory()->create(['name' => 'Alpha', 'status' => 'live', 'ends_at' => now()->addDay()]),
        Auction::factory()->create(['name' => 'Bravo', 'status' => 'live', 'ends_at' => now()->addDay()]),
        Auction::factory()->create(['name' => 'Charlie', 'status' => 'live', 'ends_at' => now()->addDay()]),
        Auction::factory()->create(['name' => 'Delta', 'status' => 'live', 'ends_at' => now()->addDay()]),
    ]);

    $service = app(LiveAuctionOrderingService::class);

    $first = $service->autoShuffle($auctions, 5)->pluck('name')->all();

    Carbon::setTestNow('2026-09-08 10:00:05');
    $second = $service->autoShuffle($auctions, 5)->pluck('name')->all();

    Carbon::setTestNow('2026-09-08 10:00:10');
    $third = $service->autoShuffle($auctions, 5)->pluck('name')->all();

    expect($first)->toHaveCount(4)
        ->and($second)->toHaveCount(4)
        ->and($third)->toHaveCount(4)
        ->and($first)->not->toBe($second)
        ->and($second)->not->toBe($third);

    Carbon::setTestNow();
});

test('auto shuffle keeps a stable order within the same five second bucket', function () {
    Carbon::setTestNow('2026-09-08 10:00:01');

    $auctions = collect([
        Auction::factory()->create(['name' => 'One', 'status' => 'live', 'ends_at' => now()->addDay()]),
        Auction::factory()->create(['name' => 'Two', 'status' => 'live', 'ends_at' => now()->addDay()]),
        Auction::factory()->create(['name' => 'Three', 'status' => 'live', 'ends_at' => now()->addDay()]),
    ]);

    $service = app(LiveAuctionOrderingService::class);
    $first = $service->autoShuffle($auctions, 5)->pluck('id')->all();

    Carbon::setTestNow('2026-09-08 10:00:04');
    $second = $service->autoShuffle($auctions, 5)->pluck('id')->all();

    expect($first)->toBe($second);

    Carbon::setTestNow();
});
