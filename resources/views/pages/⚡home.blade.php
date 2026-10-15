<?php

use App\Models\Auction;
use App\Models\AuctionResult;
use App\Models\HowItWorksStep;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Services\LiveAuctionOrderingService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.site')]
#[Title('UniqBid: Live Sales in Kenya')]
class extends Component {
    //
}; ?>

@php
    use App\Support\Money;

    $ordering = app(LiveAuctionOrderingService::class);
    $settings = SiteSetting::current();
    Auction::ensureHomepageHero();
    $pollSeconds = min(60, max(5, $settings->auctionShuffleIntervalSeconds()));

    $featured = Auction::query()->with('images')->liveOnAuctionChannel()->featured()->orderBy('ends_at')->first()
        ?? $ordering->orderedLive(1)->first();

    $liveAuctions = $ordering->orderedLive();
    $grid = $liveAuctions->take(10);
    $recentSales = AuctionResult::query()->orderByDesc('sold_on')->limit(10)->get();
    $featuredProducts = Product::query()->inShop()->featured()->orderBy('name')->limit(10)->get();

    $howItWorksSteps = HowItWorksStep::query()->orderBy('sort_order')->orderBy('id')->get();

    if ($howItWorksSteps->isEmpty()) {
        $howItWorksSteps = collect([
            (object) ['step_number' => 1, 'title_en' => 'Browse', 'description_en' => 'See product value, the amount range, and the small participation fee for each sale.'],
            (object) ['step_number' => 2, 'title_en' => 'Enter an amount', 'description_en' => 'Choose any amount inside the range. You can enter multiple amounts.'],
            (object) ['step_number' => 3, 'title_en' => 'Pay participation fee', 'description_en' => 'Pay the participation fee now to confirm that entry. It is separate from your chosen amount.'],
            (object) ['step_number' => 4, 'title_en' => 'Successful buy', 'description_en' => 'At close, the lowest unique amount secures the item. That buyer then pays their amount and can collect the item or redeem for cash.'],
        ]);
    }

    $howItWorksStepStyles = [
        1 => ['border' => 'border-navy/15', 'bg' => 'bg-navy/5'],
        2 => ['border' => 'border-violet-500/20', 'bg' => 'bg-violet-500/5'],
        3 => ['border' => 'border-gold/30', 'bg' => 'bg-gold/10'],
        4 => ['border' => 'border-emerald-500/20', 'bg' => 'bg-emerald-500/5'],
    ];
@endphp

<div>
    <section class="relative overflow-hidden bg-navy text-navy-foreground">
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

        <div class="relative z-10 mx-auto grid max-w-7xl items-center gap-8 px-4 py-10 md:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)] md:gap-10 md:py-12 lg:gap-14">
            <div class="max-w-xl">
                <h1 class="text-3xl font-bold leading-tight sm:text-4xl lg:text-5xl">
                    The lowest <span class="text-gold">unique amount</span> secures the item.
                </h1>
                <p class="mt-4 max-w-md text-base opacity-90">
                    Participate transparently on real items: phones, TVs, motorbikes, from as little as KES 20. Instantly pay via M-Pesa and get SMS or WhatsApp confirmation.
                </p>
                <div class="mt-6 flex flex-wrap gap-3">
                    @if ($featured)
                        <flux:button
                            href="{{ route('auctions.index') }}"
                            class="bg-accent! text-accent-foreground! hover:bg-accent/90!"
                        >
                            Bid now
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
                <div class="mt-6 grid grid-cols-2 gap-4 text-xs sm:grid-cols-4">
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
                    href="{{ route('auctions.index') }}"
                    class="group relative hidden w-full max-w-sm justify-self-end md:block"
                    aria-label="View live auctions featuring {{ $featured->name }}"
                >
                    <img
                        src="{{ $featured->primaryImageUrl() }}"
                        alt="{{ $featured->name }}"
                        class="aspect-[4/3] w-full rounded-2xl border border-white/20 object-cover transition duration-500 group-hover:scale-[1.02]"
                    >
                    <div class="mt-3">
                        <div class="text-lg font-semibold leading-snug">{{ $featured->name }}</div>
                        <div class="mt-1 text-sm opacity-80">
                            Value {{ Money::kes($featured->retail_value) }} · Range {{ Money::kes($featured->min_bid) }}–{{ Money::kes($featured->max_bid) }}
                        </div>
                    </div>
                </a>
            @endif
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-14" wire:poll.{{ $pollSeconds }}s.visible>
        <div class="flex items-end justify-between">
            <div>
                <div class="text-xs font-semibold uppercase tracking-wider text-gold">Live now</div>
                <h2 class="mt-1 text-2xl font-bold text-navy sm:text-3xl">Auctions closing soon</h2>
            </div>
            <a href="{{ route('auctions.index') }}" class="hidden text-sm font-medium text-navy hover:underline sm:inline">See all →</a>
        </div>
        <div class="mt-6 grid grid-cols-2 gap-3 md:hidden" data-test="home-live-grid">
            @foreach ($liveAuctions as $auction)
                <x-auction-card :auction="$auction" class="h-full" wire:key="home-live-grid-{{ $auction->id }}" />
            @endforeach
        </div>
        <x-scroll-row class="mt-6 hidden md:block">
            @foreach ($grid as $auction)
                <x-scroll-row-item>
                    <x-auction-card :auction="$auction" class="h-full" />
                </x-scroll-row-item>
            @endforeach
        </x-scroll-row>
    </section>

    <section class="bg-surface">
        <div class="mx-auto max-w-7xl px-4 py-14">
            <div class="text-center">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-gold">Simple steps</div>
                <h2 class="mt-2 text-2xl font-bold text-navy sm:text-3xl">How UniqBid works</h2>
                <p class="mx-auto mt-3 max-w-2xl text-sm leading-relaxed text-muted-foreground">
                    Product value and amount range up front. Pay a small participation fee per entry. Successful buyers pay their amount later.
                </p>
            </div>

            <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($howItWorksSteps as $step)
                    @php
                        $style = $howItWorksStepStyles[$step->step_number] ?? $howItWorksStepStyles[1];
                        $title = $step instanceof \App\Models\HowItWorksStep ? $step->titleFor('en') : $step->title_en;
                        $description = $step instanceof \App\Models\HowItWorksStep ? $step->descriptionFor('en') : $step->description_en;
                    @endphp
                    <div
                        wire:key="home-step-{{ $step->step_number }}"
                        @class([
                            'flex h-full flex-col rounded-2xl border p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md',
                            $style['border'],
                            $style['bg'],
                        ])
                    >
                        <span class="grid size-10 place-items-center rounded-full bg-navy text-sm font-bold text-navy-foreground">
                            {{ $step->step_number }}
                        </span>
                        <h3 class="mt-4 text-base font-semibold text-navy">{{ $title }}</h3>
                        <p class="mt-2 flex-1 text-sm leading-relaxed text-muted-foreground">{{ $description }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-8 text-center">
                <flux:button href="{{ route('how-it-works') }}" class="bg-accent! text-accent-foreground! hover:bg-accent/90!">
                    See the full guide with worked example
                    <flux:icon.arrow-right class="size-4" />
                </flux:button>
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
                    <x-past-sale-card :sale="$sale" class="h-full" />
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
                        'Data Protection Act, 2019 compliant',
                        'Public past sales ledger',
                        'M-Pesa STK push payments',
                        'Clear selling rules',
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
