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
#[Title('Admin — Sales')]
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
    <div>
        <flux:heading size="xl">Sales & bidding</flux:heading>
        <flux:text class="mt-1">Review live and ended sales, settlement status, and bid activity.</flux:text>
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
                            <div class="text-xs text-zinc-500">{{ $auction->category }} · fee {{ Money::kes($auction->bid_fee) }}</div>
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
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-2">
                                <flux:button size="sm" variant="outline" href="{{ route('admin.auction-bids', $auction) }}">
                                    Bids
                                </flux:button>
                                @if (! $auction->settled_at && $auction->ends_at->isPast())
                                    <flux:button size="sm" wire:click="settle({{ $auction->id }})" wire:confirm="Settle this sale now?">
                                        Settle
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
