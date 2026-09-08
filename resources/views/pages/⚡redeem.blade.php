<?php

use App\Models\AuctionResult;
use App\Models\PaymentAttempt;
use App\Services\RedeemBidService;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.site')]
#[Title('Redeem Bid: UniqBid')]
class extends Component {
    public string $step = 'lookup';

    public string $reference = '';

    public ?int $resultId = null;

    public ?int $paymentAttemptId = null;

    public string $itemName = '';

    public int $salePrice = 0;

    public int $retailValue = 0;

    public string $buyerDisplay = '';

    public bool $salePaid = false;

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
        $this->salePrice = $payload['result']->sale_price;
        $this->retailValue = $payload['auction']->retail_value;
        $this->buyerDisplay = $payload['result']->buyer_display;
        $this->salePaid = $payload['result']->isSalePaid();
        $this->step = $this->salePaid ? 'redeem' : 'pay';
    }

    public function startPayment(RedeemBidService $service): void
    {
        $result = AuctionResult::query()->findOrFail($this->resultId);
        $attempt = $service->initiateSalePayment($result);
        $this->paymentAttemptId = $attempt->id;
        $this->step = 'simulate';
    }

    public function simulateSuccess(RedeemBidService $service): void
    {
        $attempt = PaymentAttempt::query()->findOrFail($this->paymentAttemptId);
        $result = $service->confirmSalePayment($attempt);
        $this->salePaid = true;
        $this->step = 'redeem';
        Flux::toast(variant: 'success', text: 'Sale payment simulated successfully.');
    }

    public function confirmRedeem(RedeemBidService $service): void
    {
        $result = AuctionResult::query()->findOrFail($this->resultId);

        try {
            $service->redeem($result);
        } catch (ValidationException $exception) {
            Flux::toast(variant: 'danger', text: collect($exception->errors())->flatten()->first() ?? 'Unable to redeem.');

            throw $exception;
        }

        $this->step = 'done';
        Flux::toast(variant: 'success', text: 'Redeemed. Cash payout equal to product value is pending Finance.');
    }

    public function resetFlow(): void
    {
        $this->reset(['step', 'reference', 'resultId', 'paymentAttemptId', 'itemName', 'salePrice', 'retailValue', 'buyerDisplay', 'salePaid']);
        $this->step = 'lookup';
    }
}; ?>

<div class="mx-auto max-w-2xl px-4 py-12">
    <div class="text-center">
        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-gold">Successful buy</div>
        <h1 class="mt-2 text-3xl font-bold text-navy">Redeem Bid</h1>
        <p class="mt-3 text-sm text-muted-foreground">
            Enter your successful bid reference. After paying the bid amount, you can redeem for the product value in cash instead of collecting the item.
        </p>
    </div>

    <div class="mt-8 rounded-2xl border border-border bg-white p-6 shadow-sm">
        @if ($step === 'lookup')
            <form wire:submit="lookup" class="space-y-4">
                <flux:field>
                    <flux:label>Bid reference</flux:label>
                    <flux:input wire:model="reference" placeholder="BID-XXXXXXXXXX" />
                    <flux:error name="reference" />
                </flux:field>
                <flux:button type="submit" class="bg-accent! text-accent-foreground!">Continue</flux:button>
            </form>
        @elseif ($step === 'pay' || $step === 'simulate' || $step === 'redeem')
            <div class="space-y-4">
                <div>
                    <div class="text-sm text-muted-foreground">Successful buy</div>
                    <div class="text-xl font-semibold text-navy">{{ $itemName }}</div>
                    <div class="mt-1 text-sm text-muted-foreground">{{ $buyerDisplay }} · Ref {{ strtoupper($reference) }}</div>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-border bg-surface p-4">
                        <div class="text-xs uppercase tracking-wide text-muted-foreground">Pay bid amount</div>
                        <div class="mt-1 text-lg font-semibold">{{ Money::kes($salePrice) }}</div>
                    </div>
                    <div class="rounded-xl border border-border bg-surface p-4">
                        <div class="text-xs uppercase tracking-wide text-muted-foreground">Redeem for</div>
                        <div class="mt-1 text-lg font-semibold text-emerald-700">{{ Money::kes($retailValue) }}</div>
                    </div>
                </div>

                @if ($step === 'pay')
                    <flux:button wire:click="startPayment" class="bg-accent! text-accent-foreground!">
                        Pay sale amount (simulate M-Pesa)
                    </flux:button>
                @elseif ($step === 'simulate')
                    <flux:text>STK push simulation ready. Confirm success to continue.</flux:text>
                    <flux:button wire:click="simulateSuccess" class="bg-accent! text-accent-foreground!">
                        Simulate payment success
                    </flux:button>
                @else
                    <flux:button wire:click="confirmRedeem" wire:confirm="Redeem this successful buy for cash equal to the product value?" class="bg-accent! text-accent-foreground!">
                        Redeem for cash
                    </flux:button>
                @endif

                <flux:button variant="ghost" wire:click="resetFlow">Start over</flux:button>
            </div>
        @else
            <div class="space-y-3 text-center">
                <flux:icon.check-circle class="mx-auto size-10 text-emerald-600" />
                <h2 class="text-xl font-semibold text-navy">Redemption recorded</h2>
                <p class="text-sm text-muted-foreground">
                    You paid {{ Money::kes($salePrice) }}. Finance will process a cash payout of {{ Money::kes($retailValue) }} (product value).
                </p>
                <flux:button href="{{ route('home') }}" class="bg-accent! text-accent-foreground!">Back home</flux:button>
            </div>
        @endif
    </div>
</div>
