@php
    $nav = [
        ['route' => 'home', 'label' => 'Home', 'match' => 'home'],
        ['route' => 'auctions.index', 'label' => 'Live Auctions', 'match' => 'auctions.*'],
        ['route' => 'shop.index', 'label' => 'Shop', 'match' => 'shop.*'],
        ['route' => 'how-it-works', 'label' => 'How It Works', 'match' => 'how-it-works'],
        ['route' => 'results', 'label' => 'Results', 'match' => 'results'],
        ['route' => 'contact', 'label' => 'Contact', 'match' => 'contact'],
    ];
    $cartCount = app(\App\Services\CartService::class)->count();
@endphp

<header
    class="sticky top-0 z-40 border-b border-border bg-white/85 backdrop-blur"
    x-data="{ open: false }"
>
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4">
        <a href="{{ route('home') }}" class="flex items-center gap-2">
            <div class="grid h-8 w-8 place-items-center rounded-md bg-navy text-navy-foreground">
                <flux:icon.scale class="size-4" />
            </div>
            <div class="leading-tight">
                <div class="text-base font-bold text-navy">UniqBid</div>
                <div class="text-[10px] font-medium text-muted-foreground">Licensed Auctioneer · KE</div>
            </div>
        </a>

        <nav class="hidden items-center gap-1 lg:flex">
            @foreach ($nav as $item)
                @php
                    $active = request()->routeIs($item['match']);
                @endphp
                <a
                    href="{{ route($item['route']) }}"
                    @class([
                        'rounded-md px-3 py-2 text-sm font-medium transition',
                        'text-navy' => $active,
                        'text-muted-foreground hover:text-zinc-900' => ! $active,
                    ])
                >
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="flex items-center gap-2">
            <a href="{{ route('cart') }}" class="relative rounded-md p-2 text-zinc-900 hover:bg-muted">
                <flux:icon.shopping-cart class="size-5" />
                @if ($cartCount > 0)
                    <span class="absolute -right-0.5 -top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-gold px-1 text-[10px] font-bold text-gold-foreground">
                        {{ $cartCount }}
                    </span>
                @endif
            </a>

            <a href="{{ route('account') }}" class="hidden rounded-md p-2 text-zinc-900 hover:bg-muted sm:block">
                <flux:icon.user class="size-5" />
            </a>

            <a href="{{ route('auctions.index') }}" class="hidden sm:inline-flex">
                <flux:button class="bg-gold! text-gold-foreground! hover:bg-gold/90!">
                    Place Your Bid
                </flux:button>
            </a>

            <button
                type="button"
                class="rounded-md p-2 lg:hidden"
                @click="open = !open"
                aria-label="Menu"
            >
                <flux:icon.bars-3 class="size-5" x-show="!open" />
                <flux:icon.x-mark class="size-5" x-cloak x-show="open" />
            </button>
        </div>
    </div>

    <div class="border-t border-border bg-white lg:hidden" x-cloak x-show="open">
        <div class="mx-auto flex max-w-7xl flex-col p-2">
            @foreach ($nav as $item)
                <a
                    href="{{ route($item['route']) }}"
                    class="rounded-md px-3 py-2.5 text-sm font-medium text-zinc-900 hover:bg-muted"
                    @click="open = false"
                >
                    {{ $item['label'] }}
                </a>
            @endforeach
            <a href="{{ route('account') }}" class="rounded-md px-3 py-2.5 text-sm font-medium text-zinc-900 hover:bg-muted" @click="open = false">
                My Account
            </a>
            <a href="{{ route('auctions.index') }}" class="mt-2" @click="open = false">
                <flux:button class="w-full bg-gold! text-gold-foreground! hover:bg-gold/90!">
                    Place Your Bid
                </flux:button>
            </a>
        </div>
    </div>
</header>
