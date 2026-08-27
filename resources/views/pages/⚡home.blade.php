<?php

use App\Models\Auction;
use App\Models\AuctionResult;
use App\Models\Product;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.site')]
#[Title('UniqBid: Live Auctions in Kenya | Licensed Auctioneer')]
class extends Component {
    //
}; ?>

@php
    use App\Support\Money;

    $featured = Auction::query()->with('images')->live()->featured()->orderBy('ends_at')->first()
        ?? Auction::query()->with('images')->live()->orderBy('ends_at')->first();

    $grid = Auction::query()->with('images')->live()->orderBy('ends_at')->limit(10)->get();
    $recentSales = AuctionResult::query()->orderByDesc('sold_on')->limit(10)->get();
    $featuredProducts = Product::query()->featured()->orderBy('name')->limit(10)->get();
@endphp

<div>
    <section class="relative min-h-[70vh] overflow-hidden bg-navy text-navy-foreground md:min-h-[78vh]">
        <div class="absolute inset-0" aria-hidden="true">
            @if ($featured)
                <img
                    src="{{ $featured->primaryImageUrl() }}"
                    alt=""
                    class="h-full w-full object-cover"
                >
                <div class="absolute inset-0 opacity-40" style="background-image: var(--hero-overlay-1)"></div>
            @else
                <div class="h-full w-full bg-navy"></div>
            @endif
            <div class="absolute inset-0 bg-gradient-to-r from-navy/95 via-navy/80 to-navy/45"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-navy/70 via-transparent to-navy/30"></div>
        </div>

        <div class="relative z-10 mx-auto grid min-h-[70vh] max-w-7xl items-center gap-10 px-4 py-16 md:min-h-[78vh] md:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)] md:gap-10 md:py-24 lg:gap-14">
            <div class="max-w-xl">
                <span class="inline-flex w-fit items-center gap-2 rounded-full border border-white/20 bg-white/5 px-3 py-1 text-xs font-medium backdrop-blur">
                    <flux:icon.shield-check class="size-3.5 text-gold" />
                    Licensed Kenyan Auctioneer · Est. 2024
                </span>
                <h1 class="mt-4 text-4xl font-bold leading-tight sm:text-5xl lg:text-6xl">
                    The lowest <span class="text-gold">unique bid</span> secures the item.
                </h1>
                <p class="mt-4 max-w-md text-base opacity-90">
                    Bid transparently on real items: phones, TVs, motorbikes, from as little as KES 20. Instantly pay via M-Pesa and get SMS or WhatsApp confirmation.
                </p>
                <div class="mt-6 flex flex-wrap gap-3">
                    @if ($featured)
                        <flux:button
                            href="{{ route('auctions.show', $featured) }}"
                            class="bg-accent! text-accent-foreground! hover:bg-accent/90!"
                        >
                            Place Your Bid
                            <flux:icon.arrow-right class="size-4" />
                        </flux:button>
                    @endif
                    <flux:button
                        href="{{ route('how-it-works') }}"
                        variant="ghost"
                        class="border border-white/25 bg-white/5 text-navy-foreground! hover:bg-white/10!"
                    >
                        How it works
                    </flux:button>
                </div>
                <div class="mt-8 grid grid-cols-2 gap-4 text-xs sm:grid-cols-4">
                    @foreach ([
                        ['icon' => 'shield-check', 'label' => 'Licensed'],
                        ['icon' => 'check-badge', 'label' => 'Transparent'],
                        ['icon' => 'chat-bubble-left-right', 'label' => 'SMS / WhatsApp'],
                        ['icon' => 'device-phone-mobile', 'label' => 'M-Pesa Secure'],
                    ] as $trust)
                        <div class="flex items-center gap-2 opacity-90">
                            <flux:icon :name="$trust['icon']" class="size-4 text-gold" />
                            <span>{{ $trust['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            @if ($featured)
                <a
                    href="{{ route('auctions.show', $featured) }}"
                    class="group relative mx-auto hidden w-full max-w-md md:mx-0 md:block md:max-w-none"
                    aria-label="View {{ $featured->name }}"
                >
                    <img
                        src="{{ $featured->primaryImageUrl() }}"
                        alt="{{ $featured->name }}"
                        class="aspect-[4/5] w-full rounded-2xl border border-white/20 object-cover transition duration-500 group-hover:scale-[1.02]"
                    >
                    <div class="mt-4">
                        <div class="text-lg font-semibold leading-snug">{{ $featured->name }}</div>
                        <div class="mt-1 text-sm opacity-80">
                            Value {{ Money::kes($featured->retail_value) }} · Bid {{ Money::kes($featured->min_bid) }}–{{ Money::kes($featured->max_bid) }} · Closes {{ $featured->ends_at?->timezone('Africa/Nairobi')->format('d M, H:i') }} EAT
                        </div>
                    </div>
                </a>
            @endif
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-14">
        <div class="flex items-end justify-between">
            <div>
                <div class="text-xs font-semibold uppercase tracking-wider text-gold">Live now</div>
                <h2 class="mt-1 text-2xl font-bold text-navy sm:text-3xl">Auctions closing soon</h2>
            </div>
            <a href="{{ route('auctions.index') }}" class="hidden text-sm font-medium text-navy hover:underline sm:inline">See all →</a>
        </div>
        <x-scroll-row class="mt-6">
            @foreach ($grid as $auction)
                <x-scroll-row-item>
                    <x-auction-card :auction="$auction" class="h-full" />
                </x-scroll-row-item>
            @endforeach
        </x-scroll-row>
    </section>

    <section class="bg-surface">
        <div class="mx-auto max-w-7xl px-4 py-14">
            <h2 class="text-center text-2xl font-bold text-navy sm:text-3xl">How UniqBid works</h2>
            <p class="mx-auto mt-2 max-w-xl text-center text-sm text-muted-foreground">
                Product value and bid range up front. Pay a small participation fee per bid. Successful buyers pay their bid amount later.
            </p>
            <div class="mt-8 grid gap-4 md:grid-cols-3">
                @foreach ([
                    ['n' => '1', 't' => 'Browse sales', 'd' => 'See product value, bid range, countdown, and the participation fee.'],
                    ['n' => '2', 't' => 'Bid & pay fee', 'd' => 'Enter a bid amount in range, then pay the participation fee to confirm it.'],
                    ['n' => '3', 't' => 'Successful buy', 'd' => 'Lowest unique bid secures the item. That buyer pays the bid amount on collection.'],
                ] as $step)
                    <div class="rounded-xl border border-border bg-white p-6 shadow-sm">
                        <div class="grid h-9 w-9 place-items-center rounded-full bg-navy text-sm font-bold text-navy-foreground">{{ $step['n'] }}</div>
                        <h3 class="mt-4 text-base font-semibold">{{ $step['t'] }}</h3>
                        <p class="mt-1.5 text-sm text-muted-foreground">{{ $step['d'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-14">
        <div class="flex items-end justify-between">
            <h2 class="text-2xl font-bold text-navy sm:text-3xl">Recent sales</h2>
            <a href="{{ route('past-sales') }}" class="text-sm font-medium text-navy hover:underline">All past sales →</a>
        </div>
        <x-scroll-row class="mt-6" loop autoplay>
            @foreach ($recentSales as $sale)
                <x-scroll-row-item>
                    <div class="overflow-hidden rounded-xl border border-border bg-white shadow-sm">
                        <div class="aspect-square overflow-hidden bg-muted">
                            <img src="{{ $sale->image }}" alt="{{ $sale->name }}" class="h-full w-full object-cover">
                        </div>
                        <div class="p-3">
                            <div class="text-xs text-muted-foreground">{{ $sale->sold_on->format('j M') }}</div>
                            <div class="mt-0.5 line-clamp-1 text-sm font-semibold">{{ $sale->name }}</div>
                            <div class="mt-1 flex items-center justify-between">
                                <span class="text-xs text-muted-foreground">{{ $sale->buyer_display }}</span>
                                <span class="text-sm font-bold text-navy">{{ Money::kes($sale->sale_price) }}</span>
                            </div>
                        </div>
                    </div>
                </x-scroll-row-item>
            @endforeach
        </x-scroll-row>
    </section>

    @if ($featuredProducts->isNotEmpty())
        <section class="bg-surface">
            <div class="mx-auto max-w-7xl px-4 py-14">
                <div class="flex items-end justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Also from UniqBid</div>
                        <h2 class="mt-1 text-2xl font-bold text-navy sm:text-3xl">Featured in the shop</h2>
                    </div>
                    <a href="{{ route('shop.index') }}" class="hidden text-sm font-medium text-navy hover:underline sm:inline">Visit shop →</a>
                </div>
                <x-scroll-row class="mt-6" loop autoplay>
                    @foreach ($featuredProducts as $product)
                        <x-scroll-row-item>
                            <a href="{{ route('shop.show', $product) }}" class="group block overflow-hidden rounded-xl border border-border bg-card-home shadow-sm transition hover:shadow-md">
                                <div class="aspect-square overflow-hidden bg-card-home">
                                    <img src="{{ $product->image }}" alt="{{ $product->name }}" class="h-full w-full object-cover transition group-hover:scale-105">
                                </div>
                                <div class="p-3">
                                    <div class="text-[11px] text-muted-foreground">{{ $product->category }}</div>
                                    <div class="line-clamp-1 text-sm font-semibold">{{ $product->name }}</div>
                                    <div class="mt-1 text-sm font-bold text-navy">{{ Money::kes($product->price) }}</div>
                                </div>
                            </a>
                        </x-scroll-row-item>
                    @endforeach
                </x-scroll-row>
            </div>
        </section>
    @endif

    <section class="mx-auto max-w-7xl px-4 py-14">
        <div class="rounded-2xl bg-navy p-8 text-navy-foreground sm:p-12">
            <div class="grid gap-8 md:grid-cols-2 md:items-center">
                <div>
                    <div class="flex items-center gap-2 text-gold">
                        <flux:icon.check-circle class="size-5" />
                        <span class="text-xs font-semibold uppercase tracking-wider">Regulated & transparent</span>
                    </div>
                    <h2 class="mt-3 text-2xl font-bold sm:text-3xl">A real auctioneer. Real items. Real sales.</h2>
                    <p class="mt-3 max-w-md text-sm opacity-85">
                        UniqBid is registered under the Auctioneers Act with published rules, a versioned privacy policy, and public past sales, because trust is earned.
                    </p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ([
                        'Licence No. AUC/2024/00847',
                        'Data Protection Act, 2019 compliant',
                        'Public past sales ledger',
                        'M-Pesa STK push payments',
                    ] as $item)
                        <div class="flex items-start gap-2 rounded-lg bg-white/5 p-3 text-sm">
                            <flux:icon.shield-check class="mt-0.5 size-4 shrink-0 text-gold" />
                            {{ $item }}
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
</div>
