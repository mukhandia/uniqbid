<?php

use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Services\AuctionBidService;
use App\Services\CartService;
use App\Services\DarajaStkService;
use App\Services\OrderService;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.site')]
#[Title('Checkout: UniqBid Shop')]
class extends Component {
    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $delivery = Order::DELIVERY_NAIROBI;

    public string $address = '';

    public string $status = 'form';

    public string $orderRef = '';

    public ?int $paymentAttemptId = null;

    public string $failureReason = '';

    public int $amountDue = 0;

    public function mount(CartService $cart): void
    {
        $pendingId = session('checkout.order_id');

        if (is_numeric($pendingId)) {
            $order = Order::query()->find((int) $pendingId);

            if ($order !== null && $order->status === Order::STATUS_AWAITING_PAYMENT) {
                $this->resumeWaiting($order);

                return;
            }

            if ($order !== null && $order->status === Order::STATUS_PAID) {
                $this->showPaid($order, $cart);

                return;
            }

            session()->forget('checkout.order_id');
        }

        if ($cart->count() === 0) {
            $this->redirect(route('cart'), navigate: true);

            return;
        }

        $user = Auth::user();

        if ($user !== null && ! $user->is_admin) {
            $this->name = $user->name;
            $this->email = $user->email ?? '';
            $phone = $user->phone ?? '';
            $this->phone = str_starts_with($phone, '254') ? '0'.substr($phone, 3) : $phone;
        }
    }

    public function updatedDelivery(): void
    {
        if ($this->delivery === Order::DELIVERY_COLLECTION) {
            $this->resetErrorBag('address');
        }
    }

    public function placeOrder(OrderService $orders, CartService $cart, DarajaStkService $daraja): void
    {
        if ($this->status === 'pending') {
            return;
        }

        if ($cart->count() === 0) {
            $this->redirect(route('cart'), navigate: true);

            return;
        }

        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'phone' => ['required', 'regex:'.AuctionBidService::KENYAN_MOBILE_INPUT_REGEX],
            'email' => ['nullable', 'email', 'max:255'],
            'delivery' => ['required', 'in:nairobi,collection,upcountry'],
            'address' => [$this->delivery === Order::DELIVERY_COLLECTION ? 'nullable' : 'required', 'string', 'max:1000'],
        ]);

        $awaitMpesa = $daraja->isReady() && ! app()->runningUnitTests();

        try {
            $order = $orders->placeFromCart([
                'name' => $this->name,
                'phone' => $this->phone,
                'email' => $this->email !== '' ? $this->email : null,
                'delivery_method' => $this->delivery,
                'delivery_address' => $this->address,
                'user' => Auth::user()?->is_admin ? null : Auth::user(),
                'await_mpesa' => $awaitMpesa,
            ]);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?? 'Unable to place order.';
            Flux::toast(variant: 'danger', text: $message);
            throw $exception;
        }

        if (! $awaitMpesa) {
            $this->showPaid($order, $cart);
            Flux::toast(variant: 'success', text: 'Order '.$order->reference.' placed.');

            return;
        }

        $attempt = $order->paymentAttempts()->latest('id')->first();

        if ($attempt === null) {
            $this->status = 'failure';
            $this->failureReason = 'M-Pesa could not start the payment.';

            return;
        }

        session(['checkout.order_id' => $order->id]);
        $this->paymentAttemptId = $attempt->id;
        $this->orderRef = $order->reference;
        $this->amountDue = $order->total;
        $this->failureReason = '';

        try {
            $daraja->requestPayment($attempt);
            $this->status = 'pending';
        } catch (ValidationException $exception) {
            if (array_key_exists('payment_pending', $exception->errors())) {
                $this->status = 'pending';
                $this->failureReason = (string) (collect($exception->errors())->flatten()->first() ?? '');

                return;
            }

            $fresh = $attempt->fresh();

            if ($fresh !== null && $fresh->status === 'initiated') {
                $orders->markShopPaymentFailed(
                    $fresh,
                    (string) (collect($exception->errors())->flatten()->first() ?? 'M-Pesa could not start the payment.'),
                );
            }

            session()->forget('checkout.order_id');
            $this->status = 'failure';
            $this->failureReason = (string) (collect($exception->errors())->flatten()->first() ?? 'M-Pesa could not start the payment.');
        }
    }

    public function refreshShopPayment(DarajaStkService $daraja, CartService $cart): void
    {
        if ($this->status !== 'pending' || $this->paymentAttemptId === null) {
            return;
        }

        $attempt = PaymentAttempt::query()->find($this->paymentAttemptId);

        if ($attempt === null) {
            return;
        }

        if ($attempt->status === 'initiated' && filled($attempt->checkout_request_id)) {
            $daraja->queryPayment($attempt);
            $attempt->refresh();
        }

        if ($attempt->status === 'succeeded') {
            $order = $attempt->order;

            if ($order !== null) {
                $this->showPaid($order, $cart);
                Flux::toast(variant: 'success', text: 'Order '.$order->reference.' paid.');
            }

            return;
        }

        if ($attempt->status === 'failed') {
            session()->forget('checkout.order_id');
            $this->status = 'failure';
            $this->failureReason = (string) ($attempt->failure_reason ?: 'The M-Pesa payment was not completed.');
        }
    }

    public function resetCheckout(): void
    {
        $this->status = 'form';
        $this->failureReason = '';
        $this->paymentAttemptId = null;
        $this->orderRef = '';
        $this->amountDue = 0;
    }

    private function resumeWaiting(Order $order): void
    {
        $this->status = 'pending';
        $this->orderRef = $order->reference;
        $this->amountDue = $order->total;
        $attemptId = $order->paymentAttempts()->latest('id')->value('id');
        $this->paymentAttemptId = is_numeric($attemptId) ? (int) $attemptId : null;
        $phone = $order->customer_phone;
        $this->phone = str_starts_with($phone, '254') ? '0'.substr($phone, 3) : $phone;
    }

    private function showPaid(Order $order, CartService $cart): void
    {
        $cart->clear();
        session()->forget('checkout.order_id');
        $this->orderRef = $order->reference;
        $this->status = 'success';
        $this->dispatch('cart-updated');
    }

    public function with(): array
    {
        $cart = app(CartService::class);
        $subtotal = $cart->subtotal();

        return [
            'lines' => $cart->lines(),
            'subtotal' => $subtotal,
            'deliveryChargeLabel' => Order::deliveryChargeLabelFor($this->delivery),
            'total' => $subtotal,
            'hasNonReturnable' => $cart->lines()->contains(fn (array $line): bool => ! $line['product']->is_returnable),
        ];
    }
}; ?>

<div class="mx-auto max-w-5xl px-4 py-10">
    @if ($status === 'success')
        <div class="mx-auto max-w-lg rounded-2xl border border-border bg-white p-8 text-center shadow-sm">
            <div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-success/15">
                <flux:icon.check-circle class="size-6 text-success" />
            </div>
            <h1 class="mt-4 text-2xl font-bold text-navy">Order received</h1>
            <p class="mt-2 text-sm text-muted-foreground">
                Payment confirmed. Reference
                <span class="font-mono font-semibold text-zinc-900">{{ $orderRef }}</span>.
            </p>
            <p class="mt-3 text-sm text-zinc-700">
                You may cancel free of charge anytime before dispatch. After delivery, eligible unused items can be returned within 7 days;
                see our <a href="{{ route('legal') }}#refunds" class="font-medium text-navy underline">Refund Policy</a>.
            </p>
            <div class="mt-6 flex flex-wrap justify-center gap-2">
                <flux:button href="{{ route('orders.show', $orderRef) }}" class="bg-accent! text-accent-foreground!">View receipt</flux:button>
                <flux:button href="{{ route('shop.index') }}" variant="outline">Continue shopping</flux:button>
            </div>
        </div>
    @elseif ($status === 'pending')
        <div wire:poll.3s.visible="refreshShopPayment" class="mx-auto max-w-lg rounded-2xl border border-border bg-white p-6 text-center shadow-sm sm:p-8">
            <div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-navy/10">
                <flux:icon.device-phone-mobile class="size-6 text-navy" />
            </div>
            <h1 class="mt-4 text-2xl font-bold text-navy">Check your phone</h1>
            <p class="mt-2 text-sm text-muted-foreground">
                Safaricom is sending an M-Pesa prompt for {{ Money::kes($amountDue) }}
                @if ($phone !== '')
                    to {{ $phone }}
                @endif
                . Enter your PIN to pay for order
                <span class="font-mono font-semibold text-zinc-900">{{ $orderRef }}</span>.
            </p>
            <p class="mt-2 text-xs text-muted-foreground">This page checks with Safaricom until the payment is confirmed. Keep it open.</p>
            @if ($failureReason !== '')
                <p class="mt-2 text-xs text-amber-800">{{ $failureReason }}</p>
            @endif
        </div>
    @elseif ($status === 'failure')
        <div class="mx-auto max-w-lg rounded-2xl border border-border bg-white p-6 text-center shadow-sm sm:p-8">
            <h1 class="text-2xl font-bold text-navy">Payment not completed</h1>
            <p class="mt-2 text-sm text-muted-foreground">{{ $failureReason !== '' ? $failureReason : 'The M-Pesa payment was not completed.' }}</p>
            <p class="mt-2 text-sm text-zinc-700">No money was taken. Your items are still in the cart.</p>
            <div class="mt-6">
                <flux:button wire:click="resetCheckout" class="w-full bg-accent! text-accent-foreground! sm:w-auto">Try again</flux:button>
            </div>
        </div>
    @else
        <h1 class="text-3xl font-bold text-navy">Checkout</h1>
        <p class="mt-2 text-sm text-muted-foreground">
            Pay the total with M-Pesa. You will get a prompt on this number. Delivery outside Nairobi is confirmed later and is not part of this payment.
        </p>

        <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_320px]">
            <form wire:submit="placeOrder" class="space-y-4 rounded-2xl border border-border bg-white p-6">
                <flux:field>
                    <flux:label>Full name</flux:label>
                    <flux:input wire:model="name" />
                    <flux:error name="name" />
                </flux:field>
                <flux:field>
                    <flux:label>Mobile number</flux:label>
                    <flux:input wire:model="phone" inputmode="tel" placeholder="07XX or 01XX XXX XXX" />
                    <flux:error name="phone" />
                </flux:field>
                <flux:field>
                    <flux:label>Email <span class="font-normal text-muted-foreground">(optional)</span></flux:label>
                    <flux:input wire:model="email" type="email" />
                    <flux:error name="email" />
                </flux:field>
                <flux:field>
                    <flux:label>Delivery method</flux:label>
                    <flux:select wire:model.live="delivery">
                        <flux:select.option value="nairobi">Delivery within Nairobi: free</flux:select.option>
                        <flux:select.option value="collection">Office collection (Westlands): free</flux:select.option>
                        <flux:select.option value="upcountry">Delivery outside Nairobi: charges apply</flux:select.option>
                    </flux:select>
                </flux:field>
                @if ($delivery !== 'collection')
                    <flux:field>
                        <flux:label>Delivery address</flux:label>
                        <flux:textarea wire:model="address" rows="3" />
                        <flux:error name="address" />
                    </flux:field>
                @endif

                <div class="rounded-lg border border-border bg-surface p-4 text-xs leading-relaxed text-zinc-700">
                    <p class="font-semibold text-zinc-900">Before you pay</p>
                    <ul class="mt-2 list-disc space-y-1 pl-4">
                        <li>Cancel free of charge before we dispatch (Cancellation Policy 5.3).</li>
                        <li>Eligible shop returns within 7 days of delivery if unused and in original packaging (Refund Policy 4.4).</li>
                        @if ($delivery === Order::DELIVERY_UPCOUNTRY)
                            <li>Delivery fee charges apply outside Nairobi. The amount is confirmed when you collect or when the item is delivered.</li>
                        @endif
                        @if ($hasNonReturnable)
                            <li class="text-amber-800">Your cart includes one or more non-returnable items.</li>
                        @endif
                    </ul>
                </div>

                <flux:button type="submit" wire:loading.attr="disabled" class="w-full bg-accent! text-accent-foreground!">
                    Pay with M-Pesa: {{ Money::kes($total) }}
                </flux:button>
            </form>

            <aside class="h-fit rounded-2xl border border-border bg-white p-5">
                <h2 class="text-base font-semibold">Order summary</h2>
                <div class="mt-3 space-y-2 text-sm">
                    @foreach ($lines as $line)
                        <div class="flex justify-between gap-3">
                            <span class="text-muted-foreground">{{ $line['qty'] }} × {{ $line['product']->name }}</span>
                            <span class="font-medium">{{ Money::kes($line['line_total']) }}</span>
                        </div>
                    @endforeach
                    <div class="flex justify-between text-muted-foreground">
                        <span>Delivery</span>
                        <span>{{ $deliveryChargeLabel }}</span>
                    </div>
                </div>
                <div class="mt-3 flex justify-between border-t border-border pt-3 text-base font-bold">
                    <span>Total</span>
                    <span class="text-navy">{{ Money::kes($total) }}</span>
                </div>
            </aside>
        </div>
    @endif
</div>
