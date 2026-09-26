@php
    $settings = \App\Models\SiteSetting::current();

    $linkGroups = [
        'Auctions' => [
            ['route' => 'auctions.index', 'label' => 'Live auctions', 'icon' => 'bolt'],
            ['route' => 'how-it-works', 'label' => 'How it works', 'icon' => 'book-open'],
            ['route' => 'past-sales', 'label' => 'Past sales', 'icon' => 'clock'],
            ['route' => 'testimonials', 'label' => 'Testimonials', 'icon' => 'chat-bubble-left-right'],
        ],
        'Shop & support' => [
            ['route' => 'shop.index', 'label' => 'Shop', 'icon' => 'shopping-bag'],
            ['route' => 'redeem', 'label' => 'Redeem', 'icon' => 'banknotes'],
            ['route' => 'account', 'label' => 'My account', 'icon' => 'user'],
            ['route' => 'faqs', 'label' => 'FAQs', 'icon' => 'question-mark-circle'],
            ['route' => 'contact', 'label' => 'Contact support', 'icon' => 'inbox'],
            ['route' => 'legal', 'label' => 'Terms & privacy', 'icon' => 'document-text'],
        ],
    ];
@endphp

<footer class="relative mt-16 overflow-hidden border-t border-white/10 bg-navy text-navy-foreground">
    <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-gold/70 to-transparent" aria-hidden="true"></div>
    <div class="absolute -right-24 top-0 size-72 rounded-full bg-gold/10 blur-3xl" aria-hidden="true"></div>
    <div class="absolute -left-16 bottom-0 size-56 rounded-full bg-white/5 blur-3xl" aria-hidden="true"></div>

    <div class="relative mx-auto max-w-7xl px-4 py-14 lg:py-16">
        <div class="grid gap-10 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1.85fr)] lg:gap-12">
            <div class="rounded-2xl border border-white/10 bg-white/5 p-6 backdrop-blur-sm sm:p-7">
                <div class="flex items-start gap-4">
                    <img
                        src="/images/brand/uniqbid-logo.jpeg"
                        alt="UniqBid"
                        class="h-16 w-16 shrink-0 rounded-xl bg-white object-contain p-1 shadow-lg shadow-black/20"
                    />
                    <div>
                        <div class="text-lg font-bold tracking-tight">
                            <span class="text-white">Uniq</span><span class="text-gold">Bid</span>
                        </div>
                        <p class="mt-1 text-xs font-medium uppercase tracking-wider text-white/60">Kenya</p>
                    </div>
                </div>

                <p class="mt-5 text-sm leading-relaxed text-white/75">
                    Transparent lowest-unique sales and a curated shop. Genuine items, clear rules, and M-Pesa-ready checkout.
                </p>

                <div class="mt-5 inline-flex items-center gap-2 rounded-full border border-gold/30 bg-gold/10 px-3 py-1.5 text-xs font-medium text-gold">
                    <flux:icon.shield-check class="size-4" />
                    L No. AUC/2024/00847
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    <flux:button href="{{ route('auctions.index') }}" size="sm" class="bg-accent! text-accent-foreground! hover:bg-accent/90!">
                        Browse live sales
                    </flux:button>
                    <flux:button href="{{ route('faqs') }}" size="sm" variant="ghost" class="border border-white/20 text-white! hover:bg-white/10!">
                        Read FAQs
                    </flux:button>
                </div>
            </div>

            <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($linkGroups as $heading => $links)
                    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
                        <h2 class="text-xs font-semibold uppercase tracking-[0.18em] text-gold">{{ $heading }}</h2>
                        <ul class="mt-4 space-y-1">
                            @foreach ($links as $link)
                                <li>
                                    <a
                                        href="{{ route($link['route']) }}"
                                        class="group flex items-center gap-2.5 rounded-lg px-2 py-2 text-sm text-white/80 transition hover:bg-white/5 hover:text-gold"
                                    >
                                        <flux:icon :icon="$link['icon']" class="size-4 shrink-0 text-white/45 transition group-hover:text-gold" />
                                        <span>{{ $link['label'] }}</span>
                                        <flux:icon.arrow-right class="ms-auto size-3.5 opacity-0 transition group-hover:translate-x-0.5 group-hover:opacity-100" />
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach

                <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 sm:col-span-2 xl:col-span-1">
                    <h2 class="text-xs font-semibold uppercase tracking-[0.18em] text-gold">Get in touch</h2>
                    <div class="mt-4 space-y-3">
                        <a href="{{ $settings->phoneTel() }}" class="flex items-start gap-3 rounded-xl border border-white/10 bg-white/5 p-3 transition hover:border-gold/30 hover:bg-white/10">
                            <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-gold/15 text-gold">
                                <flux:icon.phone class="size-4" />
                            </span>
                            <span>
                                <span class="block text-xs text-white/50">Phone</span>
                                <span class="block text-sm font-medium text-white">{{ $settings->phone }}</span>
                            </span>
                        </a>
                        <a href="{{ $settings->whatsappUrl() }}" target="_blank" rel="noopener noreferrer" class="flex items-start gap-3 rounded-xl border border-white/10 bg-white/5 p-3 transition hover:border-gold/30 hover:bg-white/10">
                            <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-gold/15 text-gold">
                                <flux:icon.chat-bubble-left-right class="size-4" />
                            </span>
                            <span>
                                <span class="block text-xs text-white/50">WhatsApp</span>
                                <span class="block text-sm font-medium text-white">Chat with support</span>
                            </span>
                        </a>
                        <a href="mailto:{{ $settings->email }}" class="flex items-start gap-3 rounded-xl border border-white/10 bg-white/5 p-3 transition hover:border-gold/30 hover:bg-white/10">
                            <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-gold/15 text-gold">
                                <flux:icon.envelope class="size-4" />
                            </span>
                            <span class="min-w-0">
                                <span class="block text-xs text-white/50">Email</span>
                                <span class="block break-all text-sm font-medium text-white">{{ $settings->email }}</span>
                            </span>
                        </a>
                    </div>
                    <p class="mt-4 rounded-lg border border-white/10 bg-navy/40 px-3 py-2 text-xs leading-relaxed text-white/60">
                        <flux:icon.clock class="mb-0.5 inline size-3.5 text-gold" />
                        {{ $settings->hours }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="relative border-t border-white/10 bg-black/20">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-5 text-xs text-white/55 sm:flex-row sm:items-center sm:justify-between">
            <div>&copy; {{ now()->year }} UniqBid Auction Ltd. All rights reserved.</div>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                <span class="inline-flex items-center gap-1.5">
                    <flux:icon.shield-check class="size-3.5 text-gold/80" />
                    Kenya Data Protection Act, 2019
                </span>
                <a href="{{ route('legal') }}" class="transition hover:text-gold">Legal & compliance</a>
            </div>
        </div>
    </div>
</footer>
