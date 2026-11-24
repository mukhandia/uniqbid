<?php

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\AuctionResult;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\AdminReportsService;
use App\Support\ReportDateRange;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('preset ranges use africa nairobi calendar days including today', function () {
    $this->travelTo(CarbonImmutable::parse('2026-08-27 10:00:00', 'Africa/Nairobi'));

    $range = ReportDateRange::fromFilter('7d');

    expect($range->fromDate())->toBe('2026-08-21')
        ->and($range->toDate())->toBe('2026-08-27')
        ->and($range->days())->toHaveCount(7);
});

test('custom dates are inclusive and swapped when reversed', function () {
    $range = ReportDateRange::fromFilter('custom', '2026-08-20', '2026-08-10');

    expect($range->fromDate())->toBe('2026-08-10')
        ->and($range->toDate())->toBe('2026-08-20')
        ->and($range->days())->toHaveCount(11);
});

test('reports totals and top lists honour the selected date range', function () {
    $this->travelTo(CarbonImmutable::parse('2026-08-27 12:00:00', 'Africa/Nairobi'));

    $inside = CarbonImmutable::parse('2026-08-20 14:00:00', 'Africa/Nairobi');
    $outside = CarbonImmutable::parse('2026-07-01 14:00:00', 'Africa/Nairobi');

    $watch = Product::factory()->create(['name' => 'Nairobi watch', 'price' => 2000]);
    $insideOrder = Order::factory()->create([
        'status' => Order::STATUS_PAID,
        'total' => 4000,
        'paid_at' => $inside,
    ]);
    OrderItem::factory()->create([
        'order_id' => $insideOrder->id,
        'product_id' => $watch->id,
        'product_name' => $watch->name,
        'product_slug' => $watch->slug,
        'unit_price' => 2000,
        'quantity' => 2,
        'line_total' => 4000,
    ]);
    Order::factory()->create([
        'status' => Order::STATUS_PAID,
        'total' => 9000,
        'paid_at' => $outside,
    ]);

    $liveSale = Auction::factory()->create([
        'name' => 'Inside gadget sale',
        'bid_fee' => 50,
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);
    AuctionBid::factory()->accepted()->create([
        'auction_id' => $liveSale->id,
        'paid_at' => $inside,
    ]);
    AuctionBid::factory()->accepted()->create([
        'auction_id' => $liveSale->id,
        'paid_at' => $outside,
    ]);
    AuctionBid::factory()->failed()->create([
        'auction_id' => $liveSale->id,
        'created_at' => $inside,
        'updated_at' => $inside,
    ]);

    $settled = Auction::factory()->create([
        'name' => 'Settled speaker sale',
        'status' => 'closed',
        'ends_at' => $inside,
        'settled_at' => $inside,
    ]);
    AuctionResult::factory()->create([
        'auction_id' => $settled->id,
        'name' => 'Settled speaker sale',
        'sale_price' => 1800,
        'sold_on' => $inside->toDateString(),
        'sale_payment_status' => 'awaiting_payment',
        'buyer_display' => 'Amina K.',
    ]);
    AuctionResult::factory()->create([
        'auction_id' => $settled->id,
        'name' => 'Old speaker sale',
        'sale_price' => 5000,
        'sold_on' => $outside->toDateString(),
        'sale_payment_status' => 'paid',
        'buyer_display' => 'Old B.',
    ]);

    $report = app(AdminReportsService::class)->make('30d');

    expect($report['summary']['shopSales'])->toBe(4000)
        ->and($report['summary']['shopOrders'])->toBe(1)
        ->and($report['summary']['participationFees'])->toBe(50)
        ->and($report['summary']['acceptedBids'])->toBe(1)
        ->and($report['summary']['successfulBuyValue'])->toBe(1800)
        ->and($report['summary']['successfulBuys'])->toBe(1)
        ->and($report['topSales']->first()['name'])->toBe('Inside gadget sale')
        ->and($report['topSales']->first()['fees_total'])->toBe(50)
        ->and($report['topProducts']->first()->product_name)->toBe('Nairobi watch')
        ->and((int) $report['topProducts']->first()->revenue)->toBe(4000)
        ->and($report['awaitingPayment'])->toHaveCount(1)
        ->and($report['hasMixData'])->toBeTrue();

    $custom = app(AdminReportsService::class)->make('custom', '2026-07-01', '2026-07-31');

    expect($custom['summary']['shopSales'])->toBe(9000)
        ->and($custom['summary']['participationFees'])->toBe(50)
        ->and($custom['summary']['successfulBuyValue'])->toBe(5000);
});
