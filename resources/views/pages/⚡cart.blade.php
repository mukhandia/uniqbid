<?php

use App\Services\CartService;
use App\Support\Money;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.site')]
#[Title('Your Cart — UniqBid Shop')]
class extends Component {
    public function updateQty(int $productId, int $qty, CartService $cart): void
    {
        $cart->update($productId, $qty);
    }

    public function remove(int $productId, CartService $cart): void
    {
        $cart->remove($productId);
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

@if ($lines->isEmpty())
    <div class="mx-auto max-w-xl px-4 py-20 text-center">
        <div class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-surface">
            <flux:icon.shopping-bag class="size-8 text-muted-foreground" />
        </div>
        <h1 class="mt-4 text-2xl font-bold text-navy">Your cart is empty</h1>
        <p class="mt-2 text-sm text-muted-foreground">Browse the shop or explore live auctions.</p>
            <div class="mt-6 flex justify-center gap-2">
            <flux:button href="{{ route('shop.index') }}" class="bg-accent! text-accent-foreground!">Go to shop</flux:button>
            <flux:button href="{{ route('auctions.index') }}" variant="outline">Live auctions</flux:button>
        </div>
    </div>
@else
    <div class="mx-auto max-w-5xl px-4 py-10">
        <h1 class="text-3xl font-bold text-navy">Your cart</h1>
        <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_320px]">
            <div class="rounded-2xl border border-border bg-white">
                @foreach ($lines as $index => $line)
                    <div @class(['flex gap-4 p-4', 'border-t border-border' => $index > 0])>
                        <div class="h-20 w-20 shrink-0 overflow-hidden rounded-md bg-muted">
                            <img src="{{ $line['product']->image }}" alt="{{ $line['product']->name }}" class="h-full w-full object-cover">
                        </div>
                        <div class="flex flex-1 flex-col">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-xs text-muted-foreground">{{ $line['product']->category }}</div>
                                    <div class="font-semibold">{{ $line['product']->name }}</div>
                                </div>
                                <button type="button" wire:click="remove({{ $line['product']->id }})" class="text-muted-foreground hover:text-red-600">
                                    <flux:icon.trash class="size-4" />
                                </button>
                            </div>
                            <div class="mt-auto flex items-center justify-between">
                                <div class="inline-flex items-center rounded-md border border-border">
                                    <button type="button" wire:click="updateQty({{ $line['product']->id }}, {{ $line['qty'] - 1 }})" class="p-1.5 hover:bg-muted">
                                        <flux:icon.minus class="size-3.5" />
                                    </button>
                                    <span class="w-8 text-center text-sm font-semibold">{{ $line['qty'] }}</span>
                                    <button type="button" wire:click="updateQty({{ $line['product']->id }}, {{ $line['qty'] + 1 }})" class="p-1.5 hover:bg-muted">
                                        <flux:icon.plus class="size-3.5" />
                                    </button>
                                </div>
                                <div class="text-sm font-bold text-navy">{{ Money::kes($line['line_total']) }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <aside class="h-fit rounded-2xl border border-border bg-white p-5">
                <h2 class="text-base font-semibold">Order summary</h2>
                <div class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between text-muted-foreground">
                        <span>Subtotal</span>
                        <span>{{ Money::kes($subtotal) }}</span>
                    </div>
                    <div class="flex justify-between text-muted-foreground">
                        <span>Delivery</span>
                        <span>Calculated at checkout</span>
                    </div>
                </div>
                <div class="mt-3 flex justify-between border-t border-border pt-3 text-base font-bold">
                    <span>Total</span>
                    <span class="text-navy">{{ Money::kes($subtotal) }}</span>
                </div>
                <flux:button href="{{ route('checkout') }}" class="mt-4 w-full bg-accent! text-accent-foreground!">
                    Proceed to checkout
                </flux:button>
            </aside>
        </div>
    </div>
@endif
