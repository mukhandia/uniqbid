<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.site')]
#[Title('Legal & Compliance — UniqBid')]
class extends Component {
    //
}; ?>

@php
    $sections = [
        [
            'id' => 'terms',
            'title' => 'Terms & Conditions',
            'body' => [
                'These terms govern your use of the UniqBid platform operated by UniqBid Auction Ltd, a company registered in Kenya.',
                'By placing a bid or purchasing from the shop you agree to be bound by these terms, the Auction Rules, and the Privacy Policy.',
                'UniqBid reserves the right to update these terms; the current version is always displayed with an effective date.',
            ],
        ],
        [
            'id' => 'rules',
            'title' => 'Auction Rules',
            'body' => [
                'Auctions use a lowest-unique-bid mechanism. At the published close time, the lowest bid amount placed by exactly one bidder secures the item at that bid amount.',
                'Each bid requires payment of the stated bid fee. Bid fees are non-refundable.',
                'You may place multiple bids at different amounts within the stated range and increments.',
                'In the event of a system error affecting an auction, UniqBid may extend, void, or re-run the auction and refund related bid fees.',
            ],
        ],
        [
            'id' => 'privacy',
            'title' => 'Privacy Policy',
            'body' => [
                'This policy is issued in accordance with the Kenya Data Protection Act, 2019.',
                'We collect only the personal data necessary to process bids and orders: name, mobile number, and — where applicable — delivery address.',
                'We use your data to record bids, process M-Pesa payments, send SMS/WhatsApp confirmations, and comply with legal obligations.',
                'You may request access, correction, or deletion of your personal data at any time via support@uniqbid.co.ke.',
            ],
        ],
        [
            'id' => 'refunds',
            'title' => 'Refund Policy',
            'body' => [
                'Bid fees are non-refundable except where an auction is voided by UniqBid.',
                'Shop purchases may be returned within 7 days of delivery if unused and in original packaging. Refunds are processed via M-Pesa.',
            ],
        ],
        [
            'id' => 'licence',
            'title' => 'Licence Information',
            'body' => [
                'UniqBid Auction Ltd is a licensed auctioneer registered under the Auctioneers Act (Cap 526) of the Laws of Kenya.',
                'Licence Number: AUC/2024/00847. Registered office: Westlands, Nairobi.',
            ],
        ],
    ];
@endphp

<div class="mx-auto max-w-4xl px-4 py-10">
    <div class="flex items-center gap-3">
        <flux:icon.document-text class="size-6 text-navy" />
        <h1 class="text-3xl font-bold text-navy">Legal & Compliance</h1>
    </div>
    <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
        <span>Version 1.0</span>
        <span>·</span>
        <span>Effective 1 July 2026</span>
        <span>·</span>
        <span class="inline-flex items-center gap-1">
            <flux:icon.shield-check class="size-3.5 text-success" />
            Kenya Data Protection Act, 2019
        </span>
    </div>

    <div class="mt-6 rounded-2xl border border-border bg-white p-4">
        <div class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Contents</div>
        <ul class="mt-2 grid gap-1 sm:grid-cols-2">
            @foreach ($sections as $section)
                <li>
                    <a href="#{{ $section['id'] }}" class="text-sm text-navy hover:underline">{{ $section['title'] }}</a>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="mt-8 space-y-8">
        @foreach ($sections as $section)
            <section id="{{ $section['id'] }}" class="scroll-mt-24">
                <h2 class="text-xl font-bold text-navy">{{ $section['title'] }}</h2>
                <div class="mt-3 space-y-3 text-sm leading-relaxed text-zinc-800">
                    @foreach ($section['body'] as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</div>
