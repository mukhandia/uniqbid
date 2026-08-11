<?php

use App\Models\AuctionResult;
use App\Support\Money;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new
#[Layout('layouts.site')]
#[Title('Past Sales — Completed UniqBid Auctions')]
class extends Component {
    #[Url]
    public string $q = '';

    #[Url]
    public string $month = 'all';

    public function with(): array
    {
        $query = AuctionResult::query()->orderByDesc('sold_on');

        if (filled($this->q)) {
            $query->where(function ($builder): void {
                $builder
                    ->where('name', 'like', '%'.$this->q.'%')
                    ->orWhere('buyer_display', 'like', '%'.$this->q.'%');
            });
        }

        if ($this->month !== 'all') {
            [$year, $monthNum] = explode('-', $this->month);
            $query->whereYear('sold_on', $year)->whereMonth('sold_on', $monthNum);
        }

        $months = AuctionResult::query()
            ->orderByDesc('sold_on')
            ->get(['sold_on'])
            ->map(fn (AuctionResult $result): string => $result->sold_on->format('Y-m'))
            ->unique()
            ->values()
            ->all();

        return [
            'results' => $query->get(),
            'months' => ['all', ...$months],
        ];
    }
}; ?>

<div class="mx-auto max-w-7xl px-4 py-10">
    <div class="flex items-center gap-3">
        <flux:icon.shopping-bag class="size-6 text-gold" />
        <h1 class="text-3xl font-bold text-navy">Past sales</h1>
    </div>
    <p class="mt-2 max-w-xl text-sm text-muted-foreground">
        Full public ledger of completed auctions. Buyer photos shown with consent.
    </p>

    <div class="mt-6 flex flex-wrap items-center gap-3 rounded-lg border border-border bg-white p-3">
        <div class="min-w-[220px] flex-1">
            <flux:input wire:model.live.debounce.300ms="q" placeholder="Search by item or buyer" icon="magnifying-glass" />
        </div>
        <div class="w-[200px]">
            <flux:select wire:model.live="month">
                @foreach ($months as $m)
                    <flux:select.option value="{{ $m }}">
                        @if ($m === 'all')
                            All months
                        @else
                            {{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $m)->format('F Y') }}
                        @endif
                    </flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    @if ($results->isEmpty())
        <div class="mt-10 rounded-xl border border-dashed border-border bg-surface p-10 text-center">
            <div class="text-lg font-semibold">No sales match your search</div>
            <p class="mt-1 text-sm text-muted-foreground">Try a different keyword or month.</p>
        </div>
    @else
        <div class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-5">
            @foreach ($results as $result)
                <div class="overflow-hidden rounded-xl border border-border bg-white shadow-sm">
                    <div class="aspect-[4/3] overflow-hidden bg-muted">
                        <img src="{{ $result->image }}" alt="{{ $result->name }}" class="h-full w-full object-cover">
                    </div>
                    <div class="p-4">
                        <div class="flex items-center justify-between text-xs text-muted-foreground">
                            <span>{{ $result->sold_on->format('j M Y') }}</span>
                            <span>{{ $result->buyer_display }}</span>
                        </div>
                        <div class="mt-1 line-clamp-2 text-sm font-semibold">{{ $result->name }}</div>
                        <div class="mt-2 text-base font-bold text-navy">{{ Money::kes($result->sale_price) }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
