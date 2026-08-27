<?php

use App\Models\SupportTicket;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.app')]
#[Title('Admin: Support ticket')]
class extends Component {
    public SupportTicket $ticket;

    public string $status = SupportTicket::STATUS_OPEN;

    public function mount(SupportTicket $ticket): void
    {
        $this->ticket = $ticket;
        $this->status = $ticket->status;
        $this->ticket->markRead();
        $this->ticket->refresh();
    }

    public function updateStatus(): void
    {
        $validated = $this->validate([
            'status' => ['required', Rule::in(array_keys(SupportTicket::statuses()))],
        ]);

        $this->ticket->update(['status' => $validated['status']]);
        $this->ticket->refresh();

        Flux::toast(variant: 'success', text: 'Ticket status updated.');
    }
}; ?>

<div class="space-y-6">
    <div>
        <a href="{{ route('admin.tickets') }}" class="text-sm text-zinc-500 hover:underline" wire:navigate>← All tickets</a>
        <div class="mt-2 flex flex-wrap items-center gap-3">
            <flux:heading size="xl" class="font-mono">{{ $ticket->reference() }}</flux:heading>
            <flux:badge :color="$ticket->status === 'closed' ? 'zinc' : ($ticket->status === 'in_progress' ? 'amber' : 'blue')">
                {{ $ticket->statusLabel() }}
            </flux:badge>
        </div>
        <flux:text class="mt-1">Received {{ $ticket->created_at?->timezone('Africa/Nairobi')->format('d M Y H:i') }} EAT</flux:text>
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_16rem]">
        <div class="rounded-xl border border-zinc-200 bg-white p-4 sm:p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="text-lg font-semibold">{{ $ticket->subject }}</h2>
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">Name</dt>
                    <dd class="mt-1">{{ $ticket->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">Email</dt>
                    <dd class="mt-1">{{ $ticket->email ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">Phone</dt>
                    <dd class="mt-1">{{ $ticket->phone ?? '—' }}</dd>
                </div>
            </dl>
            <div class="mt-6">
                <h3 class="text-xs font-medium uppercase tracking-wide text-zinc-500">Message</h3>
                <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed">{{ $ticket->message }}</p>
            </div>
        </div>

        <aside class="rounded-xl border border-zinc-200 bg-white p-4 sm:p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <form wire:submit="updateStatus" class="space-y-3">
                <flux:field>
                    <flux:label>Status</flux:label>
                    <flux:select wire:model="status">
                        <flux:select.option value="open">Open</flux:select.option>
                        <flux:select.option value="in_progress">In progress</flux:select.option>
                        <flux:select.option value="closed">Closed</flux:select.option>
                    </flux:select>
                </flux:field>
                <flux:button type="submit" class="w-full">Update status</flux:button>
            </form>
        </aside>
    </div>
</div>
