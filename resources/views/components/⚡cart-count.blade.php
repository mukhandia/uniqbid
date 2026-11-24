<?php

use App\Services\CartService;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    #[On('cart-updated')]
    public function refreshCount(): void
    {
        // Re-render so the badge picks up the latest session cart count.
    }

    public function with(): array
    {
        return [
            'count' => app(CartService::class)->count(),
        ];
    }
};
?>

<a href="{{ route('cart') }}" class="relative rounded-md p-2 text-zinc-900 hover:bg-muted" wire:navigate aria-label="Cart{{ $count > 0 ? ', '.$count.' items' : '' }}">
    <flux:icon.shopping-cart class="size-5" />
    @if ($count > 0)
        <span class="absolute -right-0.5 -top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-gold px-1 text-[10px] font-bold text-gold-foreground">
            {{ $count }}
        </span>
    @endif
</a>
