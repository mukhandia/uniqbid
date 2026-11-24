<?php

use App\Models\Order;
use App\Models\SiteSetting;
use App\Services\OrderService;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.site')]
#[Title('Order receipt: UniqBid Shop')]
class extends Component {
    public Order $order;

    public function mount(Order $order): void
    {
        $this->order = $order->load('items');
    }

    public function cancelOrder(OrderService $orders): void
    {
        try {
            $this->order = $orders->cancelBeforeDispatch($this->order);
            Flux::toast(variant: 'success', text: 'Order cancelled. Stock has been restored.');
        } catch (ValidationException $exception) {
            Flux::toast(
                variant: 'danger',
                text: collect($exception->errors())->flatten()->first() ?? 'Unable to cancel this order.',
            );
        }
    }

    public function with(): array
    {
        return [
            'supportEmail' => SiteSetting::current()->email,
        ];
    }
}; ?>

<div class="mx-auto max-w-3xl px-4 py-10">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <div class="text-xs text-muted-foreground">
                <a href="{{ route('shop.index') }}" class="hover:underline" wire:navigate>Shop</a>
                /
                <a href="{{ route('orders.track') }}" class="hover:underline" wire:navigate>Track order</a>
            </div>
            <h1 class="mt-2 text-3xl font-bold text-navy">Order {{ $order->reference }}</h1>
            <p class="mt-1 text-sm text-muted-foreground">{{ $order->statusLabel() }} · {{ $order->created_at?->timezone(config('app.timezone'))->format('j M Y, H:i') }}</p>
        </div>
        <flux:badge :color="match ($order->status) {
            'paid', 'processing' => 'amber',
            'dispatched' => 'sky',
            'delivered' => 'green',
            'cancelled' => 'red',
            default => 'zinc',
        }">
            {{ $order->statusLabel() }}
        </flux:badge>
    </div>

    <div class="mt-6 space-y-4 rounded-2xl border border-border bg-white p-6">
        <div class="grid gap-4 sm:grid-cols-2 text-sm">
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Customer</div>
                <div class="mt-1 font-medium">{{ $order->customer_name }}</div>
                <div class="text-muted-foreground">{{ $order->customer_phone }}</div>
                @if ($order->customer_email)
                    <div class="text-muted-foreground">{{ $order->customer_email }}</div>
                @endif
            </div>
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Fulfilment</div>
                <div class="mt-1 font-medium">{{ $order->deliveryMethodLabel() }}</div>
                @if ($order->delivery_address)
                    <div class="text-muted-foreground">{{ $order->delivery_address }}</div>
                @endif
                @if ($order->payment_reference)
                    <div class="mt-2 text-xs text-muted-foreground">Payment ref: <span class="font-mono">{{ $order->payment_reference }}</span></div>
                @endif
            </div>
        </div>

        <div class="border-t border-border pt-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Items</div>
            <div class="mt-3 space-y-2 text-sm">
                @foreach ($order->items as $item)
                    <div class="flex justify-between gap-3" wire:key="item-{{ $item->id }}">
                        <div>
                            <div class="font-medium">{{ $item->quantity }} × {{ $item->product_name }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ $item->category }}
                                @unless ($item->is_returnable)
                                    · Non-returnable
                                @endunless
                            </div>
                        </div>
                        <div class="font-medium">{{ Money::kes($item->line_total) }}</div>
                    </div>
                @endforeach
            </div>
            <div class="mt-4 space-y-1 border-t border-border pt-3 text-sm">
                <div class="flex justify-between text-muted-foreground">
                    <span>Subtotal</span>
                    <span>{{ Money::kes($order->subtotal) }}</span>
                </div>
                <div class="flex justify-between text-muted-foreground">
                    <span>Delivery</span>
                    <span>{{ $order->deliveryChargeLabel() }}</span>
                </div>
                @if ($order->delivery_method === Order::DELIVERY_UPCOUNTRY)
                    <p class="text-xs text-muted-foreground">
                        Delivery fee charges apply. The amount is confirmed when you collect or when the item is delivered.
                    </p>
                @endif
                <div class="flex justify-between text-base font-bold">
                    <span>Total</span>
                    <span class="text-navy">{{ Money::kes($order->total) }}</span>
                </div>
            </div>
        </div>

        @if ($order->canCancelBeforeDispatch())
            <div class="rounded-lg border border-border bg-surface p-4 text-sm">
                <p class="text-zinc-800">
                    You can cancel this order free of charge before dispatch (Cancellation Policy 5.3). After dispatch, return rights apply under the Refund Policy.
                </p>
                <flux:button
                    class="mt-3"
                    variant="outline"
                    wire:click="cancelOrder"
                    wire:confirm="Cancel this order before dispatch? Stock will be restored."
                >
                    Cancel order
                </flux:button>
            </div>
        @elseif ($order->status === 'cancelled')
            <flux:callout variant="danger" icon="x-circle">
                Cancelled {{ $order->cancelled_at?->timezone(config('app.timezone'))->format('j M Y, H:i') }}.
                {{ $order->cancel_reason }}
            </flux:callout>
        @elseif ($order->status === 'delivered')
            <p class="text-sm text-muted-foreground">
                Eligible unused items may be returned within 7 days of delivery with proof of purchase.
                Contact <a href="mailto:{{ $supportEmail }}" class="font-medium text-navy underline">{{ $supportEmail }}</a>.
            </p>
        @endif
    </div>

    <div class="mt-6 flex flex-wrap gap-2">
        <flux:button href="{{ route('shop.index') }}" class="bg-accent! text-accent-foreground!">Back to shop</flux:button>
        @auth
            @unless (Auth::user()->is_admin)
                <flux:button href="{{ route('account') }}" variant="outline">My account</flux:button>
            @endunless
        @endauth
    </div>
</div>
