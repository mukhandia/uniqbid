<?php

use App\Services\OrderService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.site')]
#[Title('Track order: UniqBid Shop')]
class extends Component {
    public string $reference = '';

    public function mount(): void
    {
        $this->reference = strtoupper((string) request()->query('ref', ''));
    }

    public function track(OrderService $orders): void
    {
        $this->validate([
            'reference' => ['required', 'string', 'min:6', 'max:32'],
        ]);

        $order = $orders->findByReference($this->reference);

        if ($order === null) {
            $this->addError('reference', 'No order found for that reference.');

            return;
        }

        $this->redirect(route('orders.show', $order), navigate: true);
    }
}; ?>

<div class="mx-auto max-w-lg px-4 py-16">
    <h1 class="text-3xl font-bold text-navy">Track your order</h1>
    <p class="mt-2 text-sm text-muted-foreground">
        Enter the order reference from your receipt or confirmation message (for example ORD250821AB12CD).
    </p>

    <form wire:submit="track" class="mt-8 space-y-4 rounded-2xl border border-border bg-white p-6">
        <flux:field>
            <flux:label>Order reference</flux:label>
            <flux:input wire:model="reference" class="font-mono uppercase" placeholder="ORD…" />
            <flux:error name="reference" />
        </flux:field>
        <flux:button type="submit" class="w-full bg-accent! text-accent-foreground!">
            Track order
        </flux:button>
    </form>

    <p class="mt-6 text-center text-sm text-muted-foreground">
        Need help? <a href="{{ route('contact') }}" class="font-medium text-navy underline" wire:navigate>Contact support</a>
        with your order reference and M-Pesa code.
    </p>
</div>
