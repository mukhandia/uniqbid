<?php

use App\Models\Auction;
use App\Services\AuctionSettlementService;
use App\Services\DestroySaleService;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Layout('layouts.app')]
#[Title('Admin: Sales')]
class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 'all')]
    public string $status = 'all';

    #[Url(except: '')]
    public string $category = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->status = 'all';
        $this->category = '';
        $this->resetPage();
    }

    public function with(): array
    {
        $auctions = Auction::query()
            ->withCount([
                'bids as accepted_bids_count' => fn ($query) => $query->where('status', 'accepted'),
                'bids as pending_bids_count' => fn ($query) => $query->where('status', 'pending_payment'),
                'bids as failed_bids_count' => fn ($query) => $query->where('status', 'failed'),
            ])
            ->with('result')
            ->search($this->search)
            ->adminStatus($this->status)
            ->adminCategory($this->category)
            ->latest('ends_at')
            ->paginate(20);

        return [
            'auctions' => $auctions,
            'categories' => Auction::query()->distinct()->orderBy('category')->pluck('category'),
            'hasActiveFilters' => $this->search !== '' || $this->status !== 'all' || $this->category !== '',
        ];
    }

    public function settle(int $auctionId): void
    {
        $auction = Auction::query()->findOrFail($auctionId);
        $result = app(AuctionSettlementService::class)->settle($auction, auth()->user());

        $message = $result['successful_bid']
            ? 'Sale settled. Successful bid: '.Money::kes($result['successful_bid']->amount)
            : 'Sale settled with no unique accepted bid.';

        Flux::toast(variant: 'success', text: $message);
    }

    public function extend(int $auctionId): void
    {
        $auction = Auction::query()->findOrFail($auctionId);
        $auction->update([
            'ends_at' => max($auction->ends_at, now())->addMinutes($auction->extension_minutes),
            'status' => 'live',
            'settled_at' => null,
            'settled_by' => null,
        ]);

        Flux::toast(variant: 'success', text: "Sale extended by {$auction->extension_minutes} minutes.");
    }

    public function void(int $auctionId): void
    {
        $auction = Auction::query()->findOrFail($auctionId);
        $auction->update(['status' => 'voided', 'settled_at' => now(), 'settled_by' => auth()->id()]);
        app(\App\Services\CreateSaleFromProductService::class)->releaseReservedStock($auction);

        Flux::toast(variant: 'success', text: 'Sale voided. Participation-fee refunds must be processed once the payment provider is connected.');
    }

    public function rerun(int $auctionId): void
    {
        $auction = Auction::query()->findOrFail($auctionId);
        $auction->update([
            'status' => 'live',
            'ends_at' => now()->addDay(),
            'settled_at' => null,
            'settled_by' => null,
        ]);
        $auction->result()?->delete();

        Flux::toast(variant: 'success', text: 'Sale re-run for a new 24-hour window.');
    }

    public function reoffer(int $auctionId): void
    {
        $auction = Auction::query()->findOrFail($auctionId);
        $bid = app(AuctionSettlementService::class)->reofferToNextBidder($auction);

        Flux::toast(
            variant: $bid ? 'success' : 'warning',
            text: $bid
                ? 'Item re-offered to the next lowest unique bid: '.Money::kes($bid->amount)
                : 'No next unique bid is available. Re-run the sale instead.',
        );
    }

    public function toggleHomepageHero(int $auctionId): void
    {
        $auction = Auction::query()->findOrFail($auctionId);

        if ($auction->is_featured) {
            $auction->removeFromHomepageHero();
            Flux::toast(variant: 'success', text: 'This sale is no longer the homepage hero.');

            return;
        }

        if ($auction->status !== 'live' || $auction->ends_at->isPast()) {
            Flux::toast(variant: 'danger', text: 'Only a live sale can be shown on the homepage hero.');

            return;
        }

        $auction->makeHomepageHero();
        Flux::toast(variant: 'success', text: 'This sale is now the homepage hero.');
    }

    public function delete(int $auctionId): void
    {
        $auction = Auction::query()->findOrFail($auctionId);

        try {
            app(DestroySaleService::class)->handle($auction);
        } catch (ValidationException $exception) {
            Flux::toast(
                variant: 'danger',
                text: $exception->errors()['auction'][0] ?? 'This sale cannot be deleted.',
            );

            return;
        }

        Flux::toast(variant: 'success', text: 'Sale deleted. Reserved stock has been returned.');
    }
}; ?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">Sales & bidding</flux:heading>
        <flux:text class="mt-1">Click a sale name to open incoming bids and processing. That page refreshes every 3 minutes. Delete a mistaken sale only when no participation fee has been paid — that returns stock and removes the sale. Use Void when bidders have already paid, so bid history remains for refunds.</flux:text>
    </div>

    <div class="flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-end">
        <div class="lg:w-64">
            <flux:field>
                <flux:label>Search</flux:label>
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Name, category, or slug" />
            </flux:field>
        </div>
        <div class="lg:w-52">
            <flux:field>
                <flux:label>Status</flux:label>
                <flux:select wire:model.live="status">
                    <flux:select.option value="all">All statuses</flux:select.option>
                    <flux:select.option value="live">Live</flux:select.option>
                    <flux:select.option value="ending_soon">Ending within 6 hours</flux:select.option>
                    <flux:select.option value="needs_settle">Needs settle</flux:select.option>
                    <flux:select.option value="to_process">To process</flux:select.option>
                    <flux:select.option value="settled">Settled</flux:select.option>
                    <flux:select.option value="voided">Voided</flux:select.option>
                    <flux:select.option value="draft">Draft</flux:select.option>
                    <flux:select.option value="homepage">Homepage hero</flux:select.option>
                </flux:select>
            </flux:field>
        </div>
        <div class="lg:w-52">
            <flux:field>
                <flux:label>Category</flux:label>
                <flux:select wire:model.live="category">
                    <flux:select.option value="">All categories</flux:select.option>
                    @foreach ($categories as $saleCategory)
                        <flux:select.option value="{{ $saleCategory }}">{{ $saleCategory }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>
        </div>
        @if ($hasActiveFilters)
            <flux:button type="button" variant="ghost" wire:click="clearFilters">Clear filters</flux:button>
        @endif
        <flux:text class="lg:ms-auto">
            {{ $auctions->total() }} {{ $auctions->total() === 1 ? 'sale' : 'sales' }}
        </flux:text>
    </div>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900" wire:poll.180s.visible>
        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
            <thead class="bg-zinc-50 text-left dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 font-semibold">Sale</th>
                    <th class="px-4 py-3 font-semibold">Status</th>
                    <th class="px-4 py-3 font-semibold">Bids</th>
                    <th class="px-4 py-3 font-semibold">Closes</th>
                    <th class="px-4 py-3 font-semibold">Result</th>
                    <th class="px-4 py-3 font-semibold"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($auctions as $auction)
                    @php
                        $isSettled = $auction->settled_at !== null;
                        $isLiveOpen = $auction->status === 'live' && $auction->ends_at->isFuture();
                        $needsSettle = ! $isSettled && $auction->ends_at->isPast() && $auction->status !== 'voided';
                        $statusLabel = match (true) {
                            $isSettled => 'Settled',
                            $needsSettle => 'Needs settle',
                            $auction->status === 'live' => 'Live',
                            $auction->status === 'closed' => 'Closed',
                            $auction->status === 'voided' => 'Voided',
                            $auction->status === 'draft' => 'Draft',
                            default => ucfirst($auction->status),
                        };
                        $statusColor = match (true) {
                            $isLiveOpen => 'green',
                            $needsSettle => 'amber',
                            $isSettled => 'sky',
                            $auction->status === 'voided' => 'red',
                            default => 'zinc',
                        };
                    @endphp
                    <tr wire:key="admin-auction-{{ $auction->id }}">
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.auction-bids', $auction) }}" class="text-base font-semibold text-navy hover:underline dark:text-white" wire:navigate>
                                {{ $auction->name }}
                            </a>
                            <div class="mt-0.5 text-xs text-zinc-500">
                                {{ $auction->category }} · fee {{ Money::kes($auction->bid_fee) }} · no-unique: {{ $auction->no_unique_bid_rule }} ({{ $auction->extension_minutes }} min)
                            </div>
                            @if ($auction->is_featured)
                                <div class="mt-1.5">
                                    <flux:badge size="sm" color="amber">Homepage hero</flux:badge>
                                </div>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3">
                            <flux:badge size="sm" :color="$statusColor">{{ $statusLabel }}</flux:badge>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-col items-start gap-1.5">
                                <flux:badge size="sm" color="green">{{ $auction->accepted_bids_count }} fee paid</flux:badge>
                                <flux:badge size="sm" color="amber">{{ $auction->pending_bids_count }} awaiting payment</flux:badge>
                                <flux:badge size="sm" color="zinc">{{ $auction->failed_bids_count }} failed</flux:badge>
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3">
                            <div class="text-sm font-semibold tabular-nums">
                                {{ $auction->ends_at->isFuture() ? $auction->ends_at->diffForHumans() : 'Ended' }}
                            </div>
                            <div class="mt-0.5 text-xs font-medium text-zinc-600 dark:text-zinc-300">
                                {{ $auction->ends_at->timezone('Africa/Nairobi')->format('d M Y, H:i') }} EAT
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            @if ($auction->result)
                                <div class="text-sm font-semibold tabular-nums">{{ Money::kes($auction->result->sale_price) }}</div>
                                <div class="mt-0.5 text-xs font-medium text-zinc-600 dark:text-zinc-300">{{ $auction->result->buyer_display }}</div>
                                <div class="mt-1.5 flex flex-col items-start gap-1">
                                    <flux:badge size="sm" :color="$auction->result->isSalePaid() ? 'green' : 'amber'">
                                        {{ $auction->result->salePaymentLabel() }}
                                    </flux:badge>
                                    <flux:badge size="sm" :color="$auction->result->isPickedUp() ? 'green' : 'zinc'">
                                        {{ $auction->result->fulfilmentLabel() }}
                                    </flux:badge>
                                </div>
                            @else
                                <div class="text-sm font-medium text-zinc-500">No successful bid yet</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex flex-wrap justify-end gap-2">
                                <flux:button size="sm" href="{{ route('admin.auction-bids', $auction) }}" wire:navigate>
                                    Open
                                </flux:button>
                                @if ($auction->is_featured)
                                    <flux:button size="sm" variant="outline" wire:click="toggleHomepageHero({{ $auction->id }})" wire:confirm="Remove this sale from the homepage hero?">
                                        Remove from homepage
                                    </flux:button>
                                @elseif ($auction->status === 'live' && $auction->ends_at->isFuture())
                                    <flux:button size="sm" variant="outline" wire:click="toggleHomepageHero({{ $auction->id }})" wire:confirm="Show this sale on the homepage hero? This replaces any current homepage sale.">
                                        Set as homepage
                                    </flux:button>
                                @endif
                                @if (! $auction->settled_at && $auction->ends_at->isPast())
                                    <flux:button size="sm" wire:click="settle({{ $auction->id }})" wire:confirm="Settle this sale now?">
                                        Settle
                                    </flux:button>
                                @endif
                                @if ($auction->status === 'live')
                                    <flux:button size="sm" variant="outline" wire:click="extend({{ $auction->id }})" wire:confirm="Extend this sale?">
                                        Extend
                                    </flux:button>
                                    <flux:button size="sm" variant="outline" wire:click="void({{ $auction->id }})" wire:confirm="Void this sale?">
                                        Void
                                    </flux:button>
                                @elseif ($auction->status !== 'draft')
                                    @if ($auction->result)
                                        <flux:button size="sm" variant="outline" wire:click="reoffer({{ $auction->id }})" wire:confirm="Re-offer this item to the next lowest unique bidder?">
                                            Re-offer
                                        </flux:button>
                                    @endif
                                    <flux:button size="sm" variant="outline" wire:click="rerun({{ $auction->id }})" wire:confirm="Re-run this sale for 24 hours?">
                                        Re-run
                                    </flux:button>
                                @endif
                                @if ($auction->accepted_bids_count === 0)
                                    <flux:button size="sm" variant="danger" wire:click="delete({{ $auction->id }})" wire:confirm="Permanently delete this sale? Images and unpaid bid attempts will be removed, and reserved stock will be returned. This cannot be undone.">
                                        Delete
                                    </flux:button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-zinc-500">No sales match these filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $auctions->links() }}
    </div>
</div>
