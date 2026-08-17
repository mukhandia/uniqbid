<?php

use App\Models\Auction;
use App\Models\AuctionResult;
use App\Support\Money;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.app')]
#[Title('Admin — Sale bids')]
class extends Component {
    public Auction $auction;

    public string $salePaymentStatus = 'awaiting_payment';

    public string $fulfilmentStatus = 'pending';

    public function mount(Auction $auction): void
    {
        $this->auction = $auction->load('result');
        $this->salePaymentStatus = $this->auction->result?->sale_payment_status ?? 'awaiting_payment';
        $this->fulfilmentStatus = $this->auction->result?->fulfilment_status ?? 'pending';
    }

    public function updateFulfilment(): void
    {
        $validated = $this->validate([
            'salePaymentStatus' => ['required', 'in:awaiting_payment,paid'],
            'fulfilmentStatus' => ['required', 'in:pending,collection_ready,dispatched,collected,delivered'],
        ]);

        $result = AuctionResult::query()->where('auction_id', $this->auction->id)->firstOrFail();
        $result->update([
            'sale_payment_status' => $validated['salePaymentStatus'],
            'sale_payment_paid_at' => $validated['salePaymentStatus'] === 'paid'
                ? ($result->sale_payment_paid_at ?? now())
                : null,
            'fulfilment_status' => $validated['fulfilmentStatus'],
        ]);

        Flux::toast(variant: 'success', text: 'Successful-buy settlement updated.');
    }
}; ?>

@php
    $bids = $auction->bids()
        ->with(['bidder', 'paymentAttempts'])
        ->latest()
        ->paginate(30);
@endphp

<div class="space-y-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <a href="{{ route('admin.auctions') }}" class="text-sm text-zinc-500 hover:underline">← All sales</a>
            <flux:heading size="xl" class="mt-2">{{ $auction->name }}</flux:heading>
            <flux:text class="mt-1">
                Bid and payment records for this sale.
                @if ($auction->result)
                    Successful buy: {{ Money::kes($auction->result->sale_price) }} ({{ $auction->result->buyer_display }})
                @endif
            </flux:text>
        </div>
    </div>

    @if ($auction->result)
        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg">Successful-buy settlement</flux:heading>
            <flux:text class="mt-1">
                {{ Money::kes($auction->result->sale_price) }} due by
                {{ $auction->result->fulfilment_due_at?->timezone('Africa/Nairobi')->format('d M Y H:i') ?? 'the agreed deadline' }} EAT.
            </flux:text>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="salePaymentStatus" label="Sale payment">
                    <flux:select.option value="awaiting_payment">Awaiting payment</flux:select.option>
                    <flux:select.option value="paid">Paid</flux:select.option>
                </flux:select>
                <flux:select wire:model="fulfilmentStatus" label="Collection / delivery">
                    <flux:select.option value="pending">Pending</flux:select.option>
                    <flux:select.option value="collection_ready">Collection ready</flux:select.option>
                    <flux:select.option value="dispatched">Dispatched</flux:select.option>
                    <flux:select.option value="collected">Collected</flux:select.option>
                    <flux:select.option value="delivered">Delivered</flux:select.option>
                </flux:select>
            </div>
            <flux:button class="mt-4" wire:click="updateFulfilment">
                Save settlement status
            </flux:button>
        </section>
    @endif

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
            <thead class="bg-zinc-50 text-left dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 font-medium">Bidder</th>
                    <th class="px-4 py-3 font-medium">Bid</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium">Payment</th>
                    <th class="px-4 py-3 font-medium">Marketing</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($bids as $bid)
                    @php $latestPayment = $bid->paymentAttempts->sortByDesc('id')->first(); @endphp
                    <tr wire:key="admin-bid-{{ $bid->id }}">
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ $bid->bidder->name }}</div>
                            <div class="text-xs text-zinc-500">{{ $bid->bidder->phone }} · {{ $bid->bidder->email }}</div>
                            <div class="text-xs text-zinc-500">Ref {{ $bid->reference }}</div>
                        </td>
                        <td class="px-4 py-3 font-semibold">{{ Money::kes($bid->amount) }}</td>
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
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs">
                            {{ $bid->bidder->marketing_consent ? 'Yes' : 'No' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-zinc-500">No bids recorded yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $bids->links() }}
    </div>
</div>
