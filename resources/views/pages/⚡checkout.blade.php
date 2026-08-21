<?php

use App\Models\Order;
use App\Services\CartService;
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
#[Title('Checkout — UniqBid Shop')]
class extends Component {
    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $delivery = Order::DELIVERY_NAIROBI;

    public string $address = '';

    public string $status = 'form';

    public string $orderRef = '';

    public function mount(CartService $cart): void
    {
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

    public function placeOrder(OrderService $orders, CartService $cart): void
    {
        if ($cart->count() === 0) {
            $this->redirect(route('cart'), navigate: true);

            return;
        }

        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'phone' => ['required', 'regex:/^(\+?254|0)7\d{8}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'delivery' => ['required', 'in:nairobi,collection,upcountry'],
            'address' => [$this->delivery === Order::DELIVERY_COLLECTION ? 'nullable' : 'required', 'string', 'max:1000'],
        ]);

        try {
            $order = $orders->placeFromCart([
                'name' => $this->name,
                'phone' => $this->phone,
                'email' => $this->email !== '' ? $this->email : null,
                'delivery_method' => $this->delivery,
                'delivery_address' => $this->address,
                'user' => Auth::user()?->is_admin ? null : Auth::user(),
            ]);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?? 'Unable to place order.';
            Flux::toast(variant: 'danger', text: $message);
            throw $exception;
        }

        $this->orderRef = $order->reference;
        $this->status = 'success';

        Flux::toast(variant: 'success', text: 'Order '.$order->reference.' placed.');
    }

    public function with(): array
    {
        $cart = app(CartService::class);
        $orders = app(OrderService::class);
        $subtotal = $cart->subtotal();
        $deliveryFee = $orders->deliveryFee($this->delivery);

        return [
            'lines' => $cart->lines(),
            'subtotal' => $subtotal,
            'deliveryFee' => $deliveryFee,
            'total' => $subtotal + $deliveryFee,
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
                Payment confirmed (demo M-Pesa). Reference
                <span class="font-mono font-semibold text-zinc-900">{{ $orderRef }}</span>.
            </p>
            <p class="mt-3 text-sm text-zinc-700">
                You may cancel free of charge anytime before dispatch. After delivery, eligible unused items can be returned within 7 days —
                see our <a href="{{ route('legal') }}#refunds" class="font-medium text-navy underline">Refund Policy</a>.
            </p>
            <div class="mt-6 flex flex-wrap justify-center gap-2">
                <flux:button href="{{ route('orders.show', $orderRef) }}" class="bg-accent! text-accent-foreground!">View receipt</flux:button>
                <flux:button href="{{ route('shop.index') }}" variant="outline">Continue shopping</flux:button>
            </div>
        </div>
    @else
        <h1 class="text-3xl font-bold text-navy">Checkout</h1>
        <p class="mt-2 text-sm text-muted-foreground">
            M-Pesa is the default payment method. Live Daraja STK Push arrives in Milestone 2.6 — this checkout records a paid demo order.
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
                    <flux:input wire:model="phone" inputmode="tel" placeholder="07XX XXX XXX" />
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
                        <flux:select.option value="nairobi">Delivery within Nairobi — free</flux:select.option>
                        <flux:select.option value="collection">Office collection (Westlands) — free</flux:select.option>
                        <flux:select.option value="upcountry">Delivery outside Nairobi — {{ Money::kes(350) }}</flux:select.option>
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
                        @if ($hasNonReturnable)
                            <li class="text-amber-800">Your cart includes one or more non-returnable items.</li>
                        @endif
                    </ul>
                </div>

                <flux:button type="submit" class="w-full bg-accent! text-accent-foreground!">
                    Pay with M-Pesa (demo) — {{ Money::kes($total) }}
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
                        <span>{{ $deliveryFee === 0 ? 'Free' : Money::kes($deliveryFee) }}</span>
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
