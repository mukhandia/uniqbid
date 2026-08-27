<?php

use App\Models\Order;
use App\Support\Money;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Layout('layouts.app')]
#[Title('Admin: Orders')]
class extends Component {
    use WithPagination;

    #[Url]
    public string $status = 'attention';

    public string $search = '';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $query = Order::query()
            ->withCount('items')
            ->latest();

        if ($this->status === 'attention') {
            $query->awaitingFulfilment();
        } elseif ($this->status !== 'all') {
            $query->where('status', $this->status);
        }

        $term = trim($this->search);

        if ($term !== '') {
            $query->where(function ($builder) use ($term): void {
                $builder
                    ->where('reference', 'like', '%'.$term.'%')
                    ->orWhere('customer_name', 'like', '%'.$term.'%')
                    ->orWhere('customer_phone', 'like', '%'.$term.'%')
                    ->orWhere('customer_email', 'like', '%'.$term.'%');
            });
        }

        return [
            'orders' => $query->paginate(20),
            'attentionCount' => Order::query()->awaitingFulfilment()->count(),
        ];
    }
}; ?>

@php
    $statusColor = fn (string $status): string => match ($status) {
        Order::STATUS_PAID, Order::STATUS_PROCESSING => 'amber',
        Order::STATUS_DISPATCHED => 'sky',
        Order::STATUS_DELIVERED => 'green',
        Order::STATUS_CANCELLED => 'red',
        default => 'zinc',
    };
@endphp

<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">Orders</flux:heading>
            <flux:text class="mt-1">
                {{ $attentionCount }} shop orders waiting to pack or dispatch. Mark processing, dispatched, or delivered here.
            </flux:text>
        </div>
        <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
            <div class="sm:w-56">
                <flux:input wire:model.live.debounce.400ms="search" placeholder="Search reference, name, phone" />
            </div>
            <div class="sm:w-52">
                <flux:select wire:model.live="status">
                    <flux:select.option value="attention">Needs packing</flux:select.option>
                    <flux:select.option value="all">All statuses</flux:select.option>
                    <flux:select.option value="paid">Paid: preparing</flux:select.option>
                    <flux:select.option value="processing">Processing</flux:select.option>
                    <flux:select.option value="dispatched">Dispatched</flux:select.option>
                    <flux:select.option value="delivered">Delivered</flux:select.option>
                    <flux:select.option value="cancelled">Cancelled</flux:select.option>
                </flux:select>
            </div>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
            <thead class="bg-zinc-50 text-left dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 font-medium">Order</th>
                    <th class="px-4 py-3 font-medium">Customer</th>
                    <th class="px-4 py-3 font-medium">Fulfilment</th>
                    <th class="px-4 py-3 font-medium">Total</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($orders as $order)
                    <tr wire:key="order-{{ $order->id }}">
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.orders.show', $order) }}" class="font-mono text-navy hover:underline dark:text-white" wire:navigate>
                                {{ $order->reference }}
                            </a>
                            <div class="text-xs text-zinc-500">{{ $order->created_at?->timezone('Africa/Nairobi')->format('d M Y H:i') }} EAT · {{ $order->items_count }} items</div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ $order->customer_name }}</div>
                            <div class="text-xs text-zinc-500">{{ $order->customer_phone }}</div>
                        </td>
                        <td class="px-4 py-3 text-xs">
                            <div>{{ $order->deliveryMethodLabel() }}</div>
                            @if ($order->delivery_address)
                                <div class="text-zinc-500">{{ $order->delivery_address }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-medium">{{ Money::kes($order->total) }}</td>
                        <td class="px-4 py-3">
                            <flux:badge size="sm" :color="$statusColor($order->status)">
                                {{ $order->statusLabel() }}
                            </flux:badge>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-zinc-500">No orders in this view.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $orders->links() }}</div>
</div>
