<?php

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Services\AuctionBidService;
use App\Services\DarajaStkService;
use App\Services\MobileOtpService;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.site')]
#[Title('Auction: UniqBid')]
class extends Component {
    public Auction $auction;

    public int $activeImage = 0;

    public string $name = '';

    public string $phone = '';

    public string $amount = '';

    public bool $rulesConsent = false;

    public string $formState = 'idle';

    public string $bidStep = 'phone';

    public string $code = '';

    public string $placeholderCode = '';

    public string $reference = '';

    public ?int $paymentAttemptId = null;

    public bool $accountCreated = false;

    public bool $isArchived = false;

    public bool $awaitsMpesa = false;

    public string $failureReason = '';

    public function mount(Auction $auction): void
    {
        abort_unless($auction->status === 'live' || $auction->settled_at !== null, 404);
        abort_unless($auction->product_id !== null && $auction->product?->isInAuctionChannel(), 404);

        $this->auction = $auction->load('images');
        $this->isArchived = $auction->settled_at !== null;

        $user = Auth::user();

        if ($user !== null && $user->phone && ! $user->is_admin) {
            $this->fillFromCustomer($user);
            $this->bidStep = 'details';
        }
    }

    public function selectImage(int $index): void
    {
        $this->activeImage = $index;
    }

    public function continueWithPhone(): void
    {
        $this->validate([
            'phone' => ['required', 'string'],
        ], [
            'phone.required' => 'Enter a valid Kenyan mobile number.',
        ]);

        $phone = app(AuctionBidService::class)->normalizeKenyanPhone($this->phone);
        $this->phone = '0'.substr($phone, 3);
        $this->resetErrorBag();

        $this->bidStep = User::query()
            ->where('phone', $phone)
            ->where('is_admin', false)
            ->exists()
            ? 'account'
            : 'details';
    }

    public function requestSignInCode(): void
    {
        $phone = app(AuctionBidService::class)->normalizeKenyanPhone($this->phone);

        abort_unless(
            User::query()->where('phone', $phone)->where('is_admin', false)->exists(),
            422,
        );

        $this->phone = '0'.substr($phone, 3);
        $this->placeholderCode = app(MobileOtpService::class)->issue($phone);
        $this->code = '';
        $this->bidStep = 'otp';
        $this->resetErrorBag();
    }

    public function verifySignInCode(): void
    {
        $this->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $phone = app(AuctionBidService::class)->normalizeKenyanPhone($this->phone);

        app(MobileOtpService::class)->verify($phone, $this->code);

        $user = User::query()
            ->where('phone', $phone)
            ->where('is_admin', false)
            ->firstOrFail();

        $user->forceFill(['phone_verified_at' => now()])->save();

        Auth::login($user);
        session()->regenerate();

        $this->fillFromCustomer($user);
        $this->code = '';
        $this->placeholderCode = '';
        $this->bidStep = 'details';
        $this->resetErrorBag();

        Flux::toast(variant: 'success', text: 'Signed in. Continue with your entry.');
    }

    public function changePhone(): void
    {
        $this->bidStep = 'phone';
        $this->reset('phone', 'name', 'amount', 'rulesConsent', 'code', 'placeholderCode');
        $this->resetErrorBag();
    }

    private function fillFromCustomer(User $user): void
    {
        $this->name = $user->name;
        $this->phone = '0'.substr((string) $user->phone, 3);
    }

    public function submitBid(): void
    {
        $rules = [
            'name' => ['required', 'string', 'min:2'],
            'phone' => ['required', 'regex:'.AuctionBidService::KENYAN_MOBILE_INPUT_REGEX],
            'amount' => ['required', 'integer', 'min:'.$this->auction->min_bid, 'max:'.$this->auction->max_bid],
            'rulesConsent' => ['accepted'],
        ];

        $this->validate($rules, [
            'phone.regex' => 'Enter a valid Kenyan mobile number.',
            'rulesConsent.accepted' => 'You must accept the rules and privacy policy to participate.',
        ]);

        $amount = (int) $this->amount;

        if ($amount % $this->auction->bid_increment !== 0) {
            $this->addError('amount', 'Must be in increments of '.$this->auction->bid_increment);

            return;
        }

        $user = Auth::user();

        $attempt = app(AuctionBidService::class)->initiate($this->auction, $amount, [
            'name' => $this->name,
            'email' => $user?->email,
            'phone' => $this->phone,
            'marketing_consent' => true,
        ]);

        $this->paymentAttemptId = $attempt->id;
        $this->reference = $attempt->auctionBid->reference;
        $this->failureReason = '';

        $daraja = app(DarajaStkService::class);

        if ($daraja->isReady() && ! app()->runningUnitTests()) {
            try {
                $daraja->requestPayment($attempt);
                $this->awaitsMpesa = true;
            } catch (ValidationException $exception) {
                $pending = $exception->errors()['payment_pending'][0] ?? null;

                if (is_string($pending)) {
                    $this->awaitsMpesa = true;
                    $this->failureReason = $pending;
                    $this->formState = 'pending';

                    return;
                }

                $this->formState = 'failure';
                $this->failureReason = (string) (collect($exception->errors())->flatten()->first() ?? 'M-Pesa could not start the payment.');

                return;
            }
        }

        $this->formState = 'pending';
    }

    public function simulateStkPushSuccess(): void
    {
        if ($this->formState !== 'pending' || ! $this->paymentAttemptId) {
            return;
        }

        $bid = app(AuctionBidService::class)->markSucceeded(PaymentAttempt::query()->findOrFail($this->paymentAttemptId));
        $this->finishSuccessfulPayment($bid);
    }

    public function refreshPayment(): void
    {
        if ($this->formState !== 'pending' || ! $this->paymentAttemptId) {
            return;
        }

        $attempt = PaymentAttempt::query()->with('auctionBid.bidder.user')->find($this->paymentAttemptId);

        if ($attempt === null) {
            return;
        }

        if ($attempt->status === 'initiated' && filled($attempt->checkout_request_id)) {
            app(DarajaStkService::class)->queryPayment($attempt);
            $attempt->refresh()->load('auctionBid.bidder.user');
        }

        if ($attempt->status === 'succeeded') {
            $this->finishSuccessfulPayment($attempt->auctionBid);

            return;
        }

        if ($attempt->status === 'failed') {
            $this->formState = 'failure';
            $this->failureReason = (string) ($attempt->failure_reason ?? '');
        }
    }

    public function simulateStkPushFailure(): void
    {
        if ($this->paymentAttemptId) {
            app(AuctionBidService::class)->markFailed(PaymentAttempt::query()->findOrFail($this->paymentAttemptId));
        }

        $this->formState = 'failure';
    }

    public function resetForm(): void
    {
        $this->formState = 'idle';
        $this->accountCreated = false;
        $this->awaitsMpesa = false;
        $this->failureReason = '';
        $this->reference = '';
        $this->paymentAttemptId = null;
    }

    private function finishSuccessfulPayment(AuctionBid $bid): void
    {
        $bid->loadMissing('bidder.user');
        $this->formState = 'success';
        $this->reference = $bid->reference;
        $this->refreshLiveSnapshot();

        $account = $bid->bidder->user;

        if (! Auth::check() && $account !== null && ! $account->canAccessAdmin()) {
            Auth::login($account);
            session()->regenerate();
            $this->fillFromCustomer($account);
            $this->bidStep = 'details';
            $this->accountCreated = true;
        }

        Flux::toast(
            variant: 'success',
            text: 'Participation fee paid for reference '.$this->reference.'.',
        );
    }

    public function refreshLiveSnapshot(): void
    {
        $this->auction = $this->auction->fresh(['images']);
    }
}; ?>

@php
    $closingSoon = $auction->ends_at->lt(now()->addHour());
    $image = $auction->images[$activeImage] ?? $auction->images->first();
    $isSignedInCustomer = auth()->user()?->phone && ! auth()->user()?->is_admin;
@endphp

<div class="mx-auto max-w-7xl px-4 py-8" wire:poll.10s.visible="refreshLiveSnapshot">
    <div class="text-xs text-muted-foreground">
        <a href="{{ route('auctions.index') }}" class="hover:underline">Live Sales/Auctions</a>
        / {{ $auction->category }}
    </div>

    <div class="mt-4 grid gap-8 lg:grid-cols-[1.2fr_1fr]">
        <div>
            <div class="overflow-hidden rounded-2xl border border-border bg-white">
                <div class="aspect-4/3 bg-muted">
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
            </div>

            <p class="mt-4 text-sm leading-relaxed text-zinc-800">{{ $auction->description }}</p>

            <div class="mt-6 rounded-xl border border-border bg-surface p-5">
                <div class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Selling rules summary</div>
                <ul class="mt-3 space-y-2 text-sm text-zinc-800">
                    <li class="flex gap-2"><flux:icon.shield-check class="mt-0.5 size-4 shrink-0 text-navy" /> Enter an amount within the published range. The lowest unique amount at close secures the item.</li>
                    <li class="flex gap-2"><flux:icon.shield-check class="mt-0.5 size-4 shrink-0 text-navy" /> Each entry requires a participation fee of {{ Money::kes($auction->bid_fee) }}, paid now to confirm it.</li>
                    <li class="flex gap-2"><flux:icon.shield-check class="mt-0.5 size-4 shrink-0 text-navy" /> You may enter multiple amounts; each needs its own participation fee.</li>
                    <li class="flex gap-2"><flux:icon.shield-check class="mt-0.5 size-4 shrink-0 text-navy" /> If your amount is successful, you later pay that amount on collection or delivery, not the product value.</li>
                </ul>
            </div>

            @if ($auction->similar_sales)
                <div class="mt-6 rounded-xl border border-border bg-white p-5">
                    <div class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Past successful amounts on similar items</div>
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
                    @if ($isArchived)
                        <div class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Completed auction</div>
                        <div class="mt-2 text-xl font-bold text-navy sm:text-2xl">Sale record</div>
                        <p class="mt-2 text-sm text-muted-foreground">This auction has ended. Browse current live auctions to take part in another sale.</p>
                        @if ($auction->result)
                            <div class="mt-4 rounded-lg bg-surface p-3 text-sm">
                                <div class="font-semibold text-navy">Sale result</div>
                                <div class="mt-1">{{ $auction->result->name }} — sold for {{ Money::kes($auction->result->sale_price) }}</div>
                            </div>
                        @endif
                        <flux:button href="{{ route('auctions.index') }}" class="mt-4 w-full bg-accent! text-accent-foreground!" wire:navigate>Browse live auctions</flux:button>
                    @else
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <div class="text-xs text-muted-foreground">Product value</div>
                            <div class="text-xl font-bold text-navy sm:text-2xl">{{ Money::kes($auction->retail_value) }}</div>
                        </div>
                        <div class="flex items-center sm:justify-end">
                            <x-countdown
                                :auction="$auction"
                                @class(['text-base font-bold sm:text-lg', 'text-red-600' => $closingSoon, 'text-navy' => ! $closingSoon])
                            />
                        </div>
                    </div>
                    <div class="mt-3 grid gap-3 border-t border-border pt-3 sm:grid-cols-2">
                        <div>
                            <div class="text-xs text-muted-foreground">Amount range</div>
                            <div class="text-sm font-semibold tabular-nums text-zinc-900">
                                {{ Money::kes($auction->min_bid) }} – {{ Money::kes($auction->max_bid) }}
                            </div>
                            <div class="text-[11px] text-muted-foreground">Steps of {{ $auction->bid_increment }}</div>
                        </div>
                        <div class="sm:text-right">
                            <div class="text-xs text-muted-foreground">Participation fee</div>
                            <div class="text-sm font-semibold text-zinc-900">{{ Money::kes($auction->bid_fee) }}</div>
                            <div class="text-[11px] text-muted-foreground">Paid per entry</div>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-border bg-white p-5 shadow-sm">
                    @if ($formState === 'success')
                        <div class="text-center">
                            <div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-success/15">
                                <flux:icon.check-circle class="size-6 text-success" />
                            </div>
                            <h3 class="mt-3 text-lg font-bold text-navy">Entry confirmed</h3>
                            <p class="mt-1 text-sm text-muted-foreground">Your participation fee is paid and this amount is recorded for sale close.</p>
                            <dl class="mt-4 rounded-lg bg-surface p-4 text-left text-sm">
                                <div class="flex justify-between"><dt class="text-muted-foreground">Reference</dt><dd class="font-mono font-semibold">{{ $reference }}</dd></div>
                                <div class="mt-1 flex justify-between"><dt class="text-muted-foreground">Item</dt><dd class="max-w-[60%] truncate font-medium">{{ $auction->name }}</dd></div>
                                <div class="mt-1 flex justify-between"><dt class="text-muted-foreground">Your amount</dt><dd class="font-semibold">{{ Money::kes((int) $amount) }}</dd></div>
                                <div class="mt-1 flex justify-between"><dt class="text-muted-foreground">Fee paid now</dt><dd class="font-semibold">{{ Money::kes($auction->bid_fee) }}</dd></div>
                            </dl>
                            <p class="mt-3 text-xs text-muted-foreground">If this amount is the lowest unique at close, you will pay {{ Money::kes((int) $amount) }} on collection or delivery.</p>
                            @if ($accountCreated)
                                <div class="mt-3 rounded-lg border border-border bg-surface px-3 py-2 text-left text-xs text-zinc-800" data-test="account-created">
                                    We created your UniqBid account for {{ $phone }}. Track your bids in
                                    <a href="{{ route('account') }}" class="font-medium text-navy underline" wire:navigate>My Account</a>.
                                </div>
                            @endif
                            <div class="mt-4 flex gap-2">
                                <flux:button wire:click="resetForm" class="flex-1 bg-accent! text-accent-foreground!">Enter another amount</flux:button>
                                <flux:button href="{{ route('auctions.index') }}" variant="outline" class="flex-1">Browse sales</flux:button>
                            </div>
                        </div>
                    @elseif ($formState === 'pending')
                        <div @if ($awaitsMpesa) wire:poll.3s.visible="refreshPayment" @endif class="py-3 text-center">
                            <div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-navy/10">
                                <flux:icon.device-phone-mobile class="size-6 text-navy" />
                            </div>
                            <div class="mt-4 text-base font-semibold">Pay participation fee {{ Money::kes($auction->bid_fee) }}</div>
                            <p class="mt-1 text-sm text-muted-foreground">
                                This confirms your amount of {{ Money::kes((int) $amount) }}. It is not the product value and is not credited against a later successful-buy payment.
                            </p>
                            @if ($awaitsMpesa)
                                <p class="mt-2 text-sm text-navy">Check {{ $phone }} for the M-Pesa prompt and enter your PIN.</p>
                                <p class="mt-1 text-xs text-muted-foreground">This page checks with Safaricom until the payment is confirmed. Keep it open.</p>
                                @if ($failureReason !== '')
                                    <p class="mt-2 text-xs text-amber-800">{{ $failureReason }}</p>
                                @endif
                            @else
                                <p class="mt-2 text-xs text-muted-foreground">M-Pesa STK Push will send the payment prompt once Daraja credentials are connected. Use the simulated controls below during development.</p>
                                <div class="mt-5 grid gap-2 sm:grid-cols-2">
                                    <flux:button wire:click="simulateStkPushSuccess" class="bg-accent! text-accent-foreground!">Simulate STK approval</flux:button>
                                    <flux:button wire:click="simulateStkPushFailure" variant="outline">Simulate STK cancellation</flux:button>
                                </div>
                            @endif
                        </div>
                    @elseif ($formState === 'failure')
                        <div class="text-center">
                            <div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-red-100">
                                <flux:icon.exclamation-triangle class="size-6 text-red-600" />
                            </div>
                            <h3 class="mt-3 text-lg font-bold">Participation fee not paid</h3>
                            <p class="mt-1 text-sm text-muted-foreground">{{ $failureReason !== '' ? $failureReason : 'No money was deducted and this entry was not counted. You can try again.' }}</p>
                            <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                                <flux:button wire:click="resetForm" class="flex-1 bg-accent! text-accent-foreground!">Edit and retry</flux:button>
                                <flux:button href="{{ route('contact') }}" variant="outline" class="flex-1">Contact support</flux:button>
                            </div>
                        </div>
                    @else
                        @if ($bidStep === 'phone')
                            <h2 class="text-lg font-bold text-navy">Enter your amount</h2>
                            <p class="mt-1 text-xs text-muted-foreground">Start with your mobile number. We will check whether you need to sign in or add your contact details.</p>

                            <form wire:submit="continueWithPhone" class="mt-4 space-y-4">
                                <flux:field>
                                    <flux:label>Mobile number</flux:label>
                                    <flux:input wire:model="phone" inputmode="tel" placeholder="07XX or 01XX XXX XXX" autofocus />
                                    <flux:description>Used for the payment prompt and account security.</flux:description>
                                    <flux:error name="phone" />
                                </flux:field>

                                <flux:button type="submit" class="w-full bg-accent! text-accent-foreground! hover:bg-accent/90!">
                                    Continue
                                </flux:button>
                            </form>
                        @elseif ($bidStep === 'account')
                            <h2 class="text-lg font-bold text-navy">Sign in to continue</h2>
                            <p class="mt-1 text-sm text-muted-foreground">
                                This mobile number already has a UniqBid account. We will send a one-time code to {{ $phone }} so you can use your saved details securely.
                            </p>
                            <div class="mt-5 space-y-3">
                                <flux:button wire:click="requestSignInCode" class="w-full bg-accent! text-accent-foreground! hover:bg-accent/90!">
                                    Sign in with mobile
                                </flux:button>
                                <flux:button wire:click="changePhone" variant="ghost" class="w-full">
                                    Use a different number
                                </flux:button>
                            </div>
                        @elseif ($bidStep === 'otp')
                            <h2 class="text-lg font-bold text-navy">Enter your code</h2>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Enter the six-digit code sent to {{ $phone }}.
                            </p>

                            @if ($placeholderCode !== '')
                                <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950">
                                    <div class="font-semibold">SMS placeholder</div>
                                    <p class="mt-1">SMS delivery will be connected later. For this staging flow, enter <span class="font-mono font-bold">{{ $placeholderCode }}</span>.</p>
                                </div>
                            @else
                                <div class="mt-4 rounded-lg border border-border bg-surface p-3 text-sm text-muted-foreground">
                                    Check your phone for the six-digit code. It expires in 10 minutes.
                                </div>
                            @endif

                            <form wire:submit="verifySignInCode" class="mt-4 space-y-4">
                                <flux:error name="phone" />
                                <flux:field>
                                    <flux:label>Six-digit code</flux:label>
                                    <flux:input wire:model="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" autofocus />
                                    <flux:error name="code" />
                                </flux:field>

                                <flux:button type="submit" class="w-full bg-accent! text-accent-foreground! hover:bg-accent/90!">
                                    Continue
                                </flux:button>
                                <flux:button type="button" wire:click="requestSignInCode" variant="ghost" class="w-full">
                                    Send a new code
                                </flux:button>
                                <flux:button type="button" wire:click="changePhone" variant="ghost" class="w-full">
                                    Use a different number
                                </flux:button>
                            </form>
                        @else
                            <h2 class="text-lg font-bold text-navy">Add your entry details</h2>
                            <p class="mt-1 text-xs text-muted-foreground">Choose an amount in the range, then pay the participation fee to confirm it. You can enter multiple amounts.</p>

                            <form wire:submit="submitBid" class="mt-4 space-y-4">
                            @if (! $isSignedInCustomer)
                                <div class="flex items-center justify-between rounded-lg border border-border bg-surface px-3 py-2 text-sm">
                                    <span class="font-medium text-zinc-900">{{ $phone }}</span>
                                    <flux:button type="button" wire:click="changePhone" variant="ghost" size="sm">Change</flux:button>
                                </div>
                            @else
                                <div class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-muted-foreground">
                                    Using the saved details for your verified mobile account.
                                    <a href="{{ route('account') }}" class="font-medium text-navy hover:underline" wire:navigate>Update details</a>
                                </div>
                            @endif
                            @if (! $isSignedInCustomer)
                            <flux:field>
                                <flux:label>Full name</flux:label>
                                <flux:input wire:model="name" placeholder="e.g. Wanjiru Kamau" />
                                <flux:error name="name" />
                            </flux:field>
                            @endif

                            <flux:field>
                                <flux:label>Your amount (KES)</flux:label>
                                <flux:input wire:model="amount" inputmode="numeric" placeholder="{{ $auction->min_bid }} – {{ number_format($auction->max_bid) }}" />
                                <flux:description>
                                    Enter {{ Money::kes($auction->min_bid) }}–{{ Money::kes($auction->max_bid) }} in steps of {{ $auction->bid_increment }}. This amount is paid later only if it becomes the successful amount.
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

                            <flux:button type="submit" class="w-full bg-accent! text-accent-foreground! hover:bg-accent/90!">
                                Pay participation fee {{ Money::kes($auction->bid_fee) }}
                            </flux:button>
                        </form>
                        @endif
                    @endif
                </div>

                <div class="rounded-xl border border-border bg-surface p-4 text-xs text-muted-foreground">
                    <div class="flex items-center gap-2 font-semibold text-zinc-900">
                        <flux:icon.shield-check class="size-4 text-navy" />
                        Secure participation-fee payment
                    </div>
                    <p class="mt-1">You pay the participation fee now to confirm each entry. Product value is never charged as a participation payment.</p>
                </div>
                    @endif
            </div>
        </div>
    </div>
</div>
