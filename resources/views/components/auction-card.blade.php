@props([
    'auction',
])

@php
    use App\Support\Money;
@endphp

<a
    href="{{ route('auctions.show', $auction) }}"
    {{ $attributes->class('group flex h-full flex-col overflow-hidden rounded-xl border border-border bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md') }}
>
    <div class="relative aspect-4/3 overflow-hidden bg-muted">
        <img
            src="{{ $auction->primaryImageUrl() }}"
            alt="{{ $auction->name }}"
            class="h-full w-full object-cover transition group-hover:scale-105"
        >
        <div class="absolute left-2 top-2 max-w-[70%] truncate rounded-full bg-navy px-2 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-navy-foreground sm:left-3 sm:top-3 sm:px-2.5 sm:py-1 sm:text-[10px]">
            {{ $auction->category }}
        </div>
        <div class="absolute inset-x-2 bottom-2 flex items-center justify-between gap-2 rounded-lg bg-black/60 px-2 py-1 text-[10px] font-medium text-white backdrop-blur sm:inset-x-3 sm:bottom-3 sm:px-3 sm:py-1.5 sm:text-xs">
            <span class="shrink-0">Closes in</span>
            <x-countdown :auction="$auction" class="font-mono tabular-nums text-gold" />
        </div>
    </div>

    <div class="flex flex-1 flex-col gap-2 p-3 sm:gap-2.5 sm:p-4">
        <h3 class="line-clamp-2 text-xs font-semibold leading-snug text-zinc-900 sm:text-sm">{{ $auction->name }}</h3>

            <div class="mt-auto space-y-2">
                <div class="space-y-1.5">
                    <div>
                        <div class="text-[10px] text-muted-foreground sm:text-xs">Product value</div>
                        <div class="text-sm font-bold text-navy sm:text-lg">{{ Money::kes($auction->retail_value) }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] text-muted-foreground sm:text-xs">Bid range</div>
                        <div class="text-xs font-semibold tabular-nums text-zinc-900 sm:text-sm">
                            {{ Money::kes($auction->min_bid) }} – {{ Money::kes($auction->max_bid) }}
                        </div>
                    </div>
                </div>

            <div class="flex items-center justify-between gap-2 border-t border-border pt-2">
                <div class="text-[10px] text-muted-foreground sm:text-xs">{{ number_format($auction->bids_count) }} bids</div>
                <span class="inline-flex items-center gap-1 rounded-md bg-gold px-2 py-1 text-[10px] font-semibold text-gold-foreground sm:px-2.5 sm:text-xs">
                    <flux:icon.scale class="size-3 sm:size-3.5" />
                    Bid
                </span>
            </div>
        </div>
    </div>
</a>
