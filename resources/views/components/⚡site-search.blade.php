<?php

use App\Services\SiteSearchService;
use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component
{
    public string $query = '';

    public function updatedQuery(string $value): void
    {
        if (Str::length($value) > 80) {
            $this->query = Str::substr($value, 0, 80);
        }
    }

    public function clear(): void
    {
        $this->query = '';
    }

    public function with(): array
    {
        $results = app(SiteSearchService::class)->search($this->query);

        return [
            'results' => $results,
            'showResults' => Str::length(trim($this->query)) >= SiteSearchService::MIN_LENGTH,
        ];
    }
};
?>

<div class="relative">
    <form wire:submit.prevent class="relative">
        <label class="sr-only" for="site-search">Search live sales and shop</label>
        <flux:input
            id="site-search"
            type="text"
            wire:model.live.debounce.300ms="query"
            placeholder="Search live sales and shop…"
            icon="magnifying-glass"
            autocomplete="off"
            autocorrect="off"
            spellcheck="false"
            class="[&_input]:pe-10"
            role="combobox"
            aria-autocomplete="list"
            aria-expanded="{{ $showResults ? 'true' : 'false' }}"
            aria-controls="site-search-results"
        />
        @if ($query !== '')
            <button
                type="button"
                wire:click="clear"
                class="absolute inset-y-0 right-2 z-10 rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-zinc-900"
                aria-label="Clear search"
            >
                <flux:icon.x-mark class="size-4" />
            </button>
        @endif
    </form>

    @if ($showResults)
        <div
            id="site-search-results"
            class="absolute inset-x-0 top-full z-50 mt-1 max-h-[min(20rem,55dvh)] overflow-hidden rounded-xl border border-border bg-white shadow-sm"
            role="listbox"
        >
            <div class="max-h-80 overflow-y-auto py-1">
                @forelse ($results as $item)
                    <div wire:key="{{ $item['key'] }}" class="border-b border-border last:border-b-0" role="option">
                        @if ($item['in_auction'] && $item['in_shop'])
                            <div class="flex gap-3 px-3 py-2.5">
                                <img src="{{ $item['image'] }}" alt="" class="size-12 shrink-0 rounded-md bg-muted object-cover">
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-semibold text-zinc-900">{{ $item['name'] }}</div>
                                    <div class="mt-0.5 text-xs text-muted-foreground">{{ $item['category'] }}</div>
                                    <p class="mt-1 text-xs font-medium text-navy">Found in live auction and shop</p>
                                    <div class="mt-1.5 flex flex-wrap gap-3">
                                        <a
                                            href="{{ route('auctions.show', $item['auction_slug']) }}"
                                            class="text-xs font-semibold text-accent hover:underline"
                                            wire:navigate
                                        >
                                            View sale
                                        </a>
                                        <a
                                            href="{{ route('shop.show', $item['product_slug']) }}"
                                            class="text-xs font-semibold text-navy hover:underline"
                                            wire:navigate
                                        >
                                            View in shop
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @elseif ($item['in_auction'])
                            <a
                                href="{{ route('auctions.show', $item['auction_slug']) }}"
                                class="flex gap-3 px-3 py-2.5 hover:bg-muted"
                                wire:navigate
                            >
                                <img src="{{ $item['image'] }}" alt="" class="size-12 shrink-0 rounded-md bg-muted object-cover">
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-semibold text-zinc-900">{{ $item['name'] }}</div>
                                    <div class="mt-0.5 text-xs text-muted-foreground">{{ $item['category'] }}</div>
                                    <p class="mt-1 text-xs font-medium text-navy">Live auction</p>
                                </div>
                            </a>
                        @else
                            <a
                                href="{{ route('shop.show', $item['product_slug']) }}"
                                class="flex gap-3 px-3 py-2.5 hover:bg-muted"
                                wire:navigate
                            >
                                <img src="{{ $item['image'] }}" alt="" class="size-12 shrink-0 rounded-md bg-muted object-cover">
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-semibold text-zinc-900">{{ $item['name'] }}</div>
                                    <div class="mt-0.5 text-xs text-muted-foreground">{{ $item['category'] }}</div>
                                    <p class="mt-1 text-xs font-medium text-navy">Shop</p>
                                </div>
                            </a>
                        @endif
                    </div>
                @empty
                    <div class="px-3 py-6 text-center text-sm text-muted-foreground">
                        No live sales or shop items match “{{ $query }}”.
                    </div>
                @endforelse
            </div>
        </div>
    @endif
</div>
