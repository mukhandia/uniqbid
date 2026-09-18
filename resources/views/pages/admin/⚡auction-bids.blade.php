<?php

use App\Models\Auction;
use App\Models\AuctionResult;
use App\Services\AuctionSettlementService;
use App\Services\LogisticsService;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new
#[Layout('layouts.app')]
#[Title('Admin: Sale activity')]
class extends Component {
    use WithFileUploads;
    use WithPagination;

    public Auction $auction;

    public $buyerImageUpload = null;

    public function mount(Auction $auction): void
    {
        $this->auction = $auction;
    }

    public function with(): array
    {
        $this->auction->refresh();
        $this->auction->load(['result.auctionBid.bidder']);

        $snapshot = app(AuctionSettlementService::class)->uniquenessSnapshot($this->auction);

        return [
            'bids' => $this->auction->bids()
                ->with(['bidder', 'paymentAttempts'])
                ->latest('id')
                ->paginate(30),
            'snapshot' => $snapshot,
            'leadingBid' => $snapshot['leading_bid'],
            'amountCounts' => $snapshot['amount_counts'],
            'successfulBid' => $this->auction->result?->auctionBid,
            'shipment' => $this->auction->result?->activeShipment(),
        ];
    }

    public function settle(AuctionSettlementService $settlement): void
    {
        try {
            $result = $settlement->settle($this->auction, auth()->user());
        } catch (ValidationException $exception) {
            Flux::toast(
                variant: 'danger',
                text: $exception->errors()['auction'][0] ?? 'This sale cannot be settled yet.',
            );

            return;
        }

        $this->auction = $result['auction']->load(['result.auctionBid.bidder']);

        $message = $result['successful_bid']
            ? 'Sale settled. Successful bid: '.Money::kes($result['successful_bid']->amount)
            : 'Sale settled with no unique accepted bid.';

        Flux::toast(variant: 'success', text: $message);
    }

    public function markSalePaid(): void
    {
        $this->updateSettlement([
            'sale_payment_status' => AuctionResult::PAYMENT_PAID,
            'sale_payment_paid_at' => $this->auction->result?->sale_payment_paid_at ?? now(),
        ], 'Successful-buy payment recorded.');
    }

    public function markCollectionReady(): void
    {
        $this->updateSettlement([
            'fulfilment_status' => AuctionResult::FULFILMENT_COLLECTION_READY,
        ], 'Item is ready for collection.');
    }

    public function markCollected(): void
    {
        if (! $this->auction->result?->isSalePaid()) {
            Flux::toast(variant: 'danger', text: 'Record the successful-buy payment before marking the item picked up.');

            return;
        }

        $this->updateSettlement([
            'fulfilment_status' => AuctionResult::FULFILMENT_COLLECTED,
        ], 'Item marked as picked up.');
    }

    public function markDispatched(): void
    {
        if (! $this->auction->result?->isSalePaid()) {
            Flux::toast(variant: 'danger', text: 'Record the successful-buy payment before dispatching.');

            return;
        }

        $this->updateSettlement([
            'fulfilment_status' => AuctionResult::FULFILMENT_DISPATCHED,
        ], 'Item marked as dispatched.');
    }

    public function markDelivered(): void
    {
        if (! $this->auction->result?->isSalePaid()) {
            Flux::toast(variant: 'danger', text: 'Record the successful-buy payment before marking delivery.');

            return;
        }

        $this->updateSettlement([
            'fulfilment_status' => AuctionResult::FULFILMENT_DELIVERED,
        ], 'Item marked as delivered.');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function updateSettlement(array $attributes, string $success): void
    {
        $result = AuctionResult::query()->where('auction_id', $this->auction->id)->first();

        if (! $result) {
            Flux::toast(variant: 'danger', text: 'Settle this sale before updating pickup or payment.');

            return;
        }

        $result->update($attributes);
        $this->auction->load(['result.auctionBid.bidder']);

        if (array_key_exists('fulfilment_status', $attributes)) {
            app(LogisticsService::class)->syncFromAuctionResult($result->fresh(['auctionBid.bidder']));
        }

        Flux::toast(variant: 'success', text: $success);
    }

    public function saveBuyerImage(): void
    {
        $result = AuctionResult::query()->where('auction_id', $this->auction->id)->first();

        if ($result === null) {
            Flux::toast(variant: 'danger', text: 'Settle this sale before adding a successful-buyer photo.');

            return;
        }

        $this->validate([
            'buyerImageUpload' => ['required', 'image', 'max:4096'],
        ]);

        if (! $this->buyerImageUpload instanceof TemporaryUploadedFile) {
            Flux::toast(variant: 'danger', text: 'Choose a photo to upload.');

            return;
        }

        if (filled($result->buyer_image) && ! str_starts_with((string) $result->buyer_image, 'http') && ! str_starts_with((string) $result->buyer_image, '/')) {
            Storage::disk('public')->delete($result->buyer_image);
        }

        $stored = $this->buyerImageUpload->store('buyers', 'public');
        $result->update(['buyer_image' => $stored]);
        $this->reset('buyerImageUpload');
        $this->auction->load(['result.auctionBid.bidder']);

        Flux::toast(variant: 'success', text: 'Successful-buyer photo saved for past sales.');
    }

    public function clearBuyerImage(): void
    {
        $result = AuctionResult::query()->where('auction_id', $this->auction->id)->first();

        if ($result === null) {
            return;
        }

        if (filled($result->buyer_image) && ! str_starts_with((string) $result->buyer_image, 'http') && ! str_starts_with((string) $result->buyer_image, '/')) {
            Storage::disk('public')->delete($result->buyer_image);
        }

        $result->update(['buyer_image' => null]);
        $this->auction->load(['result.auctionBid.bidder']);

        Flux::toast(variant: 'success', text: 'Successful-buyer photo removed. Past sales will show an avatar.');
    }
}; ?>

@php
    $result = $auction->result;
    $isLiveOpen = $auction->isOpenLive();
    $needsSettle = $auction->needsSettle();
    $leadingBidder = $leadingBid?->bidder;
    $successfulBidder = $successfulBid?->bidder;
@endphp

<div class="space-y-6" wire:poll.180s.visible>
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('admin.auctions') }}" class="text-sm text-zinc-500 hover:underline" wire:navigate>← All sales</a>
            <flux:heading size="xl" class="mt-2">{{ $auction->name }}</flux:heading>
            <flux:text class="mt-1">
                Incoming bids refresh every 3 minutes while this page is open.
                @if ($isLiveOpen)
                    Closes {{ $auction->ends_at->timezone('Africa/Nairobi')->format('d M Y, H:i') }} EAT
                    ({{ $auction->ends_at->diffForHumans() }}).
                @elseif ($needsSettle)
                    Ended {{ $auction->ends_at->timezone('Africa/Nairobi')->format('d M Y, H:i') }} EAT. Settle to lock the successful bid.
                @elseif ($result)
                    Successful buy {{ Money::kes($result->sale_price) }} · public listing {{ $result->buyer_display }}.
                @endif
            </flux:text>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($isLiveOpen)
                <flux:badge color="green">Live</flux:badge>
            @elseif ($needsSettle)
                <flux:badge color="amber">Needs settle</flux:badge>
            @elseif ($auction->status === 'voided')
                <flux:badge color="red">Voided</flux:badge>
            @elseif ($auction->settled_at)
                <flux:badge color="sky">Settled</flux:badge>
            @endif
            <flux:badge color="zinc">{{ $snapshot['accepted_count'] }} fee paid</flux:badge>
            <flux:badge color="zinc">{{ $snapshot['unique_count'] }} unique amounts</flux:badge>
        </div>
    </div>

    @if ($needsSettle)
        <flux:callout icon="clock" color="amber">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="font-medium">This sale has ended and still needs settling.</div>
                    <p class="mt-1 text-sm">
                        @if ($leadingBid && $leadingBidder)
                            Lowest unique accepted bid is {{ Money::kes($leadingBid->amount) }} from {{ $leadingBidder->name }}.
                        @else
                            There is no unique accepted bid yet. Settling will close the sale without a successful buyer.
                        @endif
                    </p>
                </div>
                <flux:button wire:click="settle" wire:confirm="Settle this sale now?">Settle sale</flux:button>
            </div>
        </flux:callout>
    @endif

    @if ($result && $successfulBid)
        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <flux:heading size="lg">Successful bidder</flux:heading>
                    <flux:text class="mt-1">
                        Pay the sale price by
                        {{ $result->fulfilment_due_at?->timezone('Africa/Nairobi')->format('d M Y H:i') ?? 'the agreed deadline' }}
                        EAT, then mark whether the product was picked up.
                    </flux:text>
                </div>
                <div class="flex flex-wrap gap-2">
                    <flux:badge :color="$result->isSalePaid() ? 'green' : 'amber'">{{ $result->salePaymentLabel() }}</flux:badge>
                    <flux:badge :color="$result->isPickedUp() ? 'green' : 'zinc'">{{ $result->fulfilmentLabel() }}</flux:badge>
                </div>
            </div>

            <div class="mt-5 grid gap-6 lg:grid-cols-[minmax(0,1fr)_16rem]">
                <div class="grid gap-4 sm:grid-cols-2 text-sm">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Bidder</div>
                        <div class="mt-1 font-medium">{{ $successfulBidder?->name ?? $result->buyer_display }}</div>
                        @if ($successfulBidder)
                            <div class="text-zinc-500">{{ $successfulBidder->phone }}</div>
                            <div class="text-zinc-500">{{ $successfulBidder->email }}</div>
                        @endif
                        <div class="mt-2 text-xs text-zinc-500">Public result listing: {{ $result->buyer_display }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Successful amount</div>
                        <div class="mt-1 text-lg font-semibold tabular-nums">{{ Money::kes($result->sale_price) }}</div>
                        <div class="text-xs text-zinc-500">Ref {{ $successfulBid->reference }}</div>
                    </div>
                    <div class="sm:col-span-2">
                        <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Past-sales buyer photo</div>
                        <p class="mt-1 text-xs text-zinc-500">Optional. Shown on Past Sales and homepage recent sales. If empty, the public site shows an avatar.</p>
                        <div class="mt-3 flex flex-wrap items-center gap-4">
                            <div class="size-16 overflow-hidden rounded-full border border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800">
                                @if ($result->buyerImageUrl())
                                    <img src="{{ $result->buyerImageUrl() }}" alt="{{ $result->buyer_display }}" class="h-full w-full object-cover">
                                @else
                                    <div class="grid h-full w-full place-items-center text-zinc-400">
                                        <flux:icon.user class="size-7" />
                                    </div>
                                @endif
                            </div>
                            <div class="min-w-[16rem] flex-1 space-y-2">
                                <input type="file" wire:model="buyerImageUpload" accept="image/*" class="block w-full text-sm text-zinc-600 file:mr-3 file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-1.5 file:text-sm file:font-medium dark:text-zinc-300 dark:file:bg-zinc-800" />
                                <flux:error name="buyerImageUpload" />
                                <div class="flex flex-wrap gap-2">
                                    <flux:button size="sm" wire:click="saveBuyerImage" wire:loading.attr="disabled">
                                        Save photo
                                    </flux:button>
                                    @if ($result->buyerImageUrl())
                                        <flux:button size="sm" variant="ghost" wire:click="clearBuyerImage" wire:confirm="Remove the buyer photo? Past sales will show an avatar.">
                                            Remove
                                        </flux:button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <aside class="space-y-2">
                    <h2 class="text-sm font-semibold">Process this sale</h2>
                    @if ($result->canMarkPaid())
                        <flux:button class="w-full" wire:click="markSalePaid" wire:confirm="Record that the successful buyer has paid the sale price?">
                            Mark sale paid
                        </flux:button>
                    @endif
                    @if ($result->canMarkCollectionReady())
                        <flux:button class="w-full" variant="outline" wire:click="markCollectionReady">
                            Ready for collection
                        </flux:button>
                    @endif
                    @if ($result->canMarkCollected())
                        <flux:button class="w-full" wire:click="markCollected" wire:confirm="Mark this product as picked up?">
                            Mark picked up
                        </flux:button>
                    @endif
                    @if ($result->canMarkDispatched())
                        <flux:button class="w-full" variant="outline" wire:click="markDispatched" wire:confirm="Mark this product as dispatched for delivery?">
                            Mark dispatched
                        </flux:button>
                    @endif
                    @if ($result->canMarkDelivered())
                        <flux:button class="w-full" wire:click="markDelivered" wire:confirm="Mark this product as delivered?">
                            Mark delivered
                        </flux:button>
                    @endif
                    @unless ($result->canMarkPaid() || $result->canMarkCollectionReady() || $result->canMarkCollected() || $result->canMarkDispatched() || $result->canMarkDelivered())
                        <p class="text-sm text-zinc-500">This successful buy is complete.</p>
                    @endunless

                    <div class="mt-4 border-t border-zinc-200 pt-3 dark:border-zinc-700">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Logistics</h3>
                        @if ($shipment)
                            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                                Shipment #{{ $shipment->id }} · {{ $shipment->statusLabel() }}
                            </p>
                            <a href="{{ route('admin.logistics') }}" class="mt-2 inline-block text-sm text-accent hover:underline" wire:navigate>
                                Open Logistics
                            </a>
                        @else
                            <p class="mt-2 text-xs text-zinc-500">
                                A shipment is created when you mark ready for collection or dispatch. Redeem sets it to Redeemed.
                            </p>
                        @endif
                    </div>
                </aside>
            </div>
        </section>
    @elseif ($result)
        <flux:callout icon="information-circle">
            This sale was settled without a unique accepted bid. Re-offer or re-run it from the sales list if you still want to sell the item.
        </flux:callout>
    @elseif ($isLiveOpen)
        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg">Current lowest unique</flux:heading>
            @if ($leadingBid && $leadingBidder)
                <p class="mt-2 text-sm">
                    <span class="font-semibold">{{ $leadingBidder->name }}</span>
                    holds {{ Money::kes($leadingBid->amount) }}.
                    This can still change until the sale is settled.
                </p>
                <p class="mt-1 text-xs text-zinc-500">{{ $leadingBidder->phone }} · {{ $leadingBidder->email }}</p>
            @else
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                    No unique accepted bid yet. Tied amounts will not win at close.
                </p>
            @endif
        </section>
    @endif

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
            <h2 class="font-medium">Incoming bids</h2>
            <div class="text-xs text-zinc-500">Newest first · poll every 3 minutes</div>
        </div>
        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
            <thead class="bg-zinc-50 text-left dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 font-medium">When</th>
                    <th class="px-4 py-3 font-medium">Bidder</th>
                    <th class="px-4 py-3 font-medium">Bid</th>
                    <th class="px-4 py-3 font-medium">Uniqueness</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium">Payment</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($bids as $bid)
                    @php
                        $latestPayment = $bid->paymentAttempts->sortByDesc('id')->first();
                        $isLeading = $leadingBid && $bid->id === $leadingBid->id;
                        $isSuccessful = $successfulBid && $bid->id === $successfulBid->id;
                        $acceptedCount = $amountCounts[$bid->amount] ?? 0;
                        $uniquenessLabel = match (true) {
                            $bid->status !== 'accepted' => 'Fee not paid',
                            $acceptedCount > 1 => 'Tied ×'.$acceptedCount,
                            $isLeading && ! $result => 'Lowest unique',
                            $isSuccessful => 'Successful bid',
                            $acceptedCount === 1 => 'Unique',
                            default => '—',
                        };
                    @endphp
                    <tr
                        wire:key="admin-bid-{{ $bid->id }}"
                        @class([
                            'bg-emerald-50 dark:bg-emerald-500/10' => $isSuccessful || ($isLeading && ! $result),
                        ])
                    >
                        <td class="whitespace-nowrap px-4 py-3 text-xs text-zinc-500">
                            {{ $bid->created_at?->timezone('Africa/Nairobi')->format('d M Y, H:i') }} EAT
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ $bid->bidder->name }}</div>
                            <div class="text-xs text-zinc-500">{{ $bid->bidder->phone }} · {{ $bid->bidder->email }}</div>
                            <div class="text-xs text-zinc-500">Ref {{ $bid->reference }} · Marketing {{ $bid->bidder->marketing_consent ? 'Yes' : 'No' }}</div>
                        </td>
                        <td class="px-4 py-3 font-semibold tabular-nums">{{ Money::kes($bid->amount) }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium dark:bg-zinc-800">
                                {{ $uniquenessLabel }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium dark:bg-zinc-800">
                                {{ $bid->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs">
                            @if ($latestPayment)
                                <div>{{ $latestPayment->status }} · {{ Money::kes($latestPayment->amount) }}</div>
                                <div class="text-zinc-500">{{ $latestPayment->reference }}</div>
                                @if ($latestPayment->failure_reason)
                                    <div class="text-red-600">{{ $latestPayment->failure_reason }}</div>
                                @endif
                            @else
                                None
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-zinc-500">No bids recorded yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $bids->links() }}
    </div>
</div>
