<?php

use App\Models\SupportTicket;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Layout('layouts.app')]
#[Title('Admin: Support tickets')]
class extends Component {
    use WithPagination;

    #[Url]
    public string $status = 'all';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $query = SupportTicket::query()
            ->orderByRaw('case when read_at is null then 0 else 1 end')
            ->latest();

        if ($this->status !== 'all') {
            $query->where('status', $this->status);
        }

        return [
            'tickets' => $query->paginate(20),
            'unreadCount' => SupportTicket::query()->unread()->count(),
        ];
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">Support tickets</flux:heading>
            <flux:text class="mt-1">
                {{ $unreadCount }} unread. Open a ticket to mark it as read.
            </flux:text>
        </div>
        <div class="w-full sm:w-56">
            <flux:select wire:model.live="status">
                <flux:select.option value="all">All statuses</flux:select.option>
                <flux:select.option value="open">Open</flux:select.option>
                <flux:select.option value="in_progress">In progress</flux:select.option>
                <flux:select.option value="closed">Closed</flux:select.option>
            </flux:select>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
            <thead class="bg-zinc-50 text-left dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 font-medium">Ticket</th>
                    <th class="px-4 py-3 font-medium">From</th>
                    <th class="px-4 py-3 font-medium">Subject</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium">Received</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($tickets as $ticket)
                    <tr wire:key="ticket-{{ $ticket->id }}" @class(['bg-navy/5 dark:bg-white/5' => $ticket->isUnread()])>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.tickets.show', $ticket) }}" class="font-mono text-navy hover:underline" wire:navigate>
                                {{ $ticket->reference() }}
                            </a>
                            @if ($ticket->isUnread())
                                <span class="ml-2 rounded-full bg-red-600 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white">New</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div @class(['font-medium' => $ticket->isUnread()])>{{ $ticket->name }}</div>
                            <div class="text-xs text-zinc-500">{{ $ticket->email ?? $ticket->phone }}</div>
                        </td>
                        <td class="px-4 py-3">{{ $ticket->subject }}</td>
                        <td class="px-4 py-3">
                            <flux:badge size="sm" :color="$ticket->status === 'closed' ? 'zinc' : ($ticket->status === 'in_progress' ? 'amber' : 'blue')">
                                {{ $ticket->statusLabel() }}
                            </flux:badge>
                        </td>
                        <td class="px-4 py-3 text-xs text-zinc-600 dark:text-zinc-300">
                            {{ $ticket->created_at?->timezone('Africa/Nairobi')->format('d M Y H:i') }} EAT
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-zinc-500">No support tickets yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $tickets->links() }}</div>
</div>
