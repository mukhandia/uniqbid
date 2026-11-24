@props([
    'categories',
    'selectedCategory',
    'category',
    'subcategory',
    'hasActiveFilters' => false,
])

<div {{ $attributes->merge(['class' => 'space-y-6']) }}>
    <div class="flex items-center justify-between gap-3">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-navy">Filters</h2>
        <button
            type="button"
            class="rounded-md p-1.5 text-muted-foreground hover:bg-muted hover:text-zinc-900 lg:hidden"
            @click="filtersOpen = false"
            aria-label="Close filters"
        >
            <flux:icon.x-mark class="size-5" />
        </button>
    </div>

    <div>
        <h3 class="text-sm font-semibold text-zinc-900">Categories</h3>
        <nav class="mt-2 space-y-0.5" aria-label="Product categories">
            <button
                type="button"
                wire:click="$set('category', 'All')"
                @class([
                    'flex w-full rounded-lg px-3 py-2 text-left text-sm transition',
                    'bg-navy font-medium text-navy-foreground' => $category === 'All',
                    'text-zinc-700 hover:bg-surface' => $category !== 'All',
                ])
            >
                All products
            </button>
            @foreach ($categories as $cat)
                <button
                    type="button"
                    wire:click="$set('category', {{ \Illuminate\Support\Js::from($cat->name) }})"
                    wire:key="cat-filter-{{ $cat->id }}"
                    @class([
                        'flex w-full rounded-lg px-3 py-2 text-left text-sm transition',
                        'bg-navy font-medium text-navy-foreground' => $category === $cat->name,
                        'text-zinc-700 hover:bg-surface' => $category !== $cat->name,
                    ])
                >
                    {{ $cat->name }}
                </button>
            @endforeach
        </nav>

        @if ($selectedCategory && $selectedCategory->subcategories->isNotEmpty())
            <div class="mt-3 border-t border-border pt-3">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">{{ $selectedCategory->name }}</h3>
                <nav class="mt-2 space-y-0.5" aria-label="Sub-categories">
                    <button
                        type="button"
                        wire:click="$set('subcategory', '')"
                        @class([
                            'flex w-full rounded-lg px-3 py-1.5 text-left text-sm transition',
                            'bg-navy/10 font-medium text-navy' => $subcategory === '',
                            'text-zinc-600 hover:bg-surface' => $subcategory !== '',
                        ])
                    >
                        All
                    </button>
                    @foreach ($selectedCategory->subcategories as $sub)
                        <button
                            type="button"
                            wire:click="$set('subcategory', {{ \Illuminate\Support\Js::from($sub->name) }})"
                            wire:key="sub-filter-{{ $sub->id }}"
                            @class([
                                'flex w-full rounded-lg px-3 py-1.5 text-left text-sm transition',
                                'bg-navy/10 font-medium text-navy' => $subcategory === $sub->name,
                                'text-zinc-600 hover:bg-surface' => $subcategory !== $sub->name,
                            ])
                        >
                            {{ $sub->name }}
                        </button>
                    @endforeach
                </nav>
            </div>
        @endif
    </div>

    <div class="border-t border-border pt-6">
        <h3 class="text-sm font-semibold text-zinc-900">Price (KES)</h3>
        <div class="mt-3 grid grid-cols-2 gap-2">
            <flux:field>
                <flux:label>Min</flux:label>
                <flux:input type="number" wire:model.live.debounce.400ms="minPrice" min="0" inputmode="numeric" placeholder="0" />
            </flux:field>
            <flux:field>
                <flux:label>Max</flux:label>
                <flux:input type="number" wire:model.live.debounce.400ms="maxPrice" min="0" inputmode="numeric" placeholder="Any" />
            </flux:field>
        </div>
    </div>

    @if ($hasActiveFilters)
        <button
            type="button"
            wire:click="clearFilters"
            class="text-sm font-medium text-navy underline hover:no-underline"
        >
            Clear filters
        </button>
    @endif

    <button
        type="button"
        class="w-full rounded-lg bg-navy px-4 py-2.5 text-sm font-medium text-navy-foreground lg:hidden"
        @click="filtersOpen = false"
    >
        Show results
    </button>
</div>
