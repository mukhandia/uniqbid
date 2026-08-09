@props([
    'auction',
])

@php
    use App\Support\Money;
@endphp

<a
    href="{{ route('auctions.show', $auction) }}"
    {{ $attributes->class('group flex flex-col overflow-hidden rounded-xl border border-border bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md') }}
>
    <div class="relative aspect-[4/3] overflow-hidden bg-muted">
        <img
            src="{{ $auction->primaryImageUrl() }}"
            alt="{{ $auction->name }}"
            class="h-full w-full object-cover transition group-hover:scale-105"
        >
        <div class="absolute left-3 top-3 rounded-full bg-navy px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-navy-foreground">
            {{ $auction->category }}
        </div>
        <div class="absolute bottom-3 left-3 right-3 flex items-center justify-between rounded-lg bg-black/60 px-3 py-1.5 text-xs font-medium text-white backdrop-blur">
            <span>Closes in</span>
            <x-countdown :target="$auction->endsAtMs()" class="font-mono tabular-nums text-gold" />
        </div>
    </div>

    <div class="flex flex-1 flex-col gap-2 p-4">
        <h3 class="line-clamp-2 text-sm font-semibold text-zinc-900">{{ $auction->name }}</h3>
        <div class="mt-auto flex items-end justify-between">
            <div>
                <div class="text-xs text-muted-foreground">Bid fee</div>
                <div class="text-lg font-bold text-navy">{{ Money::kes($auction->bid_fee) }}</div>
            </div>
            <div class="text-right">
                <div class="text-xs text-muted-foreground">{{ number_format($auction->bids_count) }} bids</div>
                <span class="mt-1 inline-flex items-center gap-1 rounded-md bg-gold px-2.5 py-1 text-xs font-semibold text-gold-foreground">
                    <flux:icon.scale class="size-3.5" />
                    View & Bid
                </span>
            </div>
        </div>
    </div>
</a>
