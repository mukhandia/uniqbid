<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.site')]
#[Title('Legal & Compliance: UniqBid')]
class extends Component {
    //
}; ?>

@php
    $sections = [
        [
            'id' => 'terms',
            'title' => 'Terms & Conditions',
            'clauses' => [
                ['1.1 Parties and acceptance', 'These Terms are a binding agreement between UniqBid Auction Limited, of Westlands, Nairobi, and each person who registers, places a bid, or purchases an item through the platform. By using the platform, you confirm that you are at least 18, act on your own behalf, and accept these Terms, the Selling Rules, Privacy Policy, and Refund Policy.'],
                ['1.2 Eligibility and account integrity', 'One natural person may hold one account. We may request national ID or KRA PIN verification to prevent duplicate accounts, underage use, fraud, or bid manipulation. You are responsible for your account activity and must report unauthorised access to support@uniqbid.co.ke.'],
                ['1.3–1.5 Platform role and responsible participation', 'A bid does not guarantee a successful outcome. Bid fees are participation costs, not an investment or income source. Users may set daily or monthly spend limits and request self-exclusion through support for a minimum of 30 days.'],
                ['1.6–1.9 Intellectual property, liability, disputes and changes', 'Platform content is owned by or licensed to UniqBid. Our aggregate liability is limited to payments made in the three months before a claim, where law permits. Raise disputes with support first; unresolved disputes may be mediated in Nairobi under Kenyan law. We give at least 7 days’ notice of material changes.'],
            ],
        ],
        [
            'id' => 'rules',
            'title' => 'Selling Rules',
            'clauses' => [
                ['2.1 Lowest-unique-bid sale mechanism', 'Each sale lists an item, a whole-KES bid range, and a fixed increment. At close, duplicate bid amounts are excluded; the lowest remaining unique amount secures the item. If no unique amount exists, the published tie-break rule applies.'],
                ['2.2–2.3 Placing a bid and bid fees', 'Each bid costs the displayed bid fee and is counted only after payment and a reference are confirmed. Bids cannot be edited or cancelled after confirmation. Bid fees are separate from the sale price and are not credited against it.'],
                ['2.4–2.5 Sale close, results and odds', 'Sales close according to the displayed East Africa Time server clock. Results, including the successful bid and a masked identifier, are published within 24 hours. The successful buyer has seven days to pay the sale amount and confirm delivery. Bid count, distinct unique amounts, and an indicative probability statement are displayed for transparency.'],
                ['2.6–2.8 Ties, void sales and prohibited conduct', 'The sale listing states whether no-unique-bid sales are extended, awarded by the fewest duplicates, or voided. We may void, extend, or re-run a sale for errors, manipulation, unavailable items, or regulatory requirements. Bots, multiple accounts, collusion, and attempts to manipulate the mechanism are prohibited.'],
                ['2.9 Item condition and delivery', 'Items are new unless stated otherwise and must be accurately described. Delivery within Nairobi and Westlands collection are available. Delivery fee charges apply outside Nairobi and are confirmed at collection or delivery.'],
            ],
        ],
        [
            'id' => 'privacy',
            'title' => 'Privacy Policy',
            'clauses' => [
                ['3.1–3.2 Controller and data collected', 'UniqBid Auction Limited is the data controller under the Data Protection Act, 2019. We collect identity, contact, transaction, technical, and support-communication data needed to operate the platform. We do not knowingly collect data from people under 18.'],
                ['3.3–3.6 Purpose, payments and sharing', 'We process data to record bids, process payments, fulfil orders, prevent fraud, meet legal duties, and, only with consent, send marketing. We receive payment confirmation data but never M-Pesa PINs. Data is shared only with payment, delivery, hosting, and legally authorised service providers.'],
                ['3.7–3.11 Retention, rights, automation and cookies', 'Transaction records are retained for seven years; inactive account data for three years unless a longer period is required. You may request access, correction, erasure where permitted, restriction, objection, portability, or human review of a sale result. Essential cookies operate the site; analytics and marketing cookies require consent. Contact dpo@uniqbid.co.ke or support@uniqbid.co.ke.'],
            ],
        ],
        [
            'id' => 'refunds',
            'title' => 'Refund Policy',
            'clauses' => [
                ['4.1–4.3 Participation and sale payments', 'Bid fees are non-refundable except for voided sales, erroneous charges, or where required by law. A sale price paid for a cancelled sale is refunded in full within seven business days, subject to the Cancellation of Sale Policy.'],
                ['4.4–4.8 Shop returns and refund process', 'Direct shop purchases may be returned within seven days of delivery if unused, in original packaging, and supported by proof of purchase; exclusions apply to perishables, customised goods, and clearly marked non-returnable items. Faulty, damaged, or materially misdescribed goods may qualify for refund or replacement. Contact support with the order or sale reference and M-Pesa code. Approved refunds return to the original payment method.'],
            ],
        ],
        [
            'id' => 'cancellation',
            'title' => 'Cancellation of Sale Policy',
            'clauses' => [
                ['5.1–5.4 When a sale can be cancelled', 'We may cancel a sale for item unavailability, a genuine listing error, failed age or identity verification, credible fraud, non-payment within the published period, or a regulatory requirement. Before dispatch, shop purchases may be cancelled without charge; after dispatch, return rights apply.'],
                ['5.5–5.7 Effect and records', 'Where we cancel for a reason other than your non-payment or confirmed fraud, sale price and delivery fees already paid are refunded within seven business days. We may re-offer an item after non-payment, fraud, or an accepted cancellation. Cancellation records are retained with transaction records for seven years.'],
            ],
        ],
        [
            'id' => 'clearance-house',
            'title' => 'Corporate Seller Accounts',
            'clauses' => [
                ['6.1–6.7 UniqBid Clearance House', 'Clearance House is a programme for registered companies selling verified returns, overstock, or liquidation inventory. Sellers must provide incorporation, KRA, payout, ownership, and item-condition evidence. Stolen, counterfeit, unsafe, recalled, or inaccurately described goods are prohibited. Buyer protections remain unchanged.'],
            ],
        ],
        [
            'id' => 'licence',
            'title' => 'Licence Information',
            'clauses' => [
                ['7.1–7.6 Corporate and regulatory information', 'UniqBid Auction Limited is a Kenyan company with its registered office in Westlands, Nairobi. It holds auctioneer licence AUC/2024/00847 under the Auctioneers Act (Cap 526). The platform sells genuine goods through a transparent, rule-based competitive-sale mechanism; it is not a gambling, betting, or gaming service. Data-handling complaints may be referred to the ODPC, and consumer or auctioneering complaints to the relevant Kenyan regulators.'],
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
        <span>Version 2.1</span>
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

    <div class="mt-4 flex items-start gap-3 rounded-2xl border border-border bg-muted p-4 text-sm text-zinc-800">
        <flux:icon.document-arrow-down class="mt-0.5 size-5 shrink-0 text-navy" />
        <p>
            Read the complete published framework, including the full clause text:
            <a href="/documents/uniqbid-legal-compliance-framework.pdf" target="_blank" rel="noopener" class="font-semibold text-navy underline underline-offset-2">
                Download the Legal & Compliance Framework (PDF)
            </a>.
        </p>
    </div>

    <div class="mt-8 space-y-8">
        @foreach ($sections as $section)
            <section id="{{ $section['id'] }}" class="scroll-mt-24">
                <h2 class="text-xl font-bold text-navy">{{ $section['title'] }}</h2>
                <div class="mt-3 space-y-4 text-sm leading-relaxed text-zinc-800">
                    @foreach ($section['clauses'] as [$heading, $paragraph])
                        <div>
                            <h3 class="font-semibold text-zinc-900">{{ $heading }}</h3>
                            <p class="mt-1">{{ $paragraph }}</p>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</div>
