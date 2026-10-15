<?php

use App\Models\User;
use App\Support\AuditLogger;
use App\Support\CustomerSearch;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Layout('layouts.app')]
#[Title('Admin: Customer verification')]
class extends Component {
    use WithPagination;

    private const STATUSES = ['submitted', 'requested', 'verified', 'rejected', 'not_requested'];

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 'all')]
    public string $status = 'all';

    #[Url(except: 'all')]
    public string $documents = 'all';

    #[Url(except: 'all')]
    public string $period = 'all';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'documents', 'period'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status', 'documents', 'period');
        $this->resetPage();
    }

    public function updateStatus(int $userId, string $status): void
    {
        Validator::validate(['status' => $status], [
            'status' => ['required', Rule::in(['not_requested', 'requested', 'verified', 'rejected'])],
        ]);

        $user = User::query()
            ->where('is_admin', false)
            ->whereNotNull('phone')
            ->findOrFail($userId);

        $control = $user->customerControl()->firstOrCreate([]);
        $control->update([
            'verification_status' => $status,
            'verification_requested_at' => $status === 'requested' ? now() : $control->verification_requested_at,
        ]);

        AuditLogger::log('customers', 'verification status updated', 'verification_updated', $user, [
            'verification_status' => $status,
        ]);

        Flux::toast(variant: 'success', text: 'Verification status updated.');
    }

    public function with(): array
    {
        $term = trim($this->search);

        $customers = $this->customersQuery()
            ->with(['customerControl', 'verificationDocuments'])
            ->withCount('verificationDocuments')
            ->when($term !== '', function (Builder $query) use ($term): void {
                $like = '%'.$term.'%';

                $query->where(function (Builder $inner) use ($like, $term): void {
                    $inner->where('name', 'like', $like)->orWhere('email', 'like', $like);

                    foreach (CustomerSearch::phonePatterns($term) as $pattern) {
                        $inner->orWhere('phone', 'like', $pattern);
                    }
                });
            })
            ->when(in_array($this->status, self::STATUSES, true), fn (Builder $query) => $this->whereStatus($query, $this->status))
            ->when($this->documents === 'with', fn (Builder $query) => $query->has('verificationDocuments'))
            ->when($this->documents === 'without', fn (Builder $query) => $query->doesntHave('verificationDocuments'))
            ->when(in_array($this->period, ['today', '7d', '30d'], true), fn (Builder $query) => $query->whereHas(
                'customerControl',
                fn (Builder $control) => $control->where('verification_submitted_at', '>=', match ($this->period) {
                    'today' => now()->timezone('Africa/Nairobi')->startOfDay()->utc(),
                    '7d' => now()->subDays(7),
                    default => now()->subDays(30),
                }),
            ))
            ->orderByRaw("coalesce((select case when verification_status = 'submitted' then 0 else 1 end from customer_controls where customer_controls.user_id = users.id), 1)")
            ->latest()
            ->paginate(30);

        $statusCounts = collect(self::STATUSES)
            ->mapWithKeys(fn (string $status): array => [$status => $this->whereStatus($this->customersQuery(), $status)->count()]);

        return [
            'customers' => $customers,
            'statusCounts' => $statusCounts,
            'hasActiveFilters' => $term !== '' || $this->status !== 'all' || $this->documents !== 'all' || $this->period !== 'all',
        ];
    }

    private function customersQuery(): Builder
    {
        return User::query()
            ->where('is_admin', false)
            ->whereNotNull('phone');
    }

    private function whereStatus(Builder $query, string $status): Builder
    {
        if ($status === 'not_requested') {
            return $query->where(fn (Builder $inner) => $inner
                ->whereDoesntHave('customerControl')
                ->orWhereHas('customerControl', fn (Builder $control) => $control->where('verification_status', 'not_requested')));
        }

        return $query->whereHas('customerControl', fn (Builder $control) => $control->where('verification_status', $status));
    }
}; ?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">Customer verification</flux:heading>
        <flux:text class="mt-1">Review the ID / KRA documents customers upload from My Account, then verify or reject. Customers awaiting review are listed first. Documents are private: only admins can open them.</flux:text>
    </div>

    <div class="flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-end">
        <div class="lg:w-64">
            <flux:field>
                <flux:label>Search</flux:label>
                <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" placeholder="Name, phone or email" data-test="verification-search" />
            </flux:field>
        </div>
        <div class="lg:w-60">
            <flux:field>
                <flux:label>Status</flux:label>
                <flux:select wire:model.live="status">
                    <flux:select.option value="all">All statuses</flux:select.option>
                    <flux:select.option value="submitted">Awaiting review ({{ $statusCounts['submitted'] }})</flux:select.option>
                    <flux:select.option value="requested">Requested ({{ $statusCounts['requested'] }})</flux:select.option>
                    <flux:select.option value="verified">Verified ({{ $statusCounts['verified'] }})</flux:select.option>
                    <flux:select.option value="rejected">Rejected ({{ $statusCounts['rejected'] }})</flux:select.option>
                    <flux:select.option value="not_requested">Not requested ({{ $statusCounts['not_requested'] }})</flux:select.option>
                </flux:select>
            </flux:field>
        </div>
        <div class="lg:w-52">
            <flux:field>
                <flux:label>Documents</flux:label>
                <flux:select wire:model.live="documents">
                    <flux:select.option value="all">Any</flux:select.option>
                    <flux:select.option value="with">Has documents</flux:select.option>
                    <flux:select.option value="without">No documents</flux:select.option>
                </flux:select>
            </flux:field>
        </div>
        <div class="lg:w-48">
            <flux:field>
                <flux:label>Submitted</flux:label>
                <flux:select wire:model.live="period">
                    <flux:select.option value="all">Any time</flux:select.option>
                    <flux:select.option value="today">Today</flux:select.option>
                    <flux:select.option value="7d">Last 7 days</flux:select.option>
                    <flux:select.option value="30d">Last 30 days</flux:select.option>
                </flux:select>
            </flux:field>
        </div>
        @if ($hasActiveFilters)
            <flux:button type="button" variant="ghost" wire:click="clearFilters">Clear filters</flux:button>
        @endif
        <flux:text class="lg:ms-auto">
            {{ $customers->total() }} {{ $customers->total() === 1 ? 'customer' : 'customers' }}
        </flux:text>
    </div>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
            <thead class="bg-zinc-50 text-left dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 font-medium">Customer</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium">Documents</th>
                    <th class="px-4 py-3 text-right font-medium">Update</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($customers as $customer)
                    @php
                        $control = $customer->customerControl;
                        $status = $control?->verification_status ?? 'not_requested';
                    @endphp
                    <tr wire:key="verification-customer-{{ $customer->id }}" class="align-top">
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ $customer->name }}</div>
                            <div class="text-xs text-zinc-500">{{ $customer->phone }}</div>
                            @if ($customer->email)
                                <div class="text-xs text-zinc-500">{{ $customer->email }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <flux:badge size="sm" :color="match ($status) {
                                'verified' => 'green',
                                'submitted', 'requested' => 'amber',
                                'rejected' => 'red',
                                default => 'zinc',
                            }">
                                {{ str_replace('_', ' ', $status) }}
                            </flux:badge>
                            @if ($status === 'submitted' && $control?->verification_submitted_at)
                                <div class="mt-1 text-xs text-zinc-500">{{ $control->verification_submitted_at->timezone('Africa/Nairobi')->format('d M Y, H:i') }} EAT</div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($customer->verificationDocuments->isEmpty())
                                <span class="text-xs text-zinc-500">None uploaded</span>
                            @else
                                <ul class="space-y-1.5">
                                    @foreach ($customer->verificationDocuments as $document)
                                        <li wire:key="admin-verification-document-{{ $document->id }}" class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                            @if ($document->isPdf())
                                                <flux:icon.document-text class="size-4 shrink-0 text-zinc-500" />
                                            @else
                                                <flux:icon.photo class="size-4 shrink-0 text-zinc-500" />
                                            @endif
                                            <span class="max-w-[14rem] truncate" title="{{ $document->original_name }}">{{ $document->original_name }}</span>
                                            <span class="text-xs text-zinc-500">{{ $document->humanSize() }}</span>
                                            <a href="{{ route('admin.verification-documents.show', $document) }}" target="_blank" rel="noopener" class="text-xs font-medium text-sky-700 hover:underline dark:text-sky-300">View</a>
                                            <a href="{{ route('admin.verification-documents.show', ['document' => $document, 'download' => 1]) }}" class="text-xs font-medium text-sky-700 hover:underline dark:text-sky-300">Download</a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap justify-end gap-2">
                                <flux:button size="sm" variant="outline" wire:click="updateStatus({{ $customer->id }}, 'requested')">
                                    Request
                                </flux:button>
                                <flux:button size="sm" wire:click="updateStatus({{ $customer->id }}, 'verified')">
                                    Verify
                                </flux:button>
                                <flux:button size="sm" variant="outline" wire:click="updateStatus({{ $customer->id }}, 'rejected')">
                                    Reject
                                </flux:button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-zinc-500">No customers match this filter.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $customers->links() }}
    </div>
</div>
