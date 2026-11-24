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
#[Title('Shop: Accessories, Hampers & Gifts | UniqBid')]
class extends Component {
    #[Url]
    public string $category = 'All';

    #[Url]
    public string $subcategory = '';

    #[Url(except: '')]
    public string $minPrice = '';

    #[Url(except: '')]
    public string $maxPrice = '';

    public function updatedCategory(): void
    {
        $this->subcategory = '';
    }

    public function clearFilters(): void
    {
        $this->category = 'All';
        $this->subcategory = '';
        $this->minPrice = '';
        $this->maxPrice = '';
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
            ->inShop()
            ->orderByDesc('is_featured')
            ->orderBy('name');

        if ($this->category !== 'All') {
            $query->where('category', $this->category);
        }

        if ($this->subcategory !== '') {
            $query->where('subcategory', $this->subcategory);
        }

        $minPrice = $this->parsedPrice($this->minPrice);
        $maxPrice = $this->parsedPrice($this->maxPrice);

        if ($minPrice !== null && $maxPrice !== null && $minPrice > $maxPrice) {
            [$minPrice, $maxPrice] = [$maxPrice, $minPrice];
        }

        if ($minPrice !== null) {
            $query->where('price', '>=', $minPrice);
        }

        if ($maxPrice !== null) {
            $query->where('price', '<=', $maxPrice);
        }

        $selectedCategory = $categories->firstWhere('name', $this->category);

        return [
            'products' => $query->get(),
            'categories' => $categories,
            'selectedCategory' => $selectedCategory,
            'hasActiveFilters' => $this->category !== 'All'
                || $this->subcategory !== ''
                || $this->parsedPrice($this->minPrice) !== null
                || $this->parsedPrice($this->maxPrice) !== null,
        ];
    }

    private function parsedPrice(string $value): ?int
    {
        $value = trim($value);

        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        return max(0, (int) $value);
    }
}; ?>

<div class="mx-auto max-w-7xl px-4 py-10" x-data="{ filtersOpen: false }">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <h1 class="text-3xl font-bold text-navy">Shop</h1>
            <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                Curated watches, fragrances, accessories, tech, and gift hampers, priced for everyday Kenya shopping and occasion gifting.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-lg border border-border bg-white px-3 py-2 text-sm font-medium text-zinc-900 hover:bg-surface lg:hidden"
                @click="filtersOpen = true"
                :aria-expanded="filtersOpen"
                aria-controls="shop-filters"
            >
                <flux:icon.funnel class="size-4" />
                Filters
            </button>
            <flux:button href="{{ route('orders.track') }}" variant="outline" icon="magnifying-glass" wire:navigate>
                Track an order
            </flux:button>
        </div>
    </div>

    <div
        x-cloak
        x-show="filtersOpen"
        x-transition.opacity
        class="fixed inset-0 z-50 bg-navy/40 lg:hidden"
        @click="filtersOpen = false"
    ></div>

    <div class="mt-8 lg:grid lg:grid-cols-[16.5rem_minmax(0,1fr)] lg:items-start lg:gap-8">
        <aside
            id="shop-filters"
            class="fixed inset-y-0 left-0 z-[60] w-[min(20rem,92vw)] -translate-x-full overflow-y-auto bg-white p-4 shadow-xl transition-transform duration-200 lg:sticky lg:top-32 lg:z-auto lg:w-full lg:!translate-x-0 lg:overflow-visible lg:bg-transparent lg:p-0 lg:shadow-none"
            :class="filtersOpen && '!translate-x-0'"
        >
            <div class="lg:rounded-2xl lg:border lg:border-border lg:bg-white lg:p-5 lg:shadow-sm">
                <x-shop-filters
                    :categories="$categories"
                    :selected-category="$selectedCategory"
                    :category="$category"
                    :subcategory="$subcategory"
                    :has-active-filters="$hasActiveFilters"
                />
            </div>
        </aside>

        <div class="min-w-0">
            <p class="text-sm text-muted-foreground">
                {{ $products->count() }} {{ Str::plural('item', $products->count()) }}
            </p>

            @if ($products->isEmpty())
                <p class="mt-6 text-sm text-muted-foreground">No products match your filters.</p>
            @else
                <div class="mt-4 grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-4">
                    @foreach ($products as $product)
                        <a href="{{ route('shop.show', $product) }}" class="group overflow-hidden rounded-xl border border-border bg-card-shop shadow-sm transition hover:shadow-md" wire:key="product-{{ $product->id }}">
                            <div class="relative aspect-square overflow-hidden bg-card-shop">
                                <img src="{{ $product->image }}" alt="{{ $product->name }}" class="h-full w-full object-cover transition group-hover:scale-105">
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
    </div>
</div>
