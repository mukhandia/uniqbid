<?php

use App\Models\Auction;
use App\Models\AuctionResult;
use App\Models\Product;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.site')]
#[Title('UniqBid — Live Auctions in Kenya | Licensed Auctioneer')]
class extends Component {
    //
}; ?>

@php
    use App\Support\Money;

    // Hero slider: render exactly 3 slides (repeat the first slide if we have fewer).
    $heroSlides = Auction::query()->with('images')->live()->featured()->limit(3)->get();

    if ($heroSlides->count() < 3) {
        $heroSlides = Auction::query()->with('images')->live()->orderBy('ends_at')->limit(3)->get();
    }

    $heroSlides = $heroSlides->values();

    $heroSlidesForRender = [];
    for ($i = 0; $i < 3; $i++) {
        $heroSlidesForRender[] = $heroSlides[$i] ?? $heroSlides[0] ?? null;
    }

    $featured = $heroSlidesForRender[0] ?? null;
    $grid = Auction::query()->with('images')->live()->orderBy('ends_at')->limit(10)->get();
    $recentSales = AuctionResult::query()->orderByDesc('sold_on')->limit(10)->get();
    $featuredProducts = Product::query()->orderByDesc('is_featured')->orderBy('name')->limit(10)->get();
@endphp

<div>
    <section
        class="relative min-h-[70vh] overflow-hidden bg-navy text-navy-foreground md:min-h-[78vh]"
        x-data="{ index: 0, next(){ this.index = (this.index + 1) % 3 }, prev(){ this.index = (this.index + 2) % 3 } }"
        x-init="setInterval(() => { next() }, 6500)"
    >
        {{-- Full-bleed slider background --}}
        <div class="absolute inset-0" aria-hidden="true">
            <div
                class="flex h-full transition-transform duration-700 ease-in-out"
                :style="'transform: translateX(-' + (index * 100) + '%)'"
            >
                @for ($i = 0; $i < 3; $i++)
                    @php $slide = $heroSlidesForRender[$i]; @endphp
                    <div class="relative h-full w-full shrink-0">
                        @if ($slide)
                            <img
                                src="{{ $slide->primaryImageUrl() }}"
                                alt=""
                                class="h-full w-full object-cover"
                            >
                            <div class="absolute inset-0 opacity-40" style="background-image: var(--hero-overlay-{{ $i + 1 }})"></div>
                        @else
                            <div class="h-full w-full bg-navy"></div>
                        @endif
                    </div>
                @endfor
            </div>
            <div class="absolute inset-0 bg-gradient-to-r from-navy/95 via-navy/80 to-navy/45"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-navy/70 via-transparent to-navy/30"></div>
        </div>

        {{-- Side arrows: mid-height from sm up; bottom corners on small screens to clear copy --}}
        <button
            type="button"
            aria-label="Previous slide"
            class="absolute bottom-5 left-3 z-20 grid size-11 place-items-center rounded-full bg-black/40 text-white shadow-sm backdrop-blur transition hover:bg-black/55 active:scale-95 sm:bottom-auto sm:left-4 sm:top-1/2 sm:size-12 sm:-translate-y-1/2 md:left-6 lg:left-8"
            @click="prev()"
        >
            <flux:icon.chevron-left class="size-5 sm:size-6" />
        </button>
        <button
            type="button"
            aria-label="Next slide"
            class="absolute bottom-5 right-3 z-20 grid size-11 place-items-center rounded-full bg-black/40 text-white shadow-sm backdrop-blur transition hover:bg-black/55 active:scale-95 sm:bottom-auto sm:right-4 sm:top-1/2 sm:size-12 sm:-translate-y-1/2 md:right-6 lg:right-8"
            @click="next()"
        >
            <flux:icon.chevron-right class="size-5 sm:size-6" />
        </button>

        <div class="relative z-10 mx-auto flex min-h-[70vh] max-w-7xl flex-col justify-center px-4 py-16 pb-20 md:min-h-[78vh] md:py-24 sm:pb-16">
            <div class="max-w-xl">
                <span class="inline-flex w-fit items-center gap-2 rounded-full border border-white/20 bg-white/5 px-3 py-1 text-xs font-medium backdrop-blur">
                    <flux:icon.shield-check class="size-3.5 text-gold" />
                    Licensed Kenyan Auctioneer · Est. 2024
                </span>
                <h1 class="mt-4 text-4xl font-bold leading-tight sm:text-5xl lg:text-6xl">
                    The lowest <span class="text-gold">unique bid</span> wins.
                </h1>
                <p class="mt-4 max-w-md text-base opacity-90">
                    Bid transparently on real items — phones, TVs, motorbikes — from as little as KES 20. Instantly pay via M-Pesa and get SMS or WhatsApp confirmation.
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
                        ['icon' => 'trophy', 'label' => 'Transparent'],
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

            <div class="mt-10 flex items-center gap-2">
                @for ($i = 0; $i < 3; $i++)
                    <button
                        type="button"
                        aria-label="Go to slide {{ $i + 1 }}"
                        class="h-1.5 rounded-full transition-all"
                        :class="index === {{ $i }} ? 'w-6 bg-gold' : 'w-1.5 bg-white/40 hover:bg-white/70'"
                        @click="index = {{ $i }}"
                    ></button>
                @endfor
            </div>
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
                Three simple steps. No jargon, no hidden fees.
            </p>
            <div class="mt-8 grid gap-4 md:grid-cols-3">
                @foreach ([
                    ['n' => '1', 't' => 'Browse auctions', 'd' => 'Explore items with live countdowns, transparent bid fees, and past sale prices.'],
                    ['n' => '2', 't' => 'Place your bid', 'd' => 'Pay a small bid fee via M-Pesa. Instantly confirmed by SMS or WhatsApp.'],
                    ['n' => '3', 't' => 'Lowest unique bid wins', 'd' => 'When the auction closes, the lowest unique bid takes the prize at that bid price.'],
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
            <h2 class="text-2xl font-bold text-navy sm:text-3xl">Recent winners</h2>
            <a href="{{ route('results') }}" class="text-sm font-medium text-navy hover:underline">All results →</a>
        </div>
        <x-scroll-row class="mt-6">
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

    <section class="bg-surface">
        <div class="mx-auto max-w-7xl px-4 py-14">
            <div class="flex items-end justify-between">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Also from UniqBid</div>
                    <h2 class="mt-1 text-2xl font-bold text-navy sm:text-3xl">Accessories, hampers & gifts</h2>
                </div>
                <a href="{{ route('shop.index') }}" class="hidden text-sm font-medium text-navy hover:underline sm:inline">Visit shop →</a>
            </div>
            <x-scroll-row class="mt-6">
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

    <section class="mx-auto max-w-7xl px-4 py-14">
        <div class="rounded-2xl bg-navy p-8 text-navy-foreground sm:p-12">
            <div class="grid gap-8 md:grid-cols-2 md:items-center">
                <div>
                    <div class="flex items-center gap-2 text-gold">
                        <flux:icon.check-circle class="size-5" />
                        <span class="text-xs font-semibold uppercase tracking-wider">Regulated & transparent</span>
                    </div>
                    <h2 class="mt-3 text-2xl font-bold sm:text-3xl">A real auctioneer. Real items. Real winners.</h2>
                    <p class="mt-3 max-w-md text-sm opacity-85">
                        UniqBid is registered under the Auctioneers Act with published rules, a versioned privacy policy, and public sale results — because trust is earned.
                    </p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ([
                        'Licence No. AUC/2024/00847',
                        'Data Protection Act, 2019 compliant',
                        'Public results ledger',
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
