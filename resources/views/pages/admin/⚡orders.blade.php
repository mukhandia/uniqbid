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
#[Title('Admin: Shop Orders')]
class extends Component {
    use WithPagination;

    #[Url]
    public string $status = 'attention';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $delivery = '';

    #[Url(except: 'all')]
    public string $period = 'all';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedDelivery(): void
    {
        $this->resetPage();
    }

    public function updatedPeriod(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->status = 'attention';
        $this->delivery = '';
        $this->period = 'all';
        $this->resetPage();
    }

    public function with(): array
    {
        $query = Order::query()
            ->withCount('items')
            ->search($this->search)
            ->adminStatus($this->status)
            ->adminDelivery($this->delivery)
            ->placedInPeriod($this->period)
            ->latest();

        return [
            'orders' => $query->paginate(20),
            'attentionCount' => Order::query()->awaitingFulfilment()->count(),
            'hasActiveFilters' => $this->search !== ''
                || $this->status !== 'attention'
                || $this->delivery !== ''
                || $this->period !== 'all',
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
    <div>
        <flux:heading size="xl">Shop Orders</flux:heading>
        <flux:text class="mt-1">
            {{ $attentionCount }} shop orders waiting to pack or dispatch. Mark processing, dispatched, or delivered here.
        </flux:text>
    </div>

    <div class="flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-end">
        <div class="lg:w-64">
            <flux:field>
                <flux:label>Search</flux:label>
                <flux:input wire:model.live.debounce.400ms="search" placeholder="Reference, name, phone, M-Pesa" />
            </flux:field>
        </div>
        <div class="lg:w-52">
            <flux:field>
                <flux:label>Status</flux:label>
                <flux:select wire:model.live="status">
                    <flux:select.option value="attention">Needs packing</flux:select.option>
                    <flux:select.option value="all">All statuses</flux:select.option>
                    <flux:select.option value="paid">Paid: preparing</flux:select.option>
                    <flux:select.option value="processing">Processing</flux:select.option>
                    <flux:select.option value="dispatched">Dispatched</flux:select.option>
                    <flux:select.option value="delivered">Delivered</flux:select.option>
                    <flux:select.option value="cancelled">Cancelled</flux:select.option>
                </flux:select>
            </flux:field>
        </div>
        <div class="lg:w-56">
            <flux:field>
                <flux:label>Delivery</flux:label>
                <flux:select wire:model.live="delivery">
                    <flux:select.option value="">All delivery methods</flux:select.option>
                    @foreach (Order::deliveryMethods() as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>
        </div>
        <div class="lg:w-48">
            <flux:field>
                <flux:label>Placed</flux:label>
                <flux:select wire:model.live="period">
                    <flux:select.option value="all">Any time</flux:select.option>
                    <flux:select.option value="today">Today</flux:select.option>
                    <flux:select.option value="7d">Last 7 days</flux:select.option>
                    <flux:select.option value="30d">Last 30 days</flux:select.option>
                </flux:select>
            </flux:field>
        </div>
        @if ($hasActiveFilters)
            <flux:button type="button" variant="ghost" wire:click="clearFilters">Clear filters</flux:button>
        @endif
        <flux:text class="lg:ms-auto">
            {{ $orders->total() }} {{ $orders->total() === 1 ? 'order' : 'orders' }}
        </flux:text>
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
