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

    $featured = Auction::query()->with('images')->live()->featured()->first()
        ?? Auction::query()->with('images')->live()->orderBy('ends_at')->first();
    $grid = Auction::query()->with('images')->live()->orderBy('ends_at')->limit(6)->get();
    $recentSales = AuctionResult::query()->orderByDesc('sold_on')->limit(4)->get();
    $featuredProducts = Product::query()->featured()->limit(4)->get();
@endphp

<div>
    <section class="relative overflow-hidden bg-navy text-navy-foreground">
        <div
            class="absolute inset-0 opacity-30"
            style="background-image: radial-gradient(circle at 20% 20%, rgba(217,180,74,0.35), transparent 45%), radial-gradient(circle at 80% 60%, rgba(255,255,255,0.08), transparent 40%)"
        ></div>

        <div class="relative mx-auto grid max-w-7xl gap-10 px-4 py-14 md:grid-cols-2 md:py-20">
            <div class="flex flex-col justify-center">
                <span class="inline-flex w-fit items-center gap-2 rounded-full border border-white/20 bg-white/5 px-3 py-1 text-xs font-medium">
                    <flux:icon.shield-check class="size-3.5 text-gold" />
                    Licensed Kenyan Auctioneer · Est. 2024
                </span>
                <h1 class="mt-4 text-4xl font-bold leading-tight sm:text-5xl">
                    The lowest <span class="text-gold">unique bid</span> wins.
                </h1>
                <p class="mt-4 max-w-md text-base opacity-85">
                    Bid transparently on real items — phones, TVs, motorbikes — from as little as KES 20. Instantly pay via M-Pesa and get SMS or WhatsApp confirmation.
                </p>
                <div class="mt-6 flex flex-wrap gap-3">
                    @if ($featured)
                        <flux:button
                            href="{{ route('auctions.show', $featured) }}"
                            class="bg-gold! text-gold-foreground! hover:bg-gold/90!"
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

            @if ($featured)
                <div class="relative">
                    <div class="overflow-hidden rounded-2xl border border-white/15 bg-white/5 shadow-2xl backdrop-blur">
                        <div class="relative aspect-[4/3]">
                            <img src="{{ $featured->primaryImageUrl() }}" alt="{{ $featured->name }}" class="h-full w-full object-cover">
                            <div class="absolute left-3 top-3 rounded-full bg-gold px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-gold-foreground">
                                Featured Auction
                            </div>
                        </div>
                        <div class="p-5">
                            <h3 class="text-lg font-semibold">{{ $featured->name }}</h3>
                            <div class="mt-4 grid grid-cols-3 gap-3 text-center">
                                <div class="rounded-md bg-white/5 p-2">
                                    <div class="text-[10px] uppercase opacity-70">Bid fee</div>
                                    <div class="text-sm font-bold text-gold">{{ Money::kes($featured->bid_fee) }}</div>
                                </div>
                                <div class="rounded-md bg-white/5 p-2">
                                    <div class="text-[10px] uppercase opacity-70">Bids</div>
                                    <div class="text-sm font-bold">{{ number_format($featured->bids_count) }}</div>
                                </div>
                                <div class="rounded-md bg-white/5 p-2">
                                    <div class="text-[10px] uppercase opacity-70">Retail</div>
                                    <div class="text-sm font-bold">{{ Money::kes($featured->retail_value) }}</div>
                                </div>
                            </div>
                            <div class="mt-4 flex items-center justify-between rounded-md bg-black/40 px-3 py-2 text-sm">
                                <span class="opacity-80">Closes in</span>
                                <x-countdown :target="$featured->endsAtMs()" class="font-mono tabular-nums text-gold" />
                            </div>
                            <div class="mt-4">
                                <flux:button
                                    href="{{ route('auctions.show', $featured) }}"
                                    class="w-full bg-gold! text-gold-foreground! hover:bg-gold/90!"
                                >
                                    <flux:icon.scale class="size-4" />
                                    Place Your Bid
                                </flux:button>
                            </div>
                        </div>
                    </div>
                </div>
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
        <div class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($grid as $auction)
                <x-auction-card :auction="$auction" />
            @endforeach
        </div>
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
        <div class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-4">
            @foreach ($recentSales as $sale)
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
            @endforeach
        </div>
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
            <div class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-4">
                @foreach ($featuredProducts as $product)
                    <a href="{{ route('shop.show', $product) }}" class="group overflow-hidden rounded-xl border border-border bg-white shadow-sm transition hover:shadow-md">
                        <div class="aspect-square overflow-hidden bg-muted">
                            <img src="{{ $product->image }}" alt="{{ $product->name }}" class="h-full w-full object-cover transition group-hover:scale-105">
                        </div>
                        <div class="p-3">
                            <div class="text-[11px] text-muted-foreground">{{ $product->category }}</div>
                            <div class="line-clamp-1 text-sm font-semibold">{{ $product->name }}</div>
                            <div class="mt-1 text-sm font-bold text-navy">{{ Money::kes($product->price) }}</div>
                        </div>
                    </a>
                @endforeach
            </div>
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
