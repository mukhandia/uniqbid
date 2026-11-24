<?php

use App\Models\Auction;
use App\Models\Product;
use App\Models\User;
use App\Services\CreateSaleFromProductService;
use App\Services\DestroySaleService;
use App\Support\AuditLogger;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

test('activity log page sits under system audit for super users', function () {
    $admin = User::factory()->admin()->create();
    $this->seed(RolePermissionSeeder::class);

    $this->actingAs($admin->fresh())
        ->get(route('admin.auctions'))
        ->assertOk()
        ->assertSee('System Audit')
        ->assertSee('Activity Log')
        ->assertDontSee('>Activity</', false);
});

test('audit logger records domain events', function () {
    $auction = Auction::factory()->create(['name' => 'Audit Target Sale']);

    AuditLogger::log('auctions', 'sale extended', 'extended', $auction, [
        'extension_minutes' => 60,
    ]);

    $activity = Activity::query()->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->log_name)->toBe('auctions')
        ->and($activity->description)->toBe('sale extended')
        ->and($activity->event)->toBe('extended')
        ->and($activity->subject_id)->toBe($auction->id);
});

test('creating and deleting a sale writes auction audit entries', function () {
    $product = Product::factory()->create([
        'name' => 'Audit Phone',
        'stock' => 3,
        'reserved_stock' => 0,
    ]);

    $auction = app(CreateSaleFromProductService::class)->handle($product, [
        'bid_fee' => 40,
        'reserve_stock' => true,
    ]);

    expect(Activity::query()->where('event', 'created')->where('log_name', 'auctions')->exists())->toBeTrue();

    app(DestroySaleService::class)->handle($auction);

    expect(Activity::query()->where('event', 'deleted')->where('log_name', 'auctions')->exists())->toBeTrue();
});

test('voiding a sale from admin writes an audit entry', function () {
    $admin = User::factory()->admin()->create();
    $auction = Auction::factory()->onAuctionChannel()->create([
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.auctions')
        ->call('void', $auction->id);

    expect(Activity::query()->where('event', 'voided')->where('log_name', 'auctions')->exists())->toBeTrue()
        ->and($auction->fresh()->status)->toBe('voided');
});
