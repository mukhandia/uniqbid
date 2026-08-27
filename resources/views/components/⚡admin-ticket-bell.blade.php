<?php

use App\Models\SupportTicket;
use Livewire\Component;

new class extends Component {
    public function with(): array
    {
        return [
            'unreadCount' => SupportTicket::query()->unread()->count(),
        ];
    }
}; ?>

<div class="inline-flex" wire:poll.3s.visible>
    <a
        href="{{ route('admin.tickets') }}"
        wire:navigate
        class="relative inline-flex size-10 items-center justify-center rounded-lg text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800"
        aria-label="{{ $unreadCount === 1 ? '1 unread support ticket' : $unreadCount.' unread support tickets' }}"
        data-test="admin-ticket-bell"
    >
        <flux:icon.bell class="size-5" />
        @if ($unreadCount > 0)
            <span class="absolute right-0.5 top-0.5 grid min-w-4 place-items-center rounded-full bg-red-600 px-1 text-[10px] font-semibold leading-4 text-white">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </a>
</div>
