<?php

use App\Models\Order;
use App\Services\OrderService;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.app')]
#[Title('Admin: Order')]
class extends Component {
    public Order $order;

    public function mount(Order $order): void
    {
        $this->order = $order->load(['items.seller', 'shipments']);
    }

    public function with(): array
    {
        return [
            'shipment' => $this->order->activeShipment(),
        ];
    }

    public function markProcessing(OrderService $orders): void
    {
        $this->applyStatus(fn () => $orders->markProcessing($this->order), 'Order marked as processing.');
    }

    public function markDispatched(OrderService $orders): void
    {
        $this->applyStatus(fn () => $orders->markDispatched($this->order), 'Order fulfilment updated.');
    }

    public function markDelivered(OrderService $orders): void
    {
        $this->applyStatus(fn () => $orders->markDelivered($this->order), 'Order marked complete.');
    }

    public function cancelOrder(OrderService $orders): void
    {
        $this->applyStatus(
            fn () => $orders->cancelBeforeDispatch($this->order, 'Cancelled by UniqBid'),
            'Order cancelled. Stock has been restored.',
        );
    }

    private function applyStatus(callable $action, string $success): void
    {
        try {
            $this->order = $action()->load(['items.seller', 'shipments']);
            Flux::toast(variant: 'success', text: $success);
        } catch (ValidationException $exception) {
            Flux::toast(
                variant: 'danger',
                text: collect($exception->errors())->flatten()->first() ?? 'Unable to update this order.',
            );
        }
    }
}; ?>

@php
    $badgeColor = match ($order->status) {
        Order::STATUS_AWAITING_PAYMENT => 'zinc',
        Order::STATUS_PAID, Order::STATUS_PROCESSING => 'amber',
        Order::STATUS_DISPATCHED => 'sky',
        Order::STATUS_DELIVERED => 'green',
        Order::STATUS_CANCELLED => 'red',
        default => 'zinc',
    };
@endphp

<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('admin.orders') }}" class="text-sm text-zinc-500 hover:underline" wire:navigate>← All orders</a>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <flux:heading size="xl" class="font-mono">{{ $order->reference }}</flux:heading>
                <flux:badge :color="$badgeColor">{{ $order->statusLabel() }}</flux:badge>
            </div>
            <flux:text class="mt-1">
                Placed {{ $order->created_at?->timezone('Africa/Nairobi')->format('d M Y H:i') }} EAT
                · {{ $order->payment_provider }}
                @if ($order->payment_reference)
                    · {{ $order->payment_reference }}
                @endif
            </flux:text>
        </div>
        <a href="{{ route('orders.show', $order) }}" class="text-sm text-zinc-500 hover:underline" target="_blank" rel="noreferrer">Customer receipt</a>
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_16rem]">
        <div class="space-y-6">
            <div class="rounded-xl border border-zinc-200 bg-white p-4 sm:p-6 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="grid gap-4 sm:grid-cols-2 text-sm">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Customer</div>
                        <div class="mt-1 font-medium">{{ $order->customer_name }}</div>
                        <div class="text-zinc-500">{{ $order->customer_phone }}</div>
                        @if ($order->customer_email)
                            <div class="text-zinc-500">{{ $order->customer_email }}</div>
                        @endif
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Fulfilment</div>
                        <div class="mt-1 font-medium">{{ $order->deliveryMethodLabel() }}</div>
                        @if ($order->delivery_address)
                            <div class="text-zinc-500">{{ $order->delivery_address }}</div>
                        @endif
                        @if ($order->dispatched_at)
                            <div class="mt-2 text-xs text-zinc-500">Dispatched {{ $order->dispatched_at->timezone('Africa/Nairobi')->format('d M Y H:i') }} EAT</div>
                        @endif
                        @if ($order->delivered_at)
                            <div class="text-xs text-zinc-500">Delivered {{ $order->delivered_at->timezone('Africa/Nairobi')->format('d M Y H:i') }} EAT</div>
                        @endif
                    </div>
                </div>

                <div class="mt-6 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                    <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Items</div>
                    <div class="mt-3 space-y-2 text-sm">
                        @foreach ($order->items as $item)
                            <div class="flex justify-between gap-3" wire:key="admin-item-{{ $item->id }}">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <div class="font-medium">{{ $item->quantity }} × {{ $item->product_name }}</div>
                                        @if ($item->seller_user_id)
                                            <flux:badge size="sm" color="amber">Guest listing</flux:badge>
                                        @endif
                                    </div>
                                    <div class="text-xs text-zinc-500">{{ $item->category }}</div>
                                    @if ($item->seller_user_id)
                                        <div class="text-xs text-amber-700 dark:text-amber-300">
                                            Seller {{ $item->seller?->name ?? 'Guest' }}
                                            · payout {{ Money::kes($item->seller_payout_amount) }}
                                            ({{ $item->seller_payout_status }})
                                        </div>
                                    @endif
                                </div>
                                <div class="font-medium">{{ Money::kes($item->line_total) }}</div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4 space-y-1 border-t border-zinc-200 pt-3 text-sm dark:border-zinc-700">
                        <div class="flex justify-between text-zinc-500">
                            <span>Subtotal</span>
                            <span>{{ Money::kes($order->subtotal) }}</span>
                        </div>
                        <div class="flex justify-between text-zinc-500">
                            <span>Delivery</span>
                            <span>{{ $order->deliveryChargeLabel() }}</span>
                        </div>
                        <div class="flex justify-between text-base font-bold">
                            <span>Total</span>
                            <span>{{ Money::kes($order->total) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            @if ($order->status === Order::STATUS_CANCELLED)
                <flux:callout variant="danger" icon="x-circle">
                    Cancelled {{ $order->cancelled_at?->timezone('Africa/Nairobi')->format('d M Y H:i') }} EAT.
                    {{ $order->cancel_reason }}
                </flux:callout>
            @endif
        </div>

        <aside class="rounded-xl border border-zinc-200 bg-white p-4 sm:p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="text-sm font-semibold">Update status</h2>
            <div class="mt-4 space-y-2">
                @if ($order->canMarkProcessing())
                    <flux:button class="w-full" wire:click="markProcessing" wire:confirm="Mark this order as processing?">
                        Mark as processing
                    </flux:button>
                @endif
                @if ($order->canMarkDispatched())
                    <flux:button class="w-full" wire:click="markDispatched" wire:confirm="Update fulfilment for this order?">
                        {{ $order->dispatchButtonLabel() }}
                    </flux:button>
                @endif
                @if ($order->canMarkDelivered())
                    <flux:button class="w-full" wire:click="markDelivered" wire:confirm="Mark this order complete?">
                        {{ $order->deliverButtonLabel() }}
                    </flux:button>
                @endif
                @if ($order->canCancelBeforeDispatch())
                    <flux:button class="w-full" variant="danger" wire:click="cancelOrder" wire:confirm="Cancel this order? Stock will be restored.">
                        Cancel order
                    </flux:button>
                @endif
                @unless ($order->canMarkProcessing() || $order->canMarkDispatched() || $order->canMarkDelivered() || $order->canCancelBeforeDispatch())
                    <p class="text-sm text-zinc-500">No further fulfilment actions on this order.</p>
                @endunless
            </div>

            <div class="mt-6 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <h2 class="text-sm font-semibold">Logistics</h2>
                @if ($shipment)
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                        Shipment #{{ $shipment->id }} · {{ $shipment->statusLabel() }}
                    </p>
                    <p class="mt-1 text-xs text-zinc-500">{{ $shipment->methodLabel() }}</p>
                    <a href="{{ route('admin.logistics') }}" class="mt-3 inline-block text-sm text-accent hover:underline" wire:navigate>
                        Open Logistics
                    </a>
                @else
                    <p class="mt-2 text-sm text-zinc-500">
                        A shipment is created automatically when you start processing this order.
                    </p>
                @endif
            </div>
        </aside>
    </div>
</div>
