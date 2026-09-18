<?php

use App\Services\CartService;
use App\Support\Money;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.site')]
#[Title('Your Cart: UniqBid Shop')]
class extends Component {
    public function increment(int $productId, CartService $cart): void
    {
        $current = $cart->quantityOf($productId);
        $cart->update($productId, $current + 1);

        if ($cart->quantityOf($productId) === $current) {
            Flux::toast(variant: 'warning', text: 'No more stock available for this item.');
        }

        $this->dispatch('cart-updated');
    }

    public function decrement(int $productId, CartService $cart): void
    {
        $cart->update($productId, $cart->quantityOf($productId) - 1);
        $this->dispatch('cart-updated');
    }

    public function remove(int $productId, CartService $cart): void
    {
        $cart->remove($productId);
        $this->dispatch('cart-updated');
    }

    public function with(): array
    {
        $cart = app(CartService::class);

        return [
            'lines' => $cart->lines(),
            'subtotal' => $cart->subtotal(),
        ];
    }
}; ?>

<div>
    @if ($lines->isEmpty())
        <div class="mx-auto max-w-xl px-4 py-20 text-center">
            <div class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-surface">
                <flux:icon.shopping-bag class="size-8 text-muted-foreground" />
            </div>
            <h1 class="mt-4 text-2xl font-bold text-navy">Your cart is empty</h1>
            <p class="mt-2 text-sm text-muted-foreground">Browse the shop or explore live sales.</p>
            <div class="mt-6 flex justify-center gap-2">
                <flux:button href="{{ route('shop.index') }}" class="bg-accent! text-accent-foreground!">Go to shop</flux:button>
                <flux:button href="{{ route('auctions.index') }}" variant="outline">Live auctions</flux:button>
            </div>
        </div>
    @else
        <div class="mx-auto max-w-5xl px-4 py-10">
            <h1 class="text-3xl font-bold text-navy">Your cart</h1>
            <div class="mt-6 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(17.5rem,22rem)]">
                <div class="min-w-0 rounded-2xl border border-border bg-white">
                    @foreach ($lines as $index => $line)
                        @php
                            $product = $line['product'];
                            $atMax = $line['qty'] >= $product->availableStock();
                        @endphp
                        <div wire:key="cart-line-{{ $product->id }}" @class(['flex gap-4 p-4', 'border-t border-border' => $index > 0])>
                            <div class="h-20 w-20 shrink-0 overflow-hidden rounded-md bg-muted">
                                <img src="{{ $product->image }}" alt="{{ $product->name }}" class="h-full w-full object-cover">
                            </div>
                            <div class="flex flex-1 flex-col">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="text-xs text-muted-foreground">{{ $product->category }}</div>
                                        <div class="font-semibold">{{ $product->name }}</div>
                                    </div>
                                    <button
                                        type="button"
                                        wire:click="remove({{ $product->id }})"
                                        class="rounded-md p-1.5 text-muted-foreground hover:bg-muted hover:text-red-600"
                                        aria-label="Remove {{ $product->name }}"
                                    >
                                        <flux:icon.trash class="pointer-events-none size-4" />
                                    </button>
                                </div>
                                <div class="mt-auto flex items-center justify-between">
                                    <div class="inline-flex items-center rounded-md border border-border">
                                        <button
                                            type="button"
                                            wire:click="decrement({{ $product->id }})"
                                            class="p-1.5 hover:bg-muted"
                                            aria-label="Decrease quantity"
                                        >
                                            <flux:icon.minus class="pointer-events-none size-3.5" />
                                        </button>
                                        <span class="w-8 text-center text-sm font-semibold">{{ $line['qty'] }}</span>
                                        <button
                                            type="button"
                                            wire:click="increment({{ $product->id }})"
                                            class="p-1.5 hover:bg-muted disabled:cursor-not-allowed disabled:opacity-40"
                                            @disabled($atMax)
                                            aria-label="Increase quantity"
                                        >
                                            <flux:icon.plus class="pointer-events-none size-3.5" />
                                        </button>
                                    </div>
                                    <div class="text-sm font-bold text-navy">{{ Money::kes($line['line_total']) }}</div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <aside class="h-fit min-w-0 rounded-2xl border border-border bg-white p-4 shadow-sm sm:p-6 lg:sticky lg:top-32">
                    <h2 class="text-lg font-semibold text-navy">Order summary</h2>

                    <dl class="mt-5 space-y-4">
                        <div class="flex items-baseline justify-between gap-4">
                            <dt class="text-sm text-muted-foreground">Subtotal</dt>
                            <dd class="shrink-0 text-sm font-semibold tabular-nums text-zinc-900">{{ Money::kes($subtotal) }}</dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-zinc-900">Delivery</dt>
                            <dd class="mt-2 space-y-2 rounded-xl bg-surface px-3 py-3">
                                <div class="flex items-start justify-between gap-3 text-sm">
                                    <span class="min-w-0 leading-snug text-muted-foreground">Nairobi delivery and Westlands collection</span>
                                    <span class="shrink-0 font-medium text-zinc-900">Free</span>
                                </div>
                                <div class="flex items-start justify-between gap-3 text-sm">
                                    <span class="min-w-0 leading-snug text-muted-foreground">Outside Nairobi</span>
                                    <span class="shrink-0 font-medium text-zinc-900">Charges apply</span>
                                </div>
                                <p class="border-t border-border pt-2 text-xs leading-relaxed text-muted-foreground">
                                    Delivery charges apply outside Nairobi. The amount is confirmed when you collect or when the item is delivered.
                                </p>
                            </dd>
                        </div>
                    </dl>

                    <div class="mt-5 flex items-baseline justify-between gap-4 border-t border-border pt-4">
                        <span class="text-sm font-medium text-muted-foreground">Total</span>
                        <span class="text-xl font-bold tabular-nums text-navy">{{ Money::kes($subtotal) }}</span>
                    </div>

                    <flux:button href="{{ route('checkout') }}" class="mt-5 w-full bg-accent! text-accent-foreground!">
                        Proceed to checkout
                    </flux:button>
                </aside>
            </div>
        </div>
    @endif
</div>
