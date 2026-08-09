<?php

use App\Models\Auction;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new
#[Layout('layouts.site')]
#[Title('Live Auctions — UniqBid')]
class extends Component {
    #[Url]
    public string $category = 'All';

    #[Url]
    public string $sort = 'closing';

    public function with(): array
    {
        $query = Auction::query()->with('images')->live();

        if ($this->category !== 'All') {
            $query->where('category', $this->category);
        }

        match ($this->sort) {
            'popular' => $query->orderByDesc('bids_count'),
            'fee' => $query->orderBy('bid_fee'),
            default => $query->orderBy('ends_at'),
        };

        return [
            'auctions' => $query->get(),
            'categories' => ['All', ...Auction::query()->live()->distinct()->orderBy('category')->pluck('category')->all()],
        ];
    }
}; ?>

<div class="mx-auto max-w-7xl px-4 py-10">
    <div class="flex flex-col gap-2">
        <h1 class="text-3xl font-bold text-navy">Live Auctions</h1>
        <p class="text-sm text-muted-foreground">All open auctions. Timers update in real time.</p>
    </div>

    <div class="mt-6 flex flex-wrap items-center gap-3 rounded-lg border border-border bg-white p-3">
        <div class="min-w-[180px] flex-1">
            <flux:select wire:model.live="category">
                @foreach ($categories as $cat)
                    <flux:select.option value="{{ $cat }}">{{ $cat }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        <div class="min-w-[180px] flex-1">
            <flux:select wire:model.live="sort">
                <flux:select.option value="closing">Closing soonest</flux:select.option>
                <flux:select.option value="popular">Most bids</flux:select.option>
                <flux:select.option value="fee">Lowest bid fee</flux:select.option>
            </flux:select>
        </div>
        <div class="text-sm text-muted-foreground">
            {{ $auctions->count() }} {{ Str::plural('auction', $auctions->count()) }}
        </div>
    </div>

    @if ($auctions->isEmpty())
        <div class="mt-10 rounded-xl border border-dashed border-border bg-surface p-10 text-center">
            <div class="text-lg font-semibold">No auctions match your filter</div>
            <p class="mt-1 text-sm text-muted-foreground">Try a different category or check back soon.</p>
        </div>
    @else
        <div class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($auctions as $auction)
                <x-auction-card :auction="$auction" />
            @endforeach
        </div>
    @endif
</div>
