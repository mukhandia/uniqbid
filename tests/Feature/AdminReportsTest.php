<?php

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\Order;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

test('guests cannot access admin reports', function () {
    $this->get(route('admin.reports'))->assertRedirect(route('login'));
});

test('non admins cannot access admin reports', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.reports'))
        ->assertForbidden();
});

test('admins can view reports with date filters and range totals', function () {
    $this->travelTo(CarbonImmutable::parse('2026-08-27 12:00:00', 'Africa/Nairobi'));

    $admin = User::factory()->admin()->create();
    $inside = CarbonImmutable::parse('2026-08-20 11:00:00', 'Africa/Nairobi');

    Order::factory()->create([
        'total' => 2500,
        'status' => Order::STATUS_PAID,
        'paid_at' => $inside,
    ]);
    $sale = Auction::factory()->create([
        'name' => 'Report camera sale',
        'bid_fee' => 40,
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);
    AuctionBid::factory()->accepted()->create([
        'auction_id' => $sale->id,
        'paid_at' => $inside,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.reports'))
        ->assertOk()
        ->assertSee('Reports')
        ->assertSee('Revenue over time')
        ->assertSee('Where money comes from')
        ->assertSee('Bidding activity')
        ->assertSee(Money::kes(2500))
        ->assertSee(Money::kes(40))
        ->assertSee('Report camera sale')
        ->assertSee('Last 7 days')
        ->assertSee('Custom dates');

    $html = $this->actingAs($admin)
        ->get(route('admin.reports'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('data-report-chart=');

    preg_match('/data-report-chart=\'(.*?)\'/s', $html, $match);

    expect($match[1] ?? null)->not->toBeNull();

    $payload = json_decode(html_entity_decode($match[1], ENT_QUOTES), true);

    expect($payload)->toBeArray()
        ->and($payload['type'] ?? null)->toBe('line')
        ->and($payload['datasets'] ?? null)->toBeArray();

    Livewire::actingAs($admin)
        ->test('pages::admin.reports')
        ->assertSee(Money::kes(2500))
        ->set('period', 'custom')
        ->set('from', '2026-06-01')
        ->set('to', '2026-06-30')
        ->assertSee('No shop sales, fees, or successful buys in this range.')
        ->assertDontSee(Money::kes(2500));
});
