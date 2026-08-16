<?php

use App\Models\Auction;
use App\Models\PaymentAttempt;
use App\Services\AuctionBidService;
use App\Support\Money;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.site')]
#[Title('Auction — UniqBid')]
class extends Component {
    public Auction $auction;

    public int $activeImage = 0;

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $amount = '';

    public bool $rulesConsent = false;

    public bool $marketingConsent = false;

    public string $formState = 'idle';

    public string $reference = '';

    public ?int $paymentAttemptId = null;

    public function mount(Auction $auction): void
    {
        abort_unless($auction->status === 'live', 404);

        $this->auction = $auction->load('images');
    }

    public function selectImage(int $index): void
    {
        $this->activeImage = $index;
    }

    public function submitBid(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'min:2'],
            'email' => ['required', 'email'],
            'phone' => ['required', 'regex:/^(\+?254|0)7\d{8}$/'],
            'amount' => ['required', 'integer', 'min:'.$this->auction->min_bid, 'max:'.$this->auction->max_bid],
            'rulesConsent' => ['accepted'],
        ], [
            'phone.regex' => 'Enter a valid Kenyan mobile number.',
            'rulesConsent.accepted' => 'You must accept the rules and privacy policy to place a bid.',
        ]);

        $amount = (int) $this->amount;

        if ($amount % $this->auction->bid_increment !== 0) {
            $this->addError('amount', 'Must be in increments of '.$this->auction->bid_increment);

            return;
        }

        $attempt = app(AuctionBidService::class)->initiate($this->auction, $amount, [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'marketing_consent' => $this->marketingConsent,
        ]);

        $this->paymentAttemptId = $attempt->id;
        $this->reference = $attempt->auctionBid->reference;
        $this->formState = 'pending';
    }

    public function completePlaceholderPayment(): void
    {
        if ($this->formState !== 'pending' || ! $this->paymentAttemptId) {
            return;
        }

        app(AuctionBidService::class)->markSucceeded(PaymentAttempt::query()->findOrFail($this->paymentAttemptId));
        $this->formState = 'success';

        Flux::toast(
            variant: 'success',
            text: 'Participation fee paid for reference '.$this->reference.'.',
        );
    }

    public function failPlaceholderPayment(): void
    {
        if ($this->paymentAttemptId) {
            app(AuctionBidService::class)->markFailed(PaymentAttempt::query()->findOrFail($this->paymentAttemptId));
        }

        $this->formState = 'failure';
    }

    public function resetForm(): void
    {
        $this->formState = 'idle';
        $this->reference = '';
        $this->paymentAttemptId = null;
    }
}; ?>

@php
    $closingSoon = $auction->ends_at->lt(now()->addHour());
    $image = $auction->images[$activeImage] ?? $auction->images->first();
@endphp

<div class="mx-auto max-w-7xl px-4 py-8">
    <div class="text-xs text-muted-foreground">
        <a href="{{ route('auctions.index') }}" class="hover:underline">Live Auctions</a>
        / {{ $auction->category }}
    </div>

    <div class="mt-4 grid gap-8 lg:grid-cols-[1.2fr_1fr]">
        <div>
            <div class="overflow-hidden rounded-2xl border border-border bg-white">
                <div class="aspect-[4/3] bg-muted">
                    @if ($image)
                        <img src="{{ $image->url }}" alt="{{ $auction->name }}" class="h-full w-full object-cover">
                    @endif
                </div>
                @if ($auction->images->count() > 1)
                    <div class="flex gap-2 border-t border-border p-3">
                        @foreach ($auction->images as $index => $thumb)
                            <button
                                type="button"
                                wire:click="selectImage({{ $index }})"
                                @class([
                                    'h-16 w-16 overflow-hidden rounded-md border-2',
                                    'border-gold' => $activeImage === $index,
                                    'border-transparent' => $activeImage !== $index,
                                ])
                            >
                                <img src="{{ $thumb->url }}" alt="" class="h-full w-full object-cover">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <h1 class="mt-6 text-2xl font-bold text-navy sm:text-3xl">{{ $auction->name }}</h1>
            <div class="mt-2 flex flex-wrap items-center gap-3 text-sm">
                <span class="rounded-full bg-navy px-2.5 py-1 text-xs font-semibold text-navy-foreground">{{ $auction->category }}</span>
                <span class="text-muted-foreground">Retail value <span class="font-semibold text-zinc-900">{{ Money::kes($auction->retail_value) }}</span></span>
                <span class="text-muted-foreground">{{ number_format($auction->bids_count) }} bids placed</span>
            </div>

            <p class="mt-4 text-sm leading-relaxed text-zinc-800">{{ $auction->description }}</p>

            <div class="mt-6 rounded-xl border border-border bg-surface p-5">
                <div class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Selling rules summary</div>
                <ul class="mt-3 space-y-2 text-sm text-zinc-800">
                    <li class="flex gap-2"><flux:icon.shield-check class="mt-0.5 size-4 shrink-0 text-navy" /> Enter a bid amount within the published range. The lowest unique bid at close secures the item.</li>
                    <li class="flex gap-2"><flux:icon.shield-check class="mt-0.5 size-4 shrink-0 text-navy" /> Each bid requires a participation fee of {{ Money::kes($auction->bid_fee) }}, paid now to confirm the bid.</li>
                    <li class="flex gap-2"><flux:icon.shield-check class="mt-0.5 size-4 shrink-0 text-navy" /> You may place multiple bids at different amounts; each bid needs its own participation fee.</li>
                    <li class="flex gap-2"><flux:icon.shield-check class="mt-0.5 size-4 shrink-0 text-navy" /> If your bid is successful, you later pay that bid amount on collection or delivery — not the product value.</li>
                </ul>
            </div>

            @if ($auction->similar_sales)
                <div class="mt-6 rounded-xl border border-border bg-white p-5">
                    <div class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Past successful bid amounts on similar items</div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($auction->similar_sales as $sale)
                            <div class="rounded-md border border-border bg-surface px-3 py-1.5 text-sm font-semibold text-navy">
                                {{ Money::kes($sale) }}
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div>
            <div class="sticky top-20 space-y-4">
                <div class="rounded-2xl border border-border bg-white p-4 shadow-sm sm:p-5">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <div class="text-xs text-muted-foreground">Product value</div>
                            <div class="text-xl font-bold text-navy sm:text-2xl">{{ Money::kes($auction->retail_value) }}</div>
                        </div>
                        <div class="sm:text-right">
                            <div class="text-xs text-muted-foreground">Closes in</div>
                            <x-countdown
                                :target="$auction->endsAtMs()"
                                @class(['font-mono text-base font-bold tabular-nums sm:text-lg', 'text-red-600' => $closingSoon, 'text-navy' => ! $closingSoon])
                            />
                        </div>
                    </div>
                    <div class="mt-3 grid gap-3 border-t border-border pt-3 sm:grid-cols-2">
                        <div>
                            <div class="text-xs text-muted-foreground">Bid range</div>
                            <div class="text-sm font-semibold tabular-nums text-zinc-900">
                                {{ Money::kes($auction->min_bid) }} – {{ Money::kes($auction->max_bid) }}
                            </div>
                            <div class="text-[11px] text-muted-foreground">Steps of {{ $auction->bid_increment }}</div>
                        </div>
                        <div class="sm:text-right">
                            <div class="text-xs text-muted-foreground">Participation fee</div>
                            <div class="text-sm font-semibold text-zinc-900">{{ Money::kes($auction->bid_fee) }}</div>
                            <div class="text-[11px] text-muted-foreground">Paid per bid</div>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-border bg-white p-5 shadow-sm">
                    @if ($formState === 'success')
                        <div class="text-center">
                            <div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-success/15">
                                <flux:icon.check-circle class="size-6 text-success" />
                            </div>
                            <h3 class="mt-3 text-lg font-bold text-navy">Bid confirmed</h3>
                            <p class="mt-1 text-sm text-muted-foreground">Your participation fee is paid and this bid amount is recorded for sale close.</p>
                            <dl class="mt-4 rounded-lg bg-surface p-4 text-left text-sm">
                                <div class="flex justify-between"><dt class="text-muted-foreground">Reference</dt><dd class="font-mono font-semibold">{{ $reference }}</dd></div>
                                <div class="mt-1 flex justify-between"><dt class="text-muted-foreground">Item</dt><dd class="max-w-[60%] truncate font-medium">{{ $auction->name }}</dd></div>
                                <div class="mt-1 flex justify-between"><dt class="text-muted-foreground">Bid amount</dt><dd class="font-semibold">{{ Money::kes((int) $amount) }}</dd></div>
                                <div class="mt-1 flex justify-between"><dt class="text-muted-foreground">Fee paid now</dt><dd class="font-semibold">{{ Money::kes($auction->bid_fee) }}</dd></div>
                            </dl>
                            <p class="mt-3 text-xs text-muted-foreground">If this bid is the lowest unique at close, you will pay {{ Money::kes((int) $amount) }} on collection or delivery.</p>
                            <div class="mt-4 flex gap-2">
                                <flux:button wire:click="resetForm" class="flex-1 bg-accent! text-accent-foreground!">Place another bid</flux:button>
                                <flux:button href="{{ route('auctions.index') }}" variant="outline" class="flex-1">Browse sales</flux:button>
                            </div>
                        </div>
                    @elseif ($formState === 'pending')
                        <div class="py-3 text-center">
                            <div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-navy/10">
                                <flux:icon.device-phone-mobile class="size-6 text-navy" />
                            </div>
                            <div class="mt-4 text-base font-semibold">Pay participation fee {{ Money::kes($auction->bid_fee) }}</div>
                            <p class="mt-1 text-sm text-muted-foreground">
                                This confirms your bid of {{ Money::kes((int) $amount) }}. It is not the product value and is not credited against a later successful-buy payment.
                            </p>
                            <p class="mt-2 text-xs text-muted-foreground">IntaSend will send the live payment prompt once credentials are connected. Use the placeholder controls below during development.</p>
                            <div class="mt-5 grid gap-2 sm:grid-cols-2">
                                <flux:button wire:click="completePlaceholderPayment" class="bg-accent! text-accent-foreground!">Confirm fee payment</flux:button>
                                <flux:button wire:click="failPlaceholderPayment" variant="outline">Mark payment failed</flux:button>
                            </div>
                        </div>
                    @elseif ($formState === 'failure')
                        <div class="text-center">
                            <div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-red-100">
                                <flux:icon.exclamation-triangle class="size-6 text-red-600" />
                            </div>
                            <h3 class="mt-3 text-lg font-bold">Participation fee not paid</h3>
                            <p class="mt-1 text-sm text-muted-foreground">No money was deducted and this bid was not counted. You can try again.</p>
                            <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                                <flux:button wire:click="resetForm" class="flex-1 bg-accent! text-accent-foreground!">Edit and retry</flux:button>
                                <flux:button href="{{ route('contact') }}" variant="outline" class="flex-1">Contact support</flux:button>
                            </div>
                        </div>
                    @else
                        <h2 class="text-lg font-bold text-navy">Place your bid</h2>
                        <p class="mt-1 text-xs text-muted-foreground">Choose a bid amount in the range, then pay the participation fee to confirm it. You can place multiple bids.</p>

                        <form wire:submit="submitBid" class="mt-4 space-y-4">
                            <flux:field>
                                <flux:label>Full name</flux:label>
                                <flux:input wire:model="name" placeholder="e.g. Wanjiru Kamau" />
                                <flux:error name="name" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Mobile number</flux:label>
                                <flux:input wire:model="phone" inputmode="tel" placeholder="07XX XXX XXX" />
                                <flux:error name="phone" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Email address</flux:label>
                                <flux:input wire:model="email" type="email" placeholder="you@example.com" />
                                <flux:error name="email" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Your bid amount (KES)</flux:label>
                                <flux:input wire:model="amount" inputmode="numeric" placeholder="{{ $auction->min_bid }} – {{ number_format($auction->max_bid) }}" />
                                <flux:description>
                                    Enter {{ Money::kes($auction->min_bid) }}–{{ Money::kes($auction->max_bid) }} in steps of {{ $auction->bid_increment }}. This amount is paid later only if it becomes the successful bid.
                                </flux:description>
                                <flux:error name="amount" />
                            </flux:field>

                            <div class="rounded-lg border border-border bg-surface px-3 py-2 text-xs text-zinc-800">
                                <div class="flex items-center justify-between gap-3">
                                    <span>Participation fee due now</span>
                                    <span class="font-semibold text-navy">{{ Money::kes($auction->bid_fee) }}</span>
                                </div>
                            </div>

                            <flux:field variant="inline">
                                <flux:checkbox wire:model="rulesConsent" />
                                <flux:label class="text-xs!">
                                    I accept the <a href="{{ route('legal') }}" class="font-medium text-navy underline">Selling Rules & Privacy Policy</a>.
                                </flux:label>
                                <flux:error name="rulesConsent" />
                            </flux:field>

                            <flux:field variant="inline">
                                <flux:checkbox wire:model="marketingConsent" />
                                <flux:label class="text-xs!">
                                    Send me new sale and promotion updates by SMS, WhatsApp, or email. Optional.
                                </flux:label>
                            </flux:field>

                            <flux:button type="submit" class="w-full bg-accent! text-accent-foreground! hover:bg-accent/90!">
                                Pay participation fee {{ Money::kes($auction->bid_fee) }}
                            </flux:button>
                        </form>
                    @endif
                </div>

                <div class="rounded-xl border border-border bg-surface p-4 text-xs text-muted-foreground">
                    <div class="flex items-center gap-2 font-semibold text-zinc-900">
                        <flux:icon.shield-check class="size-4 text-navy" />
                        Secure participation-fee payment
                    </div>
                    <p class="mt-1">You pay the participation fee now to confirm each bid. Product value is never charged as a bid payment.</p>
                </div>
            </div>
        </div>
    </div>
</div>
