<?php

use App\Support\Money;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.site')]
#[Title('How UniqBid Works — Lowest Unique Bid Auctions in Kenya')]
class extends Component {
    public string $lang = 'en';
}; ?>

<div class="bg-surface">
    <div class="mx-auto max-w-5xl px-4 py-12">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-3xl font-bold text-navy sm:text-4xl">
                {{ $lang === 'en' ? 'How UniqBid works' : 'UniqBid Inavyofanya Kazi' }}
            </h1>
            <div class="inline-flex rounded-md border border-border bg-white p-1 text-xs">
                <button type="button" wire:click="$set('lang', 'en')" @class(['rounded px-2.5 py-1', 'bg-navy text-navy-foreground' => $lang === 'en', 'text-muted-foreground' => $lang !== 'en'])>English</button>
                <button type="button" wire:click="$set('lang', 'sw')" @class(['rounded px-2.5 py-1', 'bg-navy text-navy-foreground' => $lang === 'sw', 'text-muted-foreground' => $lang !== 'sw'])>Kiswahili</button>
            </div>
        </div>

        <p class="mt-3 max-w-2xl text-base text-muted-foreground">
            {{ $lang === 'en'
                ? 'The lowest unique bid secures the item. Unique means no one else placed exactly that amount. Here\'s the whole idea in one page.'
                : 'Zabuni ya chini kabisa isiyo na mfano ndiyo inayopata bidhaa. Hii ndio dhana yote katika ukurasa mmoja.' }}
        </p>

        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['n' => 1, 't' => 'Browse', 'd' => 'See product value, the bid range, and the small participation fee for each sale.'],
                ['n' => 2, 't' => 'Enter a bid amount', 'd' => 'Choose any amount inside the range. You can place multiple bids at different amounts.'],
                ['n' => 3, 't' => 'Pay participation fee', 'd' => 'Pay the participation fee now to confirm that bid. It is separate from your bid amount.'],
                ['n' => 4, 't' => 'Successful buy', 'd' => 'At close, the lowest unique bid secures the item. That buyer then pays their bid amount on collection or delivery.'],
            ] as $step)
                <div class="rounded-2xl border border-border bg-white p-6 shadow-sm">
                    <div class="grid h-10 w-10 place-items-center rounded-full bg-gold font-bold text-gold-foreground">{{ $step['n'] }}</div>
                    <h3 class="mt-4 text-lg font-semibold text-navy">{{ $step['t'] }}</h3>
                    <p class="mt-2 text-sm text-muted-foreground">{{ $step['d'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="mt-10 rounded-2xl border border-border bg-white p-6 shadow-sm">
            <h2 class="text-xl font-bold text-navy">A worked example</h2>
            <p class="mt-1 text-sm text-muted-foreground">Five people bid on the same item. Who secures the buy?</p>
            <div class="mt-4 divide-y divide-border">
                @foreach ([
                    ['name' => 'Bidder A', 'bid' => 15, 'unique' => false],
                    ['name' => 'Bidder B', 'bid' => 15, 'unique' => false],
                    ['name' => 'Bidder C', 'bid' => 22, 'unique' => true],
                    ['name' => 'Bidder D', 'bid' => 41, 'unique' => true],
                    ['name' => 'Bidder E', 'bid' => 50, 'unique' => true],
                ] as $bidder)
                    @php $isSuccessfulBuy = $bidder['bid'] === 22; @endphp
                    <div @class(['flex items-center justify-between py-3', 'rounded-md bg-gold/10 px-3' => $isSuccessfulBuy])>
                        <div class="flex items-center gap-3">
                            <div class="grid h-8 w-8 place-items-center rounded-full bg-navy text-xs font-bold text-navy-foreground">{{ substr($bidder['name'], -1) }}</div>
                            <span class="text-sm font-medium">{{ $bidder['name'] }}</span>
                            @unless ($bidder['unique'])
                                <span class="rounded-full bg-muted px-2 py-0.5 text-[10px] font-semibold text-muted-foreground">tied — not unique</span>
                            @endunless
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-sm font-semibold">{{ Money::kes($bidder['bid']) }}</span>
                            @if ($isSuccessfulBuy)
                                <span class="inline-flex items-center gap-1 rounded-full bg-gold px-2.5 py-1 text-xs font-bold text-gold-foreground">
                                    <flux:icon.check-circle class="size-3.5" /> Successful buy
                                </span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="mt-4 text-sm text-zinc-800">
                Each bidder also paid a separate participation fee when placing their bid. Bidder C then pays <strong>{{ Money::kes(22) }}</strong> on collection or delivery — not the product’s retail value. A and B tied at KES 15, so those bids were not unique.
            </p>
        </div>

        <div class="mt-10 grid gap-4 md:grid-cols-2">
            <div class="rounded-2xl border border-border bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-navy">What you pay</h3>
                <p class="mt-2 text-sm text-muted-foreground">
                    <strong>Participation fee</strong> — paid now for each bid you place.<br>
                    <strong>Bid amount</strong> — the number you enter in the range; paid later only if it is the successful bid.<br>
                    <strong>Product value</strong> — shown for comparison only; never charged as a bid payment.
                </p>
            </div>
            <div class="rounded-2xl border border-border bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-navy">Transparency</h3>
                <p class="mt-2 text-sm text-muted-foreground">
                    Live bid counts and sale activity appear on auction pages. Past sales are published publicly. This is a products-selling platform — not gambling.
                </p>
            </div>
        </div>

        <div class="mt-10 text-center">
            <flux:button href="{{ route('auctions.index') }}" class="bg-accent! text-accent-foreground!">
                Browse live auctions
            </flux:button>
        </div>
    </div>
</div>
