<?php

use App\Models\Auction;
use App\Services\AuctionSettlementService;
use App\Support\Money;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.app')]
#[Title('Admin: Sales')]
class extends Component {
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
}; ?>

@php
    $auctions = Auction::query()
        ->withCount([
            'bids as accepted_bids_count' => fn ($query) => $query->where('status', 'accepted'),
            'bids as pending_bids_count' => fn ($query) => $query->where('status', 'pending_payment'),
            'bids as failed_bids_count' => fn ($query) => $query->where('status', 'failed'),
        ])
        ->with('result')
        ->latest('ends_at')
        ->paginate(20);
@endphp

<div class="space-y-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Sales & bidding</flux:heading>
            <flux:text class="mt-1">Review live and ended sales, settlement status, and bid activity. One live sale can be the homepage hero at a time.</flux:text>
        </div>
        <div class="flex flex-wrap justify-end gap-2">
            <flux:button href="{{ route('admin.products') }}" variant="outline">
                Products
            </flux:button>
            <flux:button href="{{ route('admin.categories') }}" variant="outline">
                Categories
            </flux:button>
            <flux:button href="{{ route('admin.customers') }}" variant="outline">
                Customers
            </flux:button>
            <flux:button href="{{ route('admin.customer-verifications') }}" variant="outline">
                Customer verification
            </flux:button>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
            <thead class="bg-zinc-50 text-left dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 font-medium">Sale</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium">Bids</th>
                    <th class="px-4 py-3 font-medium">Closes</th>
                    <th class="px-4 py-3 font-medium">Result</th>
                    <th class="px-4 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($auctions as $auction)
                    <tr wire:key="admin-auction-{{ $auction->id }}">
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ $auction->name }}</div>
                            <div class="text-xs text-zinc-500">{{ $auction->category }} · fee {{ Money::kes($auction->bid_fee) }} · no-unique: {{ $auction->no_unique_bid_rule }} ({{ $auction->extension_minutes }} min)</div>
                            @if ($auction->is_featured)
                                <span class="mt-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-900 dark:bg-amber-900/40 dark:text-amber-100">Homepage hero</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium dark:bg-zinc-800">
                                {{ $auction->settled_at ? 'settled' : $auction->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs">
                            <div>Accepted: {{ $auction->accepted_bids_count }}</div>
                            <div>Pending: {{ $auction->pending_bids_count }}</div>
                            <div>Failed: {{ $auction->failed_bids_count }}</div>
                        </td>
                        <td class="px-4 py-3 text-xs">
                            {{ $auction->ends_at->timezone('Africa/Nairobi')->format('d M Y H:i') }} EAT
                        </td>
                        <td class="px-4 py-3 text-xs">
                            @if ($auction->result)
                                {{ Money::kes($auction->result->sale_price) }} · {{ $auction->result->buyer_display }}
                            @else
                                None
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex flex-wrap justify-end gap-2">
                                @if ($auction->is_featured)
                                    <flux:button size="sm" variant="outline" wire:click="toggleHomepageHero({{ $auction->id }})" wire:confirm="Remove this sale from the homepage hero?">
                                        Remove from homepage
                                    </flux:button>
                                @elseif ($auction->status === 'live' && $auction->ends_at->isFuture())
                                    <flux:button size="sm" variant="outline" wire:click="toggleHomepageHero({{ $auction->id }})" wire:confirm="Show this sale on the homepage hero? This replaces any current homepage sale.">
                                        Set as homepage
                                    </flux:button>
                                @endif
                                <flux:button size="sm" variant="outline" href="{{ route('admin.auction-bids', $auction) }}">
                                    Bids
                                </flux:button>
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
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div>
        {{ $auctions->links() }}
    </div>
</div>
