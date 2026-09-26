@php
    $nav = [
        ['route' => 'home', 'label' => 'Home', 'match' => 'home'],
        ['route' => 'auctions.index', 'label' => 'Live Sales/Auctions', 'match' => 'auctions.*', 'cta' => true],
        ['route' => 'redeem', 'label' => 'Redeem', 'match' => 'redeem', 'cta' => true],
        ['route' => 'how-it-works', 'label' => 'How It Works', 'match' => 'how-it-works'],
        ['route' => 'past-sales', 'label' => 'Past Sales', 'match' => 'past-sales'],
        ['route' => 'shop.index', 'label' => 'Shop', 'match' => 'shop.*'],
        ['route' => 'testimonials', 'label' => 'Testimonials', 'match' => 'testimonials'],
        ['route' => 'faqs', 'label' => 'FAQs', 'match' => 'faqs'],
        ['route' => 'contact', 'label' => 'Contact', 'match' => 'contact'],
    ];
    $onShop = request()->routeIs('shop.*');
@endphp

<header
    class="sticky top-0 z-40 border-b border-border bg-white/85 backdrop-blur"
    x-data="{
        open: false,
        close() { this.open = false },
        init() {
            window.addEventListener('resize', () => {
                if (window.matchMedia('(min-width: 1280px)').matches) {
                    this.open = false
                }
            })
        },
    }"
    @keydown.escape.window="close()"
>
    <div class="mx-auto max-w-7xl px-3 sm:px-4">
        <div class="flex items-center gap-x-2 py-2.5 sm:gap-x-3 sm:py-3">
            <a href="{{ route('home') }}" class="flex min-w-0 shrink-0 items-center gap-2" aria-label="UniqBid home">
                <img
                    src="/images/brand/uniqbid-icon.jpeg"
                    alt=""
                    class="size-8 shrink-0 rounded-lg object-cover sm:size-9"
                />
                <div class="min-w-0 leading-tight">
                    <div class="truncate text-sm font-bold sm:text-base">
                        <span class="text-navy">Uniq</span><span class="text-accent">Bid</span>
                    </div>
                    <div class="hidden text-[10px] font-medium text-muted-foreground sm:block">Licenced</div>
                </div>
            </a>

            <nav class="hidden min-w-0 flex-1 items-center justify-center gap-2 xl:flex" aria-label="Primary">
                @foreach ($nav as $item)
                    @php
                        $active = request()->routeIs($item['match']);
                        $cta = (bool) ($item['cta'] ?? false);
                    @endphp
                    <a
                        href="{{ route($item['route']) }}"
                        @class([
                            'whitespace-nowrap rounded-md px-2.5 py-1.5 text-sm font-medium transition',
                            'bg-red-600 text-white hover:bg-red-700' => $cta,
                            'bg-red-800 shadow-[inset_0_0_0_2px_rgba(255,255,255,0.85)]' => $cta && $active,
                            'text-navy underline decoration-2 underline-offset-8' => $active && ! $cta,
                            'text-muted-foreground hover:text-zinc-900' => ! $active && ! $cta,
                        ])
                    >
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="ms-auto flex shrink-0 items-center gap-1 sm:gap-1.5">
                <livewire:cart-count />

                <a
                    href="{{ route('account') }}"
                    class="hidden rounded-md p-2 text-zinc-900 hover:bg-muted sm:block"
                    aria-label="My account"
                >
                    <flux:icon.user class="size-5" />
                </a>

                @unless ($onShop)
                    <a href="{{ route('auctions.index') }}" class="hidden sm:inline-flex">
                        <flux:button size="sm" class="bg-accent! text-accent-foreground! hover:bg-accent/90!">
                            Bid now
                        </flux:button>
                    </a>
                @endunless

                <button
                    type="button"
                    class="ms-1 rounded-md p-2 xl:hidden sm:ms-2 sm:border-l sm:border-border sm:ps-3"
                    @click="open = !open"
                    :aria-expanded="open.toString()"
                    aria-controls="site-mobile-nav"
                    aria-label="Menu"
                >
                    <flux:icon.bars-3 class="size-5" x-show="!open" />
                    <flux:icon.x-mark class="size-5" x-cloak x-show="open" />
                </button>
            </div>
        </div>

        <div class="pb-2.5 sm:pb-3">
            <livewire:site-search />
        </div>
    </div>

    <div
        id="site-mobile-nav"
        class="border-t border-border bg-white xl:hidden"
        x-cloak
        x-show="open"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-1"
    >
        <div class="mx-auto flex max-h-[min(70dvh,28rem)] max-w-7xl flex-col gap-2 overflow-y-auto overscroll-contain p-3">
            @foreach ($nav as $item)
                @php
                    $active = request()->routeIs($item['match']);
                    $cta = (bool) ($item['cta'] ?? false);
                @endphp
                <a
                    href="{{ route($item['route']) }}"
                    @class([
                        'rounded-md px-3 py-2.5 text-sm font-medium',
                        'bg-red-600 text-white hover:bg-red-700' => $cta,
                        'bg-red-800 shadow-[inset_0_0_0_2px_rgba(255,255,255,0.85)]' => $cta && $active,
                        'hover:bg-muted' => ! $cta,
                        'text-navy underline decoration-2 underline-offset-4' => $active && ! $cta,
                        'text-zinc-900' => ! $active && ! $cta,
                    ])
                    @click="close()"
                >
                    {{ $item['label'] }}
                </a>
            @endforeach
            <a
                href="{{ route('account') }}"
                class="rounded-md px-3 py-2.5 text-sm font-medium text-zinc-900 hover:bg-muted sm:hidden"
                @click="close()"
            >
                My Account
            </a>
            @unless ($onShop)
                <a href="{{ route('auctions.index') }}" class="mt-1 sm:hidden" @click="close()">
                    <flux:button class="w-full bg-accent! text-accent-foreground! hover:bg-accent/90!">
                        Bid now
                    </flux:button>
                </a>
            @endunless
        </div>
    </div>
</header>
