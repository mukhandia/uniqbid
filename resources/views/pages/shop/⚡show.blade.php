<?php

use App\Models\Product;
use App\Services\CartService;
use App\Support\Money;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.site')]
#[Title('Product — UniqBid Shop')]
class extends Component {
    public Product $product;

    public int $qty = 1;

    public function mount(Product $product): void
    {
        $this->product = $product;
    }

    public function increment(): void
    {
        $this->qty = min($this->product->stock, $this->qty + 1);
    }

    public function decrement(): void
    {
        $this->qty = max(1, $this->qty - 1);
    }

    public function addToCart(CartService $cart): void
    {
        if ($this->product->stock < 1) {
            return;
        }

        $cart->add($this->product->id, $this->qty);

        Flux::toast(
            variant: 'success',
            text: "Added {$this->qty} × {$this->product->name} to cart.",
        );
    }
}; ?>

<div class="mx-auto max-w-6xl px-4 py-8">
    <div class="text-xs text-muted-foreground">
        <a href="{{ route('shop.index') }}" class="hover:underline">Shop</a>
        / {{ $product->category }}
    </div>

    <div class="mt-4 grid gap-8 md:grid-cols-2">
        <div class="overflow-hidden rounded-2xl border border-border bg-white">
            <div class="aspect-square bg-muted">
                <img src="{{ $product->image }}" alt="{{ $product->name }}" class="h-full w-full object-cover">
            </div>
        </div>

        <div>
            <div class="text-xs text-muted-foreground">{{ $product->category }}</div>
            <h1 class="mt-1 text-3xl font-bold text-navy">{{ $product->name }}</h1>
            <div class="mt-3 text-2xl font-bold">{{ Money::kes($product->price) }}</div>

            @if ($product->stock > 0)
                <span class="mt-2 inline-flex rounded-full bg-success/15 px-2.5 py-1 text-xs font-semibold text-success">
                    In stock — {{ $product->stock }} available
                </span>
            @else
                <span class="mt-2 inline-flex rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700">
                    Out of stock
                </span>
            @endif

            <p class="mt-5 text-sm text-zinc-800">{{ $product->description }}</p>

            <div class="mt-6 flex items-center gap-3">
                <div class="inline-flex items-center rounded-md border border-border">
                    <button type="button" wire:click="decrement" class="p-2 hover:bg-muted" aria-label="Decrease">
                        <flux:icon.minus class="size-4" />
                    </button>
                    <span class="w-10 text-center text-sm font-semibold">{{ $qty }}</span>
                    <button type="button" wire:click="increment" class="p-2 hover:bg-muted" aria-label="Increase">
                        <flux:icon.plus class="size-4" />
                    </button>
                </div>
                <flux:button
                    wire:click="addToCart"
                    class="flex-1 bg-navy! text-navy-foreground! hover:bg-navy/90!"
                    :disabled="$product->stock === 0"
                >
                    <flux:icon.shopping-cart class="size-4" />
                    Add to cart
                </flux:button>
            </div>

            <div class="mt-6 space-y-2 rounded-lg border border-border bg-surface p-4 text-sm">
                <div class="flex items-center gap-2">
                    <flux:icon.truck class="size-4 text-navy" />
                    Free delivery within Nairobi, or free pick-up from our office
                </div>
                <div class="flex items-center gap-2">
                    <flux:icon.shield-check class="size-4 text-navy" />
                    M-Pesa secure checkout with SMS/WhatsApp confirmation
                </div>
            </div>
        </div>
    </div>
</div>
