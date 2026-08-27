<?php

use App\Services\AdminDashboardService;
use App\Support\Money;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.app')]
#[Title('Admin: Dashboard')]
class extends Component {
    public function with(): array
    {
        $dashboard = app(AdminDashboardService::class);
        $attention = $dashboard->attention();

        return [
            'snapshot' => $dashboard->snapshot(),
            'attention' => $attention,
            'hasAttention' => collect($attention)->contains(fn ($rows) => $rows->isNotEmpty()),
            'liveSales' => $dashboard->liveSales(),
            'recentOrders' => $dashboard->recentOrders(),
        ];
    }
}; ?>

<div class="space-y-8">
    <div>
        <flux:heading size="xl">Dashboard</flux:heading>
        <flux:text class="mt-1">Review live sales, shop fulfilment, and support from one place.</flux:text>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <a href="{{ route('admin.auctions') }}" class="rounded-xl border border-emerald-500/40 bg-emerald-500/10 p-4 transition hover:border-emerald-400/70 dark:border-emerald-400/30 dark:bg-emerald-500/15" wire:navigate>
            <div class="flex items-start gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-emerald-600 text-white">
                    <flux:icon.bolt variant="micro" class="size-5" />
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-200">Live sales</div>
                    <div class="mt-1 text-2xl font-semibold text-emerald-950 dark:text-white">{{ $snapshot['liveSales'] }}</div>
                    <p class="mt-1 text-xs leading-relaxed text-emerald-900/80 dark:text-emerald-100/80">
                        Unique-bid listings that are open right now. People can still place bids.
                        {{ $snapshot['endingSoon'] }}
                        {{ $snapshot['endingSoon'] === 1 ? 'closes' : 'close' }}
                        within 6 hours.
                    </p>
                </div>
            </div>
        </a>
        <a href="{{ route('admin.auctions') }}" class="rounded-xl border border-gold/50 bg-gold/10 p-4 transition hover:border-gold dark:border-gold/40 dark:bg-gold/15" wire:navigate>
            <div class="flex items-start gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-gold text-gold-foreground">
                    <flux:icon.clock variant="micro" class="size-5" />
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-semibold uppercase tracking-wide text-amber-900 dark:text-gold">Sales to settle</div>
                    <div class="mt-1 text-2xl font-semibold text-amber-950 dark:text-white">{{ $snapshot['unsettledSales'] }}</div>
                    <p class="mt-1 text-xs leading-relaxed text-amber-950/75 dark:text-amber-50/80">
                        The countdown has ended. Open Sales and click Settle so UniqBid can record the successful unique bid.
                    </p>
                </div>
            </div>
        </a>
        <a href="{{ route('admin.orders', ['status' => 'attention']) }}" class="rounded-xl border border-navy/30 bg-navy/10 p-4 transition hover:border-navy/60 dark:border-sky-400/30 dark:bg-navy/40" wire:navigate>
            <div class="flex items-start gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-navy text-navy-foreground">
                    <flux:icon.shopping-bag variant="micro" class="size-5" />
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-semibold uppercase tracking-wide text-navy dark:text-sky-200">Shop orders to pack</div>
                    <div class="mt-1 text-2xl font-semibold text-navy dark:text-white">{{ $snapshot['ordersToPack'] }}</div>
                    <p class="mt-1 text-xs leading-relaxed text-navy/70 dark:text-sky-100/80">
                        Paid shop checkout orders still waiting to pack, dispatch, or collect. Not unique-bid sales.
                    </p>
                </div>
            </div>
        </a>
        <a href="{{ route('admin.tickets') }}" class="rounded-xl border border-red/40 bg-red/10 p-4 transition hover:border-red/70 dark:border-red/40 dark:bg-red/15" wire:navigate>
            <div class="flex items-start gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-red text-red-foreground">
                    <flux:icon.inbox variant="micro" class="size-5" />
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-semibold uppercase tracking-wide text-red dark:text-red-200">Unread tickets</div>
                    <div class="mt-1 text-2xl font-semibold text-red dark:text-white">{{ $snapshot['unreadTickets'] }}</div>
                    <p class="mt-1 text-xs leading-relaxed text-red/80 dark:text-red-100/80">
                        Contact messages not opened yet. {{ $snapshot['openTickets'] }} tickets are still open or in progress.
                    </p>
                </div>
            </div>
        </a>
        <div class="rounded-xl border border-sky-500/40 bg-sky-500/10 p-4 dark:border-sky-400/30 dark:bg-sky-500/15">
            <div class="flex items-start gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-sky-600 text-white">
                    <flux:icon.banknotes variant="micro" class="size-5" />
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-semibold uppercase tracking-wide text-sky-900 dark:text-sky-200">Shop sales today</div>
                    <div class="mt-1 text-2xl font-semibold text-sky-950 dark:text-white">{{ Money::kes($snapshot['shopSalesToday']) }}</div>
                    <p class="mt-1 text-xs leading-relaxed text-sky-950/75 dark:text-sky-100/80">
                        Paid shop checkout totals today (Africa/Nairobi). This is not bid participation fees.
                    </p>
                </div>
            </div>
        </div>
        <div class="rounded-xl border border-violet-500/40 bg-violet-500/10 p-4 dark:border-violet-400/30 dark:bg-violet-500/15">
            <div class="flex items-start gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-violet-600 text-white">
                    <flux:icon.ticket variant="micro" class="size-5" />
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-semibold uppercase tracking-wide text-violet-900 dark:text-violet-200">Participation fees today</div>
                    <div class="mt-1 text-2xl font-semibold text-violet-950 dark:text-white">{{ Money::kes($snapshot['participationFeesToday']) }}</div>
                    <p class="mt-1 text-xs leading-relaxed text-violet-950/75 dark:text-violet-100/80">
                        Fees paid today to place a unique bid. Each accepted bid adds that sale’s participation fee. This is not shop product sales.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <section class="space-y-4">
        <div>
            <flux:heading size="lg">Needs attention</flux:heading>
            <flux:text class="mt-1">Empty queues stay hidden.</flux:text>
        </div>

        @unless ($hasAttention)
            <div class="rounded-xl border border-zinc-200 bg-white p-5 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                All caught up. Nothing needs attention right now.
            </div>
        @endunless

        @if ($attention['unsettledSales']->isNotEmpty())
            <div class="overflow-hidden rounded-xl border border-gold/40 bg-gold/5 dark:border-gold/30 dark:bg-gold/10">
                <div class="flex items-center justify-between gap-3 border-b border-gold/30 px-4 py-3 dark:border-gold/20">
                    <h2 class="font-medium">Sales to settle</h2>
                    <a href="{{ route('admin.auctions') }}" class="text-sm text-navy hover:underline dark:text-white" wire:navigate>Open sales</a>
                </div>
                <ul class="divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    @foreach ($attention['unsettledSales'] as $sale)
                        <li wire:key="unsettled-{{ $sale->id }}" class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                            <div>
                                <a href="{{ route('admin.auction-bids', $sale) }}" class="font-medium hover:underline" wire:navigate>{{ $sale->name }}</a>
                                <div class="text-xs text-zinc-500">Ended {{ $sale->ends_at->timezone('Africa/Nairobi')->format('d M Y H:i') }} EAT</div>
                            </div>
                            <flux:badge size="sm" color="amber">Settle</flux:badge>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($attention['awaitingSalePayment']->isNotEmpty())
            <div class="overflow-hidden rounded-xl border border-amber-500/40 bg-amber-500/5 dark:border-amber-400/30 dark:bg-amber-500/10">
                <div class="border-b border-amber-500/30 px-4 py-3 dark:border-amber-400/20">
                    <h2 class="font-medium">Successful buys awaiting payment</h2>
                </div>
                <ul class="divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    @foreach ($attention['awaitingSalePayment'] as $result)
                        <li wire:key="sale-pay-{{ $result->id }}" class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                            <div>
                                @if ($result->auction)
                                    <a href="{{ route('admin.auction-bids', $result->auction) }}" class="font-medium hover:underline" wire:navigate>{{ $result->auction->name }}</a>
                                @else
                                    <span class="font-medium">{{ $result->name }}</span>
                                @endif
                                <div class="text-xs text-zinc-500">{{ Money::kes($result->sale_price) }} · {{ $result->buyer_display }}</div>
                            </div>
                            <flux:badge size="sm" color="amber">Awaiting payment</flux:badge>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($attention['overdueFulfilment']->isNotEmpty())
            <div class="overflow-hidden rounded-xl border border-red/40 bg-red/5 dark:border-red/30 dark:bg-red/10">
                <div class="border-b border-red/30 px-4 py-3 dark:border-red/20">
                    <h2 class="font-medium">Overdue sale collection / delivery</h2>
                </div>
                <ul class="divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    @foreach ($attention['overdueFulfilment'] as $result)
                        <li wire:key="sale-due-{{ $result->id }}" class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                            <div>
                                @if ($result->auction)
                                    <a href="{{ route('admin.auction-bids', $result->auction) }}" class="font-medium hover:underline" wire:navigate>{{ $result->auction->name }}</a>
                                @else
                                    <span class="font-medium">{{ $result->name }}</span>
                                @endif
                                <div class="text-xs text-zinc-500">Due {{ $result->fulfilment_due_at?->timezone('Africa/Nairobi')->format('d M Y H:i') }} EAT</div>
                            </div>
                            <flux:badge size="sm" color="red">Overdue</flux:badge>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($attention['ordersToPack']->isNotEmpty())
            <div class="overflow-hidden rounded-xl border border-navy/30 bg-navy/5 dark:border-sky-400/30 dark:bg-navy/20">
                <div class="flex items-center justify-between gap-3 border-b border-navy/20 px-4 py-3 dark:border-sky-400/20">
                    <h2 class="font-medium">Shop orders to pack</h2>
                    <a href="{{ route('admin.orders', ['status' => 'attention']) }}" class="text-sm text-navy hover:underline dark:text-white" wire:navigate>Open orders</a>
                </div>
                <ul class="divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    @foreach ($attention['ordersToPack'] as $order)
                        <li wire:key="pack-{{ $order->id }}" class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                            <div>
                                <a href="{{ route('admin.orders.show', $order) }}" class="font-mono font-medium hover:underline" wire:navigate>{{ $order->reference }}</a>
                                <div class="text-xs text-zinc-500">{{ $order->customer_name }} · {{ Money::kes($order->total) }}</div>
                            </div>
                            <flux:badge size="sm" color="amber">{{ $order->statusLabel() }}</flux:badge>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($attention['unreadTickets']->isNotEmpty())
            <div class="overflow-hidden rounded-xl border border-red/40 bg-red/5 dark:border-red/30 dark:bg-red/10">
                <div class="flex items-center justify-between gap-3 border-b border-red/30 px-4 py-3 dark:border-red/20">
                    <h2 class="font-medium">Unread tickets</h2>
                    <a href="{{ route('admin.tickets') }}" class="text-sm text-navy hover:underline dark:text-white" wire:navigate>Open tickets</a>
                </div>
                <ul class="divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    @foreach ($attention['unreadTickets'] as $ticket)
                        <li wire:key="dash-ticket-{{ $ticket->id }}" class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                            <div>
                                <a href="{{ route('admin.tickets.show', $ticket) }}" class="font-medium hover:underline" wire:navigate>{{ $ticket->subject }}</a>
                                <div class="text-xs text-zinc-500">{{ $ticket->name }} · {{ $ticket->reference() }}</div>
                            </div>
                            <flux:badge size="sm" color="red">New</flux:badge>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($attention['verificationsRequested']->isNotEmpty())
            <div class="overflow-hidden rounded-xl border border-violet-500/40 bg-violet-500/5 dark:border-violet-400/30 dark:bg-violet-500/10">
                <div class="flex items-center justify-between gap-3 border-b border-violet-500/30 px-4 py-3 dark:border-violet-400/20">
                    <h2 class="font-medium">Customer verification requested</h2>
                    <a href="{{ route('admin.customer-verifications') }}" class="text-sm text-navy hover:underline dark:text-white" wire:navigate>Open verification</a>
                </div>
                <ul class="divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    @foreach ($attention['verificationsRequested'] as $customer)
                        <li wire:key="verify-{{ $customer->id }}" class="px-4 py-3">
                            <div class="font-medium">{{ $customer->name }}</div>
                            <div class="text-xs text-zinc-500">{{ $customer->phone }} · {{ $customer->email }}</div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($attention['outOfStock']->isNotEmpty())
            <div class="overflow-hidden rounded-xl border border-red/40 bg-red/5 dark:border-red/30 dark:bg-red/10">
                <div class="flex items-center justify-between gap-3 border-b border-red/30 px-4 py-3 dark:border-red/20">
                    <h2 class="font-medium">Out of stock</h2>
                    <a href="{{ route('admin.products') }}" class="text-sm text-navy hover:underline dark:text-white" wire:navigate>Open products</a>
                </div>
                <ul class="divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    @foreach ($attention['outOfStock'] as $product)
                        <li wire:key="out-stock-{{ $product->id }}" class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                            <div>
                                <div class="font-medium">{{ $product->name }}</div>
                                <div class="text-xs text-zinc-500">0 in stock. Shop cannot sell this until you add quantity.</div>
                            </div>
                            <flux:badge size="sm" color="red">Out of stock</flux:badge>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($attention['reservedStock']->isNotEmpty())
            <div class="overflow-hidden rounded-xl border border-amber-500/40 bg-amber-500/5 dark:border-amber-400/30 dark:bg-amber-500/10">
                <div class="flex items-center justify-between gap-3 border-b border-amber-500/30 px-4 py-3 dark:border-amber-400/20">
                    <h2 class="font-medium">Reserved for live sales</h2>
                    <a href="{{ route('admin.products') }}" class="text-sm text-navy hover:underline dark:text-white" wire:navigate>Open products</a>
                </div>
                <ul class="divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    @foreach ($attention['reservedStock'] as $product)
                        <li wire:key="reserved-{{ $product->id }}" class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                            <div>
                                <div class="font-medium">{{ $product->name }}</div>
                                <div class="text-xs text-zinc-500">
                                    {{ $product->reserved_stock }} reserved · {{ $product->stock }} in stock · {{ $product->availableStock() }} available for shop
                                </div>
                            </div>
                            <flux:badge size="sm" color="amber">Reserved</flux:badge>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="overflow-hidden rounded-xl border border-emerald-500/30 bg-emerald-500/5 dark:border-emerald-400/25 dark:bg-emerald-500/10">
            <div class="flex items-center justify-between gap-3 border-b border-emerald-500/25 px-4 py-3 dark:border-emerald-400/20">
                <h2 class="font-medium">Live sales</h2>
                <a href="{{ route('admin.auctions') }}" class="text-sm text-navy hover:underline dark:text-white" wire:navigate>All sales</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead class="bg-emerald-500/10 text-left dark:bg-emerald-500/15">
                        <tr>
                            <th class="px-4 py-3 font-medium">Sale</th>
                            <th class="px-4 py-3 font-medium">Closes</th>
                            <th class="px-4 py-3 font-medium">Bids</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse ($liveSales as $sale)
                            <tr wire:key="live-{{ $sale->id }}">
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.auction-bids', $sale) }}" class="font-medium hover:underline" wire:navigate>{{ $sale->name }}</a>
                                    @if ($sale->is_featured)
                                        <div class="text-xs text-amber-700 dark:text-amber-200">Homepage hero</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-zinc-600 dark:text-zinc-300">{{ $sale->ends_at->timezone('Africa/Nairobi')->format('d M Y H:i') }} EAT</td>
                                <td class="px-4 py-3 text-xs">Accepted {{ $sale->accepted_bids_count }} · Pending {{ $sale->pending_bids_count }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-6 text-center text-zinc-500">No live sales.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-navy/25 bg-navy/5 dark:border-sky-400/25 dark:bg-navy/20">
            <div class="flex items-center justify-between gap-3 border-b border-navy/20 px-4 py-3 dark:border-sky-400/20">
                <h2 class="font-medium">Recent shop orders</h2>
                <a href="{{ route('admin.orders') }}" class="text-sm text-navy hover:underline dark:text-white" wire:navigate>All orders</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead class="bg-navy/10 text-left dark:bg-navy/40">
                        <tr>
                            <th class="px-4 py-3 font-medium">Order</th>
                            <th class="px-4 py-3 font-medium">Customer</th>
                            <th class="px-4 py-3 font-medium">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse ($recentOrders as $order)
                            <tr wire:key="recent-order-{{ $order->id }}">
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.orders.show', $order) }}" class="font-mono hover:underline" wire:navigate>{{ $order->reference }}</a>
                                    <div class="text-xs text-zinc-500">{{ $order->statusLabel() }}</div>
                                </td>
                                <td class="px-4 py-3">{{ $order->customer_name }}</td>
                                <td class="px-4 py-3">{{ Money::kes($order->total) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-6 text-center text-zinc-500">No shop orders yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
