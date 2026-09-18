<?php

use App\Models\Product;
use App\Models\SupportTicket;
use App\Services\AdminAttentionService;
use Livewire\Component;

new class extends Component {
    public string $placement = 'up';

    public function openTicket(int $ticketId): void
    {
        $user = auth()->user();
        abort_unless(app(AdminAttentionService::class)->canViewTickets($user), 403);

        $ticket = SupportTicket::query()->findOrFail($ticketId);
        $ticket->markRead();

        $this->redirect(route('admin.tickets.show', $ticket), navigate: true);
    }

    public function openGuestListing(int $productId): void
    {
        $user = auth()->user();
        abort_unless(app(AdminAttentionService::class)->canViewGuestListings($user), 403);

        $listing = Product::query()->pendingGuestApproval()->findOrFail($productId);
        $listing->markAdminSeen();

        $this->redirect(route('admin.guest-listings', ['status' => 'pending']), navigate: true);
    }

    public function with(): array
    {
        $attention = app(AdminAttentionService::class);
        $user = auth()->user();

        return [
            'totalCount' => $attention->totalCount($user),
            'ariaLabel' => $attention->ariaLabel($user),
            'items' => $attention->items($user),
            'canViewTickets' => $attention->canViewTickets($user),
            'canViewGuestListings' => $attention->canViewGuestListings($user),
        ];
    }
}; ?>

<div
    class="relative inline-flex"
    x-data="{ open: false }"
    @keydown.escape.window="open = false"
    wire:poll.3s.visible
>
    <button
        type="button"
        class="relative inline-flex size-10 items-center justify-center rounded-lg text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800"
        aria-label="{{ $ariaLabel }}"
        aria-expanded="false"
        x-bind:aria-expanded="open.toString()"
        data-test="admin-attention-bell"
        @click="open = !open"
    >
        <flux:icon.bell class="size-5" />
        @if ($totalCount > 0)
            <span class="absolute right-0.5 top-0.5 grid min-w-4 place-items-center rounded-full bg-red-600 px-1 text-[10px] font-semibold leading-4 text-white">
                {{ $totalCount > 9 ? '9+' : $totalCount }}
            </span>
        @endif
    </button>

    <div
        x-cloak
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        @click.outside="open = false"
        @class([
            'absolute z-50 w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-900',
            'bottom-full left-0 mb-2 origin-bottom-left' => $placement === 'up',
            'top-full right-0 mt-2 origin-top-right' => $placement === 'down',
        ])
        data-test="admin-attention-panel"
    >
        <div class="border-b border-zinc-200 px-3 py-2 dark:border-zinc-700">
            <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Needs attention</div>
            <div class="text-xs text-zinc-500">Open an item to mark it as seen.</div>
        </div>

        <ul class="max-h-80 divide-y divide-zinc-100 overflow-y-auto dark:divide-zinc-800">
            @forelse ($items as $item)
                <li wire:key="attention-{{ $item['type'] }}-{{ $item['id'] }}">
                    <button
                        type="button"
                        class="flex w-full flex-col gap-0.5 px-3 py-2.5 text-left hover:bg-zinc-50 dark:hover:bg-zinc-800/80"
                        wire:click="{{ $item['type'] === 'ticket' ? 'openTicket('.$item['id'].')' : 'openGuestListing('.$item['id'].')' }}"
                        @click="open = false"
                    >
                        <span class="text-xs font-semibold uppercase tracking-wide text-zinc-500">
                            {{ $item['type'] === 'ticket' ? 'Support ticket' : 'Guest listing' }}
                        </span>
                        <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $item['title'] }}</span>
                        <span class="text-xs text-zinc-500">{{ $item['subtitle'] }}</span>
                        @if ($item['created_at'] !== '')
                            <span class="text-[11px] text-zinc-400">{{ $item['created_at'] }}</span>
                        @endif
                    </button>
                </li>
            @empty
                <li class="px-3 py-6 text-center text-sm text-zinc-500">All caught up. Nothing new.</li>
            @endforelse
        </ul>

        @if ($canViewTickets || $canViewGuestListings)
            <div class="flex flex-wrap gap-2 border-t border-zinc-200 px-3 py-2 dark:border-zinc-700">
                @if ($canViewTickets)
                    <a href="{{ route('admin.tickets') }}" wire:navigate class="text-xs font-medium text-navy hover:underline dark:text-sky-300" @click="open = false">
                        All tickets
                    </a>
                @endif
                @if ($canViewGuestListings)
                    <a href="{{ route('admin.guest-listings') }}" wire:navigate class="text-xs font-medium text-navy hover:underline dark:text-sky-300" @click="open = false">
                        Guest listings
                    </a>
                @endif
            </div>
        @endif
    </div>
</div>
