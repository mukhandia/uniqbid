<?php

use Spatie\Activitylog\Models\Activity;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Layout('layouts.app')]
#[Title('Admin: Activity Log')]
class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $logName = '';

    #[Url(except: '')]
    public string $event = '';

    #[Url(except: '')]
    public string $dateFrom = '';

    #[Url(except: '')]
    public string $dateTo = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedLogName(): void
    {
        $this->resetPage();
    }

    public function updatedEvent(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->logName = '';
        $this->event = '';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->resetPage();
    }

    public function with(): array
    {
        $query = Activity::query()
            ->with(['causer', 'subject'])
            ->latest();

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $query->where(function ($inner) use ($term): void {
                $inner->where('description', 'like', $term)
                    ->orWhere('log_name', 'like', $term)
                    ->orWhere('event', 'like', $term)
                    ->orWhere('subject_type', 'like', $term);
            });
        }

        if ($this->logName !== '') {
            $query->where('log_name', $this->logName);
        }

        if ($this->event !== '') {
            $query->where('event', $this->event);
        }

        if ($this->dateFrom !== '') {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        return [
            'activities' => $query->paginate(25),
            'logNames' => Activity::query()->whereNotNull('log_name')->distinct()->orderBy('log_name')->pluck('log_name'),
            'events' => Activity::query()->whereNotNull('event')->distinct()->orderBy('event')->pluck('event'),
            'hasActiveFilters' => $this->search !== ''
                || $this->logName !== ''
                || $this->event !== ''
                || $this->dateFrom !== ''
                || $this->dateTo !== '',
        ];
    }
}; ?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">Activity Log</flux:heading>
        <flux:text class="mt-1">System audit trail for sales, products, redeem, logistics, roles, settings, and verification decisions.</flux:text>
    </div>

    <div class="flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-end">
        <div class="lg:w-64">
            <flux:field>
                <flux:label>Search</flux:label>
                <flux:input wire:model.live.debounce.400ms="search" placeholder="Description, log, event…" />
            </flux:field>
        </div>
        <div class="lg:w-48">
            <flux:field>
                <flux:label>Log name</flux:label>
                <flux:select wire:model.live="logName">
                    <flux:select.option value="">All logs</flux:select.option>
                    @foreach ($logNames as $name)
                        <flux:select.option value="{{ $name }}">{{ $name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>
        </div>
        <div class="lg:w-48">
            <flux:field>
                <flux:label>Event</flux:label>
                <flux:select wire:model.live="event">
                    <flux:select.option value="">All events</flux:select.option>
                    @foreach ($events as $eventName)
                        <flux:select.option value="{{ $eventName }}">{{ $eventName }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>
        </div>
        <div class="lg:w-44">
            <flux:field>
                <flux:label>From</flux:label>
                <flux:input type="date" wire:model.live="dateFrom" />
            </flux:field>
        </div>
        <div class="lg:w-44">
            <flux:field>
                <flux:label>To</flux:label>
                <flux:input type="date" wire:model.live="dateTo" />
            </flux:field>
        </div>
        @if ($hasActiveFilters)
            <flux:button type="button" variant="ghost" wire:click="clearFilters">Clear filters</flux:button>
        @endif
    </div>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
            <thead class="bg-zinc-50 text-left dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 font-medium">When</th>
                    <th class="px-4 py-3 font-medium">Description</th>
                    <th class="px-4 py-3 font-medium">Log</th>
                    <th class="px-4 py-3 font-medium">Event</th>
                    <th class="px-4 py-3 font-medium">Causer</th>
                    <th class="px-4 py-3 font-medium">Subject</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($activities as $activity)
                    <tr wire:key="activity-{{ $activity->id }}">
                        <td class="px-4 py-3 text-xs text-zinc-500 whitespace-nowrap">
                            {{ $activity->created_at?->timezone('Africa/Nairobi')->format('d M Y H:i') }} EAT
                        </td>
                        <td class="px-4 py-3 font-medium">{{ $activity->description }}</td>
                        <td class="px-4 py-3">
                            @if ($activity->log_name)
                                <flux:badge size="sm" color="zinc">{{ $activity->log_name }}</flux:badge>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs">{{ $activity->event ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs">
                            {{ $activity->causer?->email ?? $activity->causer?->name ?? 'System' }}
                        </td>
                        <td class="px-4 py-3 text-xs text-zinc-500">
                            @if ($activity->subject_type)
                                {{ class_basename($activity->subject_type) }} #{{ $activity->subject_id }}
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-zinc-500">No activity recorded yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $activities->links() }}</div>
</div>
