<?php

use App\Services\AdminReportsService;
use App\Support\Money;
use App\Support\ReportDateRange;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new
#[Layout('layouts.app')]
#[Title('Admin: Reports')]
class extends Component {
    #[Url(except: '30d')]
    public string $period = '30d';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    public function updatedPeriod(string $value): void
    {
        if ($value !== 'custom' || ($this->from !== '' && $this->to !== '')) {
            return;
        }

        $range = ReportDateRange::fromFilter('30d');
        $this->from = $range->fromDate();
        $this->to = $range->toDate();
    }

    public function updatedFrom(): void
    {
        $this->period = 'custom';
    }

    public function updatedTo(): void
    {
        $this->period = 'custom';
    }

    public function resetRange(): void
    {
        $this->period = '30d';
        $this->from = '';
        $this->to = '';
    }

    public function with(): array
    {
        $report = app(AdminReportsService::class)->make($this->period, $this->from, $this->to);

        return [
            'report' => $report,
            'today' => now()->timezone(ReportDateRange::TIMEZONE)->toDateString(),
        ];
    }
}; ?>

@php
    $range = $report['range'];
    $summary = $report['summary'];
@endphp

<div class="space-y-8">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <flux:heading size="xl">Reports</flux:heading>
            <flux:text class="mt-1">
                How UniqBid performed from {{ $range->label() }}. Shop checkout, participation fees, and successful unique-bid amounts are shown separately.
            </flux:text>
        </div>
        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
            <flux:field>
                <flux:label>Range</flux:label>
                <flux:select wire:model.live="period" class="sm:w-40">
                    <flux:select.option value="7d">Last 7 days</flux:select.option>
                    <flux:select.option value="30d">Last 30 days</flux:select.option>
                    <flux:select.option value="90d">Last 90 days</flux:select.option>
                    <flux:select.option value="custom">Custom dates</flux:select.option>
                </flux:select>
            </flux:field>
            @if ($period === 'custom')
                <flux:field>
                    <flux:label>From</flux:label>
                    <flux:input type="date" wire:model.live="from" max="{{ $today }}" class="sm:w-40" />
                </flux:field>
                <flux:field>
                    <flux:label>To</flux:label>
                    <flux:input type="date" wire:model.live="to" max="{{ $today }}" class="sm:w-40" />
                </flux:field>
            @endif
            @if ($period !== '30d' || $from !== '' || $to !== '')
                <flux:button type="button" variant="ghost" wire:click="resetRange">Reset</flux:button>
            @endif
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <div class="rounded-xl border border-navy/30 bg-navy/10 p-4 dark:border-sky-400/30 dark:bg-navy/40">
            <div class="text-xs font-semibold uppercase tracking-wide text-navy dark:text-sky-200">Shop sales</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums text-navy dark:text-white">{{ Money::kes($summary['shopSales']) }}</div>
            <p class="mt-1 text-xs text-navy/70 dark:text-sky-100/80">
                {{ $summary['shopOrders'] }} paid shop {{ $summary['shopOrders'] === 1 ? 'order' : 'orders' }}. Not bid fees.
            </p>
        </div>
        <div class="rounded-xl border border-violet-500/40 bg-violet-500/10 p-4 dark:border-violet-400/30 dark:bg-violet-500/15">
            <div class="text-xs font-semibold uppercase tracking-wide text-violet-900 dark:text-violet-200">Participation fees</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums text-violet-950 dark:text-white">{{ Money::kes($summary['participationFees']) }}</div>
            <p class="mt-1 text-xs text-violet-950/75 dark:text-violet-100/80">
                {{ $summary['acceptedBids'] }} accepted {{ $summary['acceptedBids'] === 1 ? 'bid' : 'bids' }} in this range.
            </p>
        </div>
        <div class="rounded-xl border border-gold/50 bg-gold/10 p-4 dark:border-gold/40 dark:bg-gold/15">
            <div class="text-xs font-semibold uppercase tracking-wide text-amber-900 dark:text-gold">Successful buys</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums text-amber-950 dark:text-white">{{ Money::kes($summary['successfulBuyValue']) }}</div>
            <p class="mt-1 text-xs text-amber-950/75 dark:text-amber-50/80">
                {{ $summary['successfulBuys'] }} unique-bid {{ $summary['successfulBuys'] === 1 ? 'win' : 'wins' }} recorded.
                {{ $summary['paidSuccessfulBuys'] }} paid.
            </p>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <section class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900 xl:col-span-2">
            <h2 class="font-medium">Revenue over time</h2>
            <p class="mt-1 text-sm text-zinc-500">Daily shop checkout totals, participation fees, and unique-bid sale amounts.</p>
            <div
                wire:key="revenue-{{ $period }}-{{ $range->fromDate() }}-{{ $range->toDate() }}"
                class="relative mt-4 h-72 w-full"
                data-report-chart='@json($report['revenueChart'])'
            >
                <canvas class="size-full"></canvas>
            </div>
        </section>
        <section class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="font-medium">Where money comes from</h2>
            <p class="mt-1 text-sm text-zinc-500">Totals for this range, kept as three separate streams.</p>
            @if ($report['hasMixData'])
                <div
                    wire:key="mix-{{ $period }}-{{ $range->fromDate() }}-{{ $range->toDate() }}"
                    class="relative mt-4 h-72 w-full"
                    data-report-chart='@json($report['mixChart'])'
                >
                    <canvas class="size-full"></canvas>
                </div>
            @else
                <p class="mt-8 text-center text-sm text-zinc-500">No shop sales, fees, or successful buys in this range.</p>
            @endif
        </section>
    </div>

    <section class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        <h2 class="font-medium">Bidding activity</h2>
        <p class="mt-1 text-sm text-zinc-500">Accepted bids by payment day. Unpaid and failed attempts by the day they were placed.</p>
        <div
            wire:key="bids-{{ $period }}-{{ $range->fromDate() }}-{{ $range->toDate() }}"
            class="relative mt-4 h-72 w-full"
            data-report-chart='@json($report['bidsChart'])'
        >
            <canvas class="size-full"></canvas>
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <h2 class="font-medium">Top sales by participation fees</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead class="bg-zinc-50 text-left dark:bg-zinc-800">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Sale</th>
                            <th class="px-4 py-3 font-semibold">Bids</th>
                            <th class="px-4 py-3 font-semibold">Fees</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse ($report['topSales'] as $sale)
                            <tr wire:key="top-sale-{{ $sale['id'] }}">
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.auction-bids', $sale['slug']) }}" class="font-medium hover:underline" wire:navigate>{{ $sale['name'] }}</a>
                                </td>
                                <td class="px-4 py-3 tabular-nums">{{ $sale['accepted_bids_count'] }}</td>
                                <td class="px-4 py-3 font-semibold tabular-nums">{{ Money::kes($sale['fees_total']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-6 text-center text-zinc-500">No paid bids in this range.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <h2 class="font-medium">Top shop products</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead class="bg-zinc-50 text-left dark:bg-zinc-800">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Product</th>
                            <th class="px-4 py-3 font-semibold">Units</th>
                            <th class="px-4 py-3 font-semibold">Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse ($report['topProducts'] as $product)
                            <tr wire:key="top-product-{{ $product->product_slug }}">
                                <td class="px-4 py-3 font-medium">{{ $product->product_name }}</td>
                                <td class="px-4 py-3 tabular-nums">{{ (int) $product->units_sold }}</td>
                                <td class="px-4 py-3 font-semibold tabular-nums">{{ Money::kes((int) $product->revenue) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-6 text-center text-zinc-500">No shop sales in this range.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
            <h2 class="font-medium">Successful buys awaiting payment</h2>
            <p class="mt-1 text-sm text-zinc-500">Unique-bid wins recorded in this range that still need the sale amount paid.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 text-left dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Sale</th>
                        <th class="px-4 py-3 font-semibold">Buyer</th>
                        <th class="px-4 py-3 font-semibold">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($report['awaitingPayment'] as $result)
                        <tr wire:key="awaiting-{{ $result->id }}">
                            <td class="px-4 py-3">
                                @if ($result->auction)
                                    <a href="{{ route('admin.auction-bids', $result->auction) }}" class="font-medium hover:underline" wire:navigate>{{ $result->auction->name }}</a>
                                @else
                                    <span class="font-medium">{{ $result->name }}</span>
                                @endif
                                <div class="text-xs text-zinc-500">{{ $result->sold_on?->format('d M Y') }}</div>
                            </td>
                            <td class="px-4 py-3">{{ $result->buyer_display }}</td>
                            <td class="px-4 py-3 font-semibold tabular-nums">{{ Money::kes($result->sale_price) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-6 text-center text-zinc-500">No unpaid unique-bid wins in this range.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

@script
<script>
    queueMicrotask(() => window.UniqbidReportsCharts?.boot())
</script>
@endscript
