<?php

namespace App\Services;

use App\Models\AuctionBid;
use App\Models\AuctionResult;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\ReportDateRange;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class AdminReportsService
{
    /**
     * @return array{
     *     range: ReportDateRange,
     *     summary: array{
     *         shopSales: int,
     *         shopOrders: int,
     *         participationFees: int,
     *         acceptedBids: int,
     *         successfulBuyValue: int,
     *         successfulBuys: int,
     *         paidSuccessfulBuys: int
     *     },
     *     revenueChart: array{type: string, currency: bool, labels: list<string>, datasets: list<array{label: string, data: list<int>, color: string}>},
     *     bidsChart: array{type: string, currency: bool, labels: list<string>, datasets: list<array{label: string, data: list<int>, color: string}>},
     *     mixChart: array{type: string, currency: bool, labels: list<string>, datasets: list<array{label: string, data: list<int>, color: string, colors?: list<string>}>},
     *     hasMixData: bool,
     *     topSales: Collection<int, array{id: int, name: string, slug: string, accepted_bids_count: int, fees_total: int}>,
     *     topProducts: Collection<int, OrderItem>,
     *     awaitingPayment: Collection<int, AuctionResult>
     * }
     */
    public function make(string $period, string $from = '', string $to = ''): array
    {
        $range = ReportDateRange::fromFilter($period, $from, $to);

        $orders = Order::query()
            ->paidNotCancelled()
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$range->startUtc(), $range->endUtc()])
            ->get(['id', 'paid_at', 'total']);

        $acceptedBids = AuctionBid::query()
            ->select([
                'auction_bids.id',
                'auction_bids.auction_id',
                'auction_bids.paid_at',
                'auctions.bid_fee',
                'auctions.name',
                'auctions.slug',
            ])
            ->join('auctions', 'auctions.id', '=', 'auction_bids.auction_id')
            ->where('auction_bids.status', 'accepted')
            ->whereNotNull('auction_bids.paid_at')
            ->whereBetween('auction_bids.paid_at', [$range->startUtc(), $range->endUtc()])
            ->get();

        $otherBids = AuctionBid::query()
            ->whereIn('status', ['pending_payment', 'failed'])
            ->whereBetween('created_at', [$range->startUtc(), $range->endUtc()])
            ->get(['status', 'created_at']);

        $results = AuctionResult::query()
            ->whereBetween('sold_on', [$range->fromDate(), $range->toDate()])
            ->with(['auction:id,slug,name'])
            ->orderByDesc('sold_on')
            ->get([
                'id',
                'auction_id',
                'name',
                'sale_price',
                'sold_on',
                'sale_payment_status',
                'buyer_display',
            ]);

        $shopByDay = $this->sumByNairobiDay(
            $orders,
            fn (Order $order): ?CarbonInterface => $this->asTimestamp($order->paid_at),
            fn (Order $order): int => (int) $order->total,
        );
        $feesByDay = $this->sumByNairobiDay(
            $acceptedBids,
            fn (AuctionBid $bid): ?CarbonInterface => $this->asTimestamp($bid->getAttribute('paid_at')),
            fn (AuctionBid $bid): int => (int) $bid->getAttribute('bid_fee'),
        );
        $buysByDay = $this->sumByNairobiDay(
            $results,
            fn (AuctionResult $result): ?CarbonInterface => $this->asTimestamp($result->sold_on),
            fn (AuctionResult $result): int => (int) $result->sale_price,
        );
        $acceptedByDay = $this->countByNairobiDay(
            $acceptedBids,
            fn (AuctionBid $bid): ?CarbonInterface => $this->asTimestamp($bid->getAttribute('paid_at')),
        );
        $pendingByDay = $this->countByNairobiDay(
            $otherBids->where('status', 'pending_payment'),
            fn (AuctionBid $bid): ?CarbonInterface => $this->asTimestamp($bid->created_at),
        );
        $failedByDay = $this->countByNairobiDay(
            $otherBids->where('status', 'failed'),
            fn (AuctionBid $bid): ?CarbonInterface => $this->asTimestamp($bid->created_at),
        );

        $labels = [];
        $shopSeries = [];
        $feesSeries = [];
        $buysSeries = [];
        $acceptedSeries = [];
        $pendingSeries = [];
        $failedSeries = [];

        foreach ($range->days() as $day) {
            $key = $day->toDateString();
            $labels[] = $range->dayLabel($day);
            $shopSeries[] = $shopByDay[$key] ?? 0;
            $feesSeries[] = $feesByDay[$key] ?? 0;
            $buysSeries[] = $buysByDay[$key] ?? 0;
            $acceptedSeries[] = $acceptedByDay[$key] ?? 0;
            $pendingSeries[] = $pendingByDay[$key] ?? 0;
            $failedSeries[] = $failedByDay[$key] ?? 0;
        }

        $shopSales = (int) $orders->sum('total');
        $participationFees = (int) $acceptedBids->sum(fn (AuctionBid $bid): int => (int) $bid->getAttribute('bid_fee'));
        $successfulBuyValue = (int) $results->sum('sale_price');

        return [
            'range' => $range,
            'summary' => [
                'shopSales' => $shopSales,
                'shopOrders' => $orders->count(),
                'participationFees' => $participationFees,
                'acceptedBids' => $acceptedBids->count(),
                'successfulBuyValue' => $successfulBuyValue,
                'successfulBuys' => $results->count(),
                'paidSuccessfulBuys' => $results->where('sale_payment_status', 'paid')->count(),
            ],
            'revenueChart' => [
                'type' => 'line',
                'currency' => true,
                'labels' => $labels,
                'datasets' => [
                    ['label' => 'Shop sales', 'data' => $shopSeries, 'color' => '#234394'],
                    ['label' => 'Participation fees', 'data' => $feesSeries, 'color' => '#7c3aed'],
                    ['label' => 'Successful buys', 'data' => $buysSeries, 'color' => '#F2B01E'],
                ],
            ],
            'bidsChart' => [
                'type' => 'bar',
                'currency' => false,
                'labels' => $labels,
                'datasets' => [
                    ['label' => 'Fee paid', 'data' => $acceptedSeries, 'color' => '#059669'],
                    ['label' => 'Awaiting payment', 'data' => $pendingSeries, 'color' => '#F2B01E'],
                    ['label' => 'Failed', 'data' => $failedSeries, 'color' => '#E31E24'],
                ],
            ],
            'mixChart' => [
                'type' => 'doughnut',
                'currency' => true,
                'labels' => ['Shop sales', 'Participation fees', 'Successful buys'],
                'datasets' => [
                    [
                        'label' => 'Money recorded',
                        'data' => [$shopSales, $participationFees, $successfulBuyValue],
                        'color' => '#234394',
                        'colors' => ['#234394', '#7c3aed', '#F2B01E'],
                    ],
                ],
            ],
            'hasMixData' => ($shopSales + $participationFees + $successfulBuyValue) > 0,
            'topSales' => $this->topSales($acceptedBids),
            'topProducts' => $this->topProducts($range),
            'awaitingPayment' => $results
                ->where('sale_payment_status', 'awaiting_payment')
                ->take(8)
                ->values(),
        ];
    }

    /**
     * @param  Collection<int, AuctionBid>  $acceptedBids
     * @return Collection<int, array{id: int, name: string, slug: string, accepted_bids_count: int, fees_total: int}>
     */
    private function topSales(Collection $acceptedBids): Collection
    {
        return $acceptedBids
            ->groupBy('auction_id')
            ->map(function (Collection $bids): array {
                $first = $bids->first();

                if (! $first instanceof AuctionBid) {
                    return [
                        'id' => 0,
                        'name' => '',
                        'slug' => '',
                        'accepted_bids_count' => 0,
                        'fees_total' => 0,
                    ];
                }

                return [
                    'id' => (int) $first->auction_id,
                    'name' => (string) $first->getAttribute('name'),
                    'slug' => (string) $first->getAttribute('slug'),
                    'accepted_bids_count' => $bids->count(),
                    'fees_total' => (int) $bids->sum(fn (AuctionBid $bid): int => (int) $bid->getAttribute('bid_fee')),
                ];
            })
            ->sortByDesc('fees_total')
            ->values()
            ->take(8);
    }

    /**
     * @return Collection<int, OrderItem>
     */
    private function topProducts(ReportDateRange $range): Collection
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', Order::STATUS_CANCELLED)
            ->whereNotNull('orders.paid_at')
            ->whereBetween('orders.paid_at', [$range->startUtc(), $range->endUtc()])
            ->selectRaw('order_items.product_name as product_name, order_items.product_slug as product_slug, SUM(order_items.quantity) as units_sold, SUM(order_items.line_total) as revenue')
            ->groupBy('order_items.product_name', 'order_items.product_slug')
            ->orderByDesc('revenue')
            ->limit(8)
            ->get();
    }

    /**
     * @template T of object
     *
     * @param  Collection<int, T>  $rows
     * @param  callable(T): ?CarbonInterface  $timestamp
     * @param  callable(T): int  $amount
     * @return array<string, int>
     */
    private function sumByNairobiDay(Collection $rows, callable $timestamp, callable $amount): array
    {
        $totals = [];

        foreach ($rows as $row) {
            $key = $this->nairobiDayKey($timestamp($row));

            if ($key === null) {
                continue;
            }

            $totals[$key] = ($totals[$key] ?? 0) + $amount($row);
        }

        return $totals;
    }

    /**
     * @template T of object
     *
     * @param  Collection<int, T>  $rows
     * @param  callable(T): ?CarbonInterface  $timestamp
     * @return array<string, int>
     */
    private function countByNairobiDay(Collection $rows, callable $timestamp): array
    {
        $totals = [];

        foreach ($rows as $row) {
            $key = $this->nairobiDayKey($timestamp($row));

            if ($key === null) {
                continue;
            }

            $totals[$key] = ($totals[$key] ?? 0) + 1;
        }

        return $totals;
    }

    private function nairobiDayKey(?CarbonInterface $timestamp): ?string
    {
        if ($timestamp === null) {
            return null;
        }

        return $timestamp->timezone(ReportDateRange::TIMEZONE)->toDateString();
    }

    private function asTimestamp(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return CarbonImmutable::parse($value);
        }

        return null;
    }
}
