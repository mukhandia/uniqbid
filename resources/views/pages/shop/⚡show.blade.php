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
#[Title('Product: UniqBid Shop')]
class extends Component {
    public Product $product;

    public int $qty = 1;

    public function mount(Product $product): void
    {
        $this->product = $product;
        $this->qty = 1;
    }

    public function increment(): void
    {
        $max = max(1, $this->remainingPurchasable());
        $this->qty = min($max, $this->qty + 1);
    }

    public function decrement(): void
    {
        $this->qty = max(1, $this->qty - 1);
    }

    public function addToCart(CartService $cart): void
    {
        $remaining = $this->remainingPurchasable($cart);

        if ($remaining < 1) {
            Flux::toast(variant: 'warning', text: 'No more stock available to add for this item.');

            return;
        }

        $this->qty = min($remaining, max(1, $this->qty));
        $added = $cart->add($this->product->id, $this->qty);

        if ($added < 1) {
            Flux::toast(variant: 'warning', text: 'No more stock available to add for this item.');

            return;
        }

        $this->dispatch('cart-updated');
        $this->qty = 1;

        Flux::toast(
            variant: 'success',
            text: "Added {$added} × {$this->product->name} to cart.",
        );
    }

    public function with(): array
    {
        $remaining = $this->remainingPurchasable();

        return [
            'remaining' => $remaining,
            'inCart' => app(CartService::class)->quantityOf($this->product->id),
        ];
    }

    private function remainingPurchasable(?CartService $cart = null): int
    {
        $cart ??= app(CartService::class);

        return max(0, $this->product->availableStock() - $cart->quantityOf($this->product->id));
    }
}; ?>

<div class="mx-auto max-w-6xl px-4 py-8">
    <div class="text-xs text-muted-foreground">
        <a href="{{ route('shop.index') }}" class="hover:underline" wire:navigate>Shop</a>
        / {{ $product->category }}
        @if ($product->subcategory)
            / {{ $product->subcategory }}
        @endif
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

            <div class="mt-2 flex flex-wrap gap-2 text-xs">
                @if ($product->price_tier)
                    <span class="rounded-full border border-border bg-surface px-2.5 py-1 font-medium">{{ $product->price_tier }}</span>
                @endif
                @if ($product->gender_age)
                    <span class="rounded-full border border-border bg-surface px-2.5 py-1 font-medium">{{ $product->gender_age }}</span>
                @endif
            </div>

            @if ($product->availableStock() > 0)
                <span class="mt-3 inline-flex rounded-full bg-success/15 px-2.5 py-1 text-xs font-semibold text-success">
                    In stock: {{ $product->availableStock() }} available
                    @if ($inCart > 0)
                        · {{ $inCart }} in cart
                    @endif
                </span>
            @else
                <span class="mt-3 inline-flex rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700">
                    Out of stock
                </span>
            @endif

            <p class="mt-5 text-sm text-zinc-800">{{ $product->description }}</p>

            <div class="mt-6 flex items-center gap-3">
                <div class="inline-flex items-center rounded-md border border-border">
                    <button type="button" wire:click="decrement" class="p-2 hover:bg-muted" aria-label="Decrease">
                        <flux:icon.minus class="pointer-events-none size-4" />
                    </button>
                    <span class="w-10 text-center text-sm font-semibold">{{ $qty }}</span>
                    <button
                        type="button"
                        wire:click="increment"
                        class="p-2 hover:bg-muted disabled:cursor-not-allowed disabled:opacity-40"
                        @disabled($remaining < 1 || $qty >= $remaining)
                        aria-label="Increase"
                    >
                        <flux:icon.plus class="pointer-events-none size-4" />
                    </button>
                </div>
                <flux:button
                    wire:click="addToCart"
                    icon="shopping-cart"
                    class="flex-1 bg-accent! text-accent-foreground! hover:bg-accent/90!"
                    :disabled="$remaining < 1"
                >
                    Add to cart
                </flux:button>
            </div>

            <div class="mt-6 space-y-2 rounded-lg border border-border bg-surface p-4 text-sm">
                <div class="flex items-center gap-2">
                    <flux:icon.truck class="size-4 text-navy" />
                    Free delivery within Nairobi, or free pick-up from our Westlands office. Delivery fee charges apply outside Nairobi.
                </div>
                <div class="flex items-center gap-2">
                    <flux:icon.shield-check class="size-4 text-navy" />
                    M-Pesa checkout · Cancel free before dispatch · Returns within 7 days if eligible
                </div>
                @unless ($product->is_returnable)
                    <div class="flex items-start gap-2 text-amber-800">
                        <flux:icon.exclamation-triangle class="mt-0.5 size-4 shrink-0" />
                        This item is marked non-returnable under our Refund Policy.
                    </div>
                @endunless
            </div>
        </div>
    </div>
</div>
