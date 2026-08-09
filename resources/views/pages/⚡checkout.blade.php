<?php

use App\Services\CartService;
use App\Support\Money;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.site')]
#[Title('Checkout — UniqBid Shop')]
class extends Component {
    public string $name = '';

    public string $phone = '';

    public string $delivery = 'nairobi';

    public string $address = '';

    public string $status = 'form';

    public string $orderRef = '';

    public function mount(CartService $cart): void
    {
        if ($cart->count() === 0) {
            $this->redirect(route('cart'), navigate: true);
        }
    }

    public function placeOrder(CartService $cart): void
    {
        $this->validate([
            'name' => ['required', 'string', 'min:2'],
            'phone' => ['required', 'regex:/^0\d{9}$/'],
            'delivery' => ['required', 'in:nairobi,collection,upcountry'],
            'address' => [$this->delivery === 'collection' ? 'nullable' : 'required', 'string'],
        ]);

        $this->orderRef = 'ORD'.random_int(10000, 99999);
        $cart->clear();
        $this->status = 'success';

        Flux::toast(variant: 'success', text: 'Order '.$this->orderRef.' placed (demo).');
    }

    public function with(): array
    {
        $cart = app(CartService::class);

        return [
            'lines' => $cart->lines(),
            'subtotal' => $cart->subtotal(),
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
                Demo checkout complete. Reference <span class="font-mono font-semibold text-zinc-900">{{ $orderRef }}</span>.
            </p>
            <div class="mt-6 flex justify-center gap-2">
                <flux:button href="{{ route('shop.index') }}" class="bg-navy! text-navy-foreground!">Continue shopping</flux:button>
                <flux:button href="{{ route('account') }}" variant="outline">My account</flux:button>
            </div>
        </div>
    @else
        <h1 class="text-3xl font-bold text-navy">Checkout</h1>
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
                    <flux:label>Delivery method</flux:label>
                    <flux:select wire:model.live="delivery">
                        <flux:select.option value="nairobi">Delivery within Nairobi</flux:select.option>
                        <flux:select.option value="collection">Office collection (Westlands)</flux:select.option>
                        <flux:select.option value="upcountry">Delivery outside Nairobi</flux:select.option>
                    </flux:select>
                </flux:field>
                @if ($delivery !== 'collection')
                    <flux:field>
                        <flux:label>Delivery address</flux:label>
                        <flux:textarea wire:model="address" rows="3" />
                        <flux:error name="address" />
                    </flux:field>
                @endif
                <flux:button type="submit" class="w-full bg-gold! text-gold-foreground!">
                    Pay with M-Pesa (demo) — {{ Money::kes($subtotal) }}
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
                </div>
                <div class="mt-3 flex justify-between border-t border-border pt-3 text-base font-bold">
                    <span>Total</span>
                    <span class="text-navy">{{ Money::kes($subtotal) }}</span>
                </div>
            </aside>
        </div>
    @endif
</div>
