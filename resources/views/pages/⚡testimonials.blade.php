<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.site')]
#[Title('Testimonials — What UniqBid Customers Say')]
class extends Component {
    public function with(): array
    {
        return [
            'testimonials' => [
                [
                    'name' => 'Amina Wanjiku',
                    'location' => 'Nairobi',
                    'role' => 'Successful buyer',
                    'item' => 'Samsung 55" QLED TV',
                    'quote' => 'I secured a TV for less than the price of a month\'s data bundles. The countdown was clear, M-Pesa was instant, and the delivery team called the same day.',
                    'rating' => 5,
                ],
                [
                    'name' => 'Brian Otieno',
                    'location' => 'Kisumu',
                    'role' => 'Regular bidder',
                    'item' => 'iPhone 15 Pro',
                    'quote' => 'Finally an auction site that shows the bid fee up front. I placed three bids, got WhatsApp receipts every time, and understood the outcome clearly when past sales went public.',
                    'rating' => 5,
                ],
                [
                    'name' => 'Faith Chebet',
                    'location' => 'Eldoret',
                    'role' => 'Shop customer',
                    'item' => 'Leather Cardholder',
                    'quote' => 'Came for the auctions, stayed for the shop. Ordered a gift hamper for my sister — quality packaging and it arrived in Eldoret in two days.',
                    'rating' => 5,
                ],
                [
                    'name' => 'Daniel Kamau',
                    'location' => 'Thika',
                    'role' => 'Successful buyer',
                    'item' => 'PlayStation 5 Bundle',
                    'quote' => 'I was sceptical about "lowest unique bid", but the worked example on How It Works made sense. Secured a PS5 for a fraction of retail. UniqBid feels legit.',
                    'rating' => 5,
                ],
                [
                    'name' => 'Grace Njeri',
                    'location' => 'Mombasa',
                    'role' => 'First-time bidder',
                    'item' => 'Dyson Airwrap',
                    'quote' => 'Support replied on WhatsApp within an hour when I mistyped my bid amount. They walked me through the rules without pressure. That trust made me place another bid.',
                    'rating' => 4,
                ],
                [
                    'name' => 'Joseph Mutua',
                    'location' => 'Nakuru',
                    'role' => 'Successful buyer',
                    'item' => 'MacBook Air M3',
                    'quote' => 'The public past sales ledger sold me. I checked earlier sales before bidding and saw real prices. After my successful buy, collection at their Nairobi depot was smooth.',
                    'rating' => 5,
                ],
            ],
        ];
    }
}; ?>

<div class="bg-surface">
    <div class="mx-auto max-w-7xl px-4 py-12">
        <div class="max-w-2xl">
            <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Customer stories</p>
            <h1 class="mt-2 text-3xl font-bold text-navy sm:text-4xl">What people say about UniqBid</h1>
            <p class="mt-3 text-base text-muted-foreground">
                Placeholder reviews from Kenyan bidders and shoppers while we collect live feedback. Replace these with real customer quotes when ready.
            </p>
        </div>

        <div class="mt-10 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($testimonials as $item)
                <article class="flex flex-col rounded-2xl border border-border bg-white p-6 shadow-sm">
                    <div class="flex items-center gap-1 text-gold" aria-label="{{ $item['rating'] }} out of 5 stars">
                        @for ($i = 1; $i <= 5; $i++)
                            <flux:icon.star
                                @class([
                                    'size-4',
                                    'fill-current' => $i <= $item['rating'],
                                    'opacity-30' => $i > $item['rating'],
                                ])
                            />
                        @endfor
                    </div>

                    <blockquote class="mt-4 flex-1 text-sm leading-relaxed text-zinc-800">
                        “{{ $item['quote'] }}”
                    </blockquote>

                    <div class="mt-6 flex items-center gap-3 border-t border-border pt-4">
                        <div class="grid h-10 w-10 place-items-center rounded-full bg-navy text-sm font-bold text-navy-foreground">
                            {{ strtoupper(substr($item['name'], 0, 1)) }}
                        </div>
                        <div class="min-w-0">
                            <div class="truncate text-sm font-semibold text-navy">{{ $item['name'] }}</div>
                            <div class="truncate text-xs text-muted-foreground">
                                {{ $item['location'] }} · {{ $item['role'] }}
                            </div>
                            <div class="mt-0.5 truncate text-xs font-medium text-zinc-600">{{ $item['item'] }}</div>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-12 rounded-2xl border border-border bg-white p-8 text-center shadow-sm sm:p-10">
            <h2 class="text-xl font-bold text-navy sm:text-2xl">Ready to place a bid on a live auction?</h2>
            <p class="mx-auto mt-2 max-w-lg text-sm text-muted-foreground">
                Browse open sales, pay for your bid with M-Pesa, and see why Kenyans keep coming back.
            </p>
            <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                <flux:button href="{{ route('auctions.index') }}" class="bg-accent! text-accent-foreground!">
                    Browse live auctions
                </flux:button>
                <flux:button href="{{ route('past-sales') }}" variant="outline">
                    See past sales
                </flux:button>
            </div>
        </div>
    </div>
</div>
