@props([
    'sale',
])

@php
    /** @var \App\Models\AuctionResult $sale */
    $buyerPhoto = $sale->buyerImageUrl();
    $initial = strtoupper(substr(trim($sale->buyer_display) !== '' ? $sale->buyer_display : 'U', 0, 1));
@endphp

<div {{ $attributes->class('flex h-full flex-col overflow-hidden rounded-xl border border-border bg-white shadow-sm') }}>
    <div class="relative aspect-square overflow-hidden bg-muted">
        <img src="{{ $sale->image }}" alt="{{ $sale->name }}" class="h-full w-full object-cover">
        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-navy/85 via-navy/40 to-transparent px-3 pb-3 pt-10 text-navy-foreground">
            <div class="flex items-end gap-2">
                <div class="size-11 shrink-0 overflow-hidden rounded-full border-2 border-white/80 bg-navy shadow">
                    @if ($buyerPhoto)
                        <img src="{{ $buyerPhoto }}" alt="{{ $sale->buyer_display }}" class="h-full w-full object-cover">
                    @else
                        <div class="grid h-full w-full place-items-center bg-gold/20 text-sm font-bold text-gold">{{ $initial }}</div>
                    @endif
                </div>
                <div class="min-w-0">
                    <div class="truncate text-sm font-semibold">{{ $sale->buyer_display }}</div>
                    <div class="text-[11px] opacity-85">Secured this item for {{ \App\Support\Money::kes($sale->sale_price) }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="flex flex-1 flex-col p-3">
        <div class="text-xs text-muted-foreground">{{ $sale->sold_on->format('j M Y') }}</div>
        <div class="mt-0.5 line-clamp-2 min-h-[2.5rem] text-sm font-semibold leading-snug">{{ $sale->name }}</div>
        <p class="mt-2 text-xs leading-relaxed text-muted-foreground">
            Real people. Real payouts. Transparent lowest-unique sales you can verify.
        </p>
        <div class="mt-auto pt-2 text-sm font-bold tabular-nums text-navy">{{ \App\Support\Money::kes($sale->sale_price) }}</div>
    </div>
</div>
