<?php

use App\Models\Auction;
use App\Models\SiteSetting;
use App\Services\LiveAuctionOrderingService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new
#[Layout('layouts.site')]
#[Title('Live Sales/Auctions: UniqBid')]
class extends Component {
    #[Url]
    public string $category = 'All';

    #[Url]
    public string $sort = 'display';

    public function with(): array
    {
        $settings = SiteSetting::current();
        $ordering = app(LiveAuctionOrderingService::class);
        $pollSeconds = min(60, max(5, $settings->auctionShuffleIntervalSeconds()));

        $query = Auction::query()->with('images')->liveOnAuctionChannel();

        if ($this->category !== 'All') {
            $query->where('category', $this->category);
        }

        $auctions = match ($this->sort) {
            'popular' => $query->orderByDesc('bids_count')->orderBy('id')->get(),
            'value' => $query->orderByDesc('retail_value')->orderBy('id')->get(),
            'closing' => $query->orderBy('ends_at')->orderBy('id')->get(),
            default => $this->category === 'All'
                ? $ordering->orderedLive()
                : $ordering->autoShuffle($query->orderBy('id')->get(), $settings->auctionShuffleIntervalSeconds()),
        };

        return [
            'auctions' => $auctions,
            'categories' => ['All', ...Auction::query()->liveOnAuctionChannel()->distinct()->orderBy('category')->pluck('category')->all()],
            'pollSeconds' => $pollSeconds,
        ];
    }
}; ?>

<div class="mx-auto max-w-7xl px-4 py-10" wire:poll.{{ $pollSeconds }}s.visible>
    <div class="flex flex-col gap-2">
        <h1 class="text-3xl font-bold text-navy">Live Sales/Auctions</h1>
        <p class="text-sm text-muted-foreground">All open auctions.</p>
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
                <flux:select.option value="display">Live order</flux:select.option>
                <flux:select.option value="closing">Closing soonest</flux:select.option>
                <flux:select.option value="popular">Most bids</flux:select.option>
                <flux:select.option value="value">Highest product value</flux:select.option>
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
        <div class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-5">
            @foreach ($auctions as $auction)
                <x-auction-card :auction="$auction" class="h-full" />
            @endforeach
        </div>
    @endif
</div>
