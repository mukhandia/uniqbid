<?php

use App\Models\Category;
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

    #[Url]
    public string $subcategory = '';

    #[Url]
    public string $q = '';

    public function updatedCategory(): void
    {
        $this->subcategory = '';
    }

    public function with(): array
    {
        $categories = Category::query()
            ->active()
            ->with(['subcategories' => fn ($q) => $q->active()->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $query = Product::query()
            ->search($this->q !== '' ? $this->q : null)
            ->orderByDesc('is_featured')
            ->orderBy('name');

        if ($this->category !== 'All') {
            $query->where('category', $this->category);
        }

        if ($this->subcategory !== '') {
            $query->where('subcategory', $this->subcategory);
        }

        $selectedCategory = $categories->firstWhere('name', $this->category);

        return [
            'products' => $query->get(),
            'categories' => $categories,
            'selectedCategory' => $selectedCategory,
        ];
    }
}; ?>

<div class="mx-auto max-w-7xl px-4 py-10">
    <h1 class="text-3xl font-bold text-navy">Shop</h1>
    <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
        Curated watches, fragrances, accessories, tech, and gift hampers — priced for everyday Kenya shopping and occasion gifting.
    </p>

    <div class="mt-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <flux:input
            wire:model.live.debounce.300ms="q"
            placeholder="Search products…"
            icon="magnifying-glass"
            class="sm:max-w-sm"
        />
        <a href="{{ route('orders.track') }}" class="text-sm font-medium text-navy hover:underline" wire:navigate>
            Track an order →
        </a>
    </div>

    <div class="mt-6 flex flex-wrap gap-2">
        <button
            type="button"
            wire:click="$set('category', 'All')"
            @class([
                'rounded-full border px-4 py-2 text-sm font-medium transition',
                'border-navy bg-navy text-navy-foreground' => $category === 'All',
                'border-border bg-white text-zinc-900 hover:border-navy/40' => $category !== 'All',
            ])
        >
            All
        </button>
        @foreach ($categories as $cat)
            <button
                type="button"
                wire:click="$set('category', {{ \Illuminate\Support\Js::from($cat->name) }})"
                wire:key="cat-filter-{{ $cat->id }}"
                @class([
                    'rounded-full border px-4 py-2 text-sm font-medium transition',
                    'border-navy bg-navy text-navy-foreground' => $category === $cat->name,
                    'border-border bg-white text-zinc-900 hover:border-navy/40' => $category !== $cat->name,
                ])
            >
                {{ $cat->name }}
            </button>
        @endforeach
    </div>

    @if ($selectedCategory && $selectedCategory->subcategories->isNotEmpty())
        <div class="mt-3 flex flex-wrap gap-2">
            <button
                type="button"
                wire:click="$set('subcategory', '')"
                @class([
                    'rounded-full border px-3 py-1.5 text-xs font-medium transition',
                    'border-navy/70 bg-navy/10 text-navy' => $subcategory === '',
                    'border-border bg-white text-zinc-700 hover:border-navy/30' => $subcategory !== '',
                ])
            >
                All {{ $selectedCategory->name }}
            </button>
            @foreach ($selectedCategory->subcategories as $sub)
                <button
                    type="button"
                    wire:click="$set('subcategory', {{ \Illuminate\Support\Js::from($sub->name) }})"
                    wire:key="sub-filter-{{ $sub->id }}"
                    @class([
                        'rounded-full border px-3 py-1.5 text-xs font-medium transition',
                        'border-navy/70 bg-navy/10 text-navy' => $subcategory === $sub->name,
                        'border-border bg-white text-zinc-700 hover:border-navy/30' => $subcategory !== $sub->name,
                    ])
                >
                    {{ $sub->name }}
                </button>
            @endforeach
        </div>
    @endif

    @if ($products->isEmpty())
        <p class="mt-10 text-sm text-muted-foreground">No products match your filters.</p>
    @else
        <div class="mt-8 grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-5">
            @foreach ($products as $product)
                <a href="{{ route('shop.show', $product) }}" class="group overflow-hidden rounded-xl border border-border bg-card-shop shadow-sm transition hover:shadow-md" wire:key="product-{{ $product->id }}">
                    <div class="relative aspect-square overflow-hidden bg-card-shop">
                        <img src="{{ $product->image }}" alt="{{ $product->name }}" class="h-full w-full object-cover transition group-hover:scale-105">
                        @if ($product->availableStock() === 0)
                            <span class="absolute right-2 top-2 rounded-full bg-red-600 px-2 py-0.5 text-[10px] font-semibold text-white">Out of stock</span>
                        @elseif ($product->isLowStock())
                            <span class="absolute right-2 top-2 rounded-full bg-warning px-2 py-0.5 text-[10px] font-semibold text-warning-foreground">Only {{ $product->availableStock() }} left</span>
                        @endif
                        @if ($product->price_tier)
                            <span class="absolute left-2 top-2 rounded-full bg-white/90 px-2 py-0.5 text-[10px] font-semibold text-navy">{{ $product->price_tier }}</span>
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
    @endif
</div>
