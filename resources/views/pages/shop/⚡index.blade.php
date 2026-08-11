<?php

use App\Models\Product;
use App\Support\Money;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new
#[Layout('layouts.site')]
#[Title('Shop — Accessories, Hampers & Gifts | UniqBid')]
class extends Component {
    #[Url]
    public string $category = 'All';

    /**
     * @return list<string>
     */
    public function categories(): array
    {
        return ['All', "Men's Accessories", "Women's Accessories", 'Hampers', 'Gifts'];
    }

    public function with(): array
    {
        $query = Product::query()->orderBy('name');

        if ($this->category !== 'All') {
            $query->where('category', $this->category);
        }

        return [
            'products' => $query->get(),
            'categories' => $this->categories(),
        ];
    }
}; ?>

<div class="mx-auto max-w-7xl px-4 py-10">
    <h1 class="text-3xl font-bold text-navy">Shop</h1>
    <p class="mt-2 text-sm text-muted-foreground">A curated selection of accessories, hampers, and gifts.</p>

    <div class="mt-6 flex flex-wrap gap-2">
        @foreach ($categories as $cat)
            <button
                type="button"
                wire:click="$set('category', '{{ $cat }}')"
                @class([
                    'rounded-full border px-4 py-2 text-sm font-medium transition',
                    'border-navy bg-navy text-navy-foreground' => $category === $cat,
                    'border-border bg-white text-zinc-900 hover:border-navy/40' => $category !== $cat,
                ])
            >
                {{ $cat }}
            </button>
        @endforeach
    </div>

    <div class="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
        @foreach ($products as $product)
            <a href="{{ route('shop.show', $product) }}" class="group overflow-hidden rounded-xl border border-border bg-card-shop shadow-sm transition hover:shadow-md">
                <div class="relative aspect-square overflow-hidden bg-muted">
                    <img src="{{ $product->image }}" alt="{{ $product->name }}" class="h-full w-full object-cover transition group-hover:scale-105">
                    @if ($product->stock === 0)
                        <span class="absolute right-2 top-2 rounded-full bg-red-600 px-2 py-0.5 text-[10px] font-semibold text-white">Out of stock</span>
                    @elseif ($product->isLowStock())
                        <span class="absolute right-2 top-2 rounded-full bg-warning px-2 py-0.5 text-[10px] font-semibold text-warning-foreground">Only {{ $product->stock }} left</span>
                    @endif
                </div>
                <div class="p-3">
                    <div class="text-[11px] text-muted-foreground">{{ $product->category }}</div>
                    <div class="line-clamp-1 text-sm font-semibold">{{ $product->name }}</div>
                    <div class="mt-1 text-sm font-bold text-navy">{{ Money::kes($product->price) }}</div>
                </div>
            </a>
        @endforeach
    </div>
</div>
