<?php

use App\Models\AuctionResult;
use App\Services\RedeemBidService;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.site')]
#[Title('Redeem: UniqBid')]
class extends Component {
    public string $step = 'lookup';

    public string $reference = '';

    public ?int $resultId = null;

    public string $itemName = '';

    public int $salePrice = 0;

    public int $retailValue = 0;

    public int $payoutAmount = 0;

    public string $buyerDisplay = '';

    public string $phone = '';

    public string $payoutMessage = '';

    public bool $payoutPaid = false;

    public ?int $paymentAttemptId = null;

    public bool $alreadyRedeemed = false;

    public function mount(RedeemBidService $service): void
    {
        session()->forget('redeem.attempt_id');

        $reference = strtoupper(trim((string) request()->query('reference', '')));

        if ($reference === '') {
            return;
        }

        $this->reference = $reference;

        try {
            $this->lookup($service);
        } catch (ValidationException) {
            $this->step = 'lookup';
        }
    }

    public function lookup(RedeemBidService $service): void
    {
        $this->validate([
            'reference' => ['required', 'string', 'max:40'],
        ]);

        try {
            $payload = $service->validateReference($this->reference);
        } catch (ValidationException $exception) {
            Flux::toast(variant: 'danger', text: collect($exception->errors())->flatten()->first() ?? 'Invalid reference.');

            throw $exception;
        }

        $this->resultId = $payload['result']->id;
        $this->itemName = $payload['auction']->name;
        $this->salePrice = (int) $payload['result']->sale_price;
        $this->retailValue = (int) $payload['auction']->retail_value;
        $this->payoutAmount = max(0, $this->retailValue - $this->salePrice);
        $this->buyerDisplay = $payload['result']->buyer_display;
        $phone = (string) ($payload['bid']->bidder->phone ?? '');
        $this->phone = str_starts_with($phone, '254') ? '0'.substr($phone, 3) : $phone;
        $this->alreadyRedeemed = $payload['result']->fulfilment_status === AuctionResult::FULFILMENT_REDEEMED;
        $this->step = $payload['result']->redemption_requested_at !== null ? 'review' : 'redeem';
    }

    public function confirmRedeem(RedeemBidService $service): void
    {
        $result = AuctionResult::query()->findOrFail($this->resultId);

        if ($this->payoutAmount < 10) {
            Flux::toast(variant: 'danger', text: 'M-Pesa cannot send a payout below KES 10.');

            return;
        }

        if (! $this->alreadyRedeemed) {
            try {
                $result = $service->redeem($result);
            } catch (ValidationException $exception) {
                Flux::toast(variant: 'danger', text: collect($exception->errors())->flatten()->first() ?? 'Unable to redeem.');

                throw $exception;
            }
        }

        try {
            $service->submitForReview($result);
        } catch (ValidationException $exception) {
            Flux::toast(variant: 'danger', text: collect($exception->errors())->flatten()->first() ?? 'Unable to send this request.');

            throw $exception;
        }

        $this->step = 'review';
        Flux::toast(variant: 'success', text: 'Your redeem request has been sent for review.');
    }

    public function resetFlow(): void
    {
        session()->forget('redeem.attempt_id');
        $this->reset(['step', 'reference', 'resultId', 'itemName', 'salePrice', 'retailValue', 'payoutAmount', 'buyerDisplay', 'phone', 'payoutMessage', 'payoutPaid', 'paymentAttemptId', 'alreadyRedeemed']);
        $this->step = 'lookup';
    }
}; ?>

<div class="mx-auto max-w-2xl px-4 py-12">
    <div class="text-center">
        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-gold">Successful buy</div>
        <h1 class="mt-2 text-3xl font-bold text-navy">Redeem for cash</h1>
        <p class="mt-3 text-sm text-muted-foreground">
            Enter your successful-buy reference to redeem the item for cash. Your cash payout is the product value minus your successful amount — you do not pay that amount again.
        </p>
    </div>

    <div class="mt-8 rounded-2xl border border-border bg-white p-6 shadow-sm">
        @if ($step === 'lookup')
            <form wire:submit="lookup" class="space-y-4">
                <flux:field>
                    <flux:label>Successful-buy reference</flux:label>
                    <flux:input wire:model="reference" placeholder="BID-XXXXXXXXXX" />
                    <flux:error name="reference" />
                </flux:field>
                <flux:button type="submit" class="bg-accent! text-accent-foreground!">Continue</flux:button>
            </form>
        @elseif ($step === 'redeem')
            <div class="space-y-4">
                <div>
                    <div class="text-sm text-muted-foreground">Successful buy</div>
                    <div class="text-xl font-semibold text-navy">{{ $itemName }}</div>
                    <div class="mt-1 text-sm text-muted-foreground">{{ $buyerDisplay }} · Ref {{ strtoupper($reference) }}</div>
                    @if ($phone !== '')
                        <div class="mt-1 text-sm text-muted-foreground">Payout phone {{ $phone }}</div>
                    @endif
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border border-border bg-surface p-4">
                        <div class="text-xs uppercase tracking-wide text-muted-foreground">Product value</div>
                        <div class="mt-1 text-lg font-semibold">{{ Money::kes($retailValue) }}</div>
                    </div>
                    <div class="rounded-xl border border-border bg-surface p-4">
                        <div class="text-xs uppercase tracking-wide text-muted-foreground">Successful amount</div>
                        <div class="mt-1 text-lg font-semibold">{{ Money::kes($salePrice) }}</div>
                    </div>
                    <div class="rounded-xl border border-border bg-surface p-4">
                        <div class="text-xs uppercase tracking-wide text-muted-foreground">Cash payout</div>
                        <div class="mt-1 text-lg font-semibold text-emerald-700">{{ Money::kes($payoutAmount) }}</div>
                    </div>
                </div>

                <flux:button wire:click="confirmRedeem" wire:confirm="Send this redeem request for review?" wire:loading.attr="disabled" class="w-full bg-accent! text-accent-foreground! sm:w-auto">
                    {{ $alreadyRedeemed ? 'Redeem '.Money::kes($payoutAmount).' to M-Pesa' : 'Redeem for '.Money::kes($payoutAmount) }}
                </flux:button>

                <flux:button variant="ghost" wire:click="resetFlow">Start over</flux:button>
            </div>
        @elseif ($step === 'review')
            <div class="space-y-3 text-center">
                <flux:icon.check-circle class="mx-auto size-10 text-emerald-600" />
                <h2 class="text-xl font-semibold text-navy">Request sent</h2>
                <p class="text-sm text-muted-foreground">Your redeem request has been sent for review.</p>
                <flux:button href="{{ route('account') }}" class="w-full bg-accent! text-accent-foreground! sm:w-auto" wire:navigate>My account</flux:button>
            </div>
        @else
            <div class="space-y-3 text-center">
                <flux:icon.check-circle class="mx-auto size-10 text-emerald-600" />
                <h2 class="text-xl font-semibold text-navy">Request sent</h2>
                <p class="text-sm text-muted-foreground">Your redeem request has been sent for review.</p>
                <flux:button href="{{ route('home') }}" class="w-full bg-accent! text-accent-foreground! sm:w-auto">Back home</flux:button>
            </div>
        @endif
    </div>
</div>
