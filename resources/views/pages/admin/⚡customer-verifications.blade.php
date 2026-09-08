<?php

use App\Models\User;
use App\Support\AuditLogger;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.app')]
#[Title('Admin: Customer verification')]
class extends Component {
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
}; ?>

@php
    $customers = User::query()
        ->where('is_admin', false)
        ->whereNotNull('phone')
        ->with('customerControl')
        ->latest()
        ->paginate(30);
@endphp

<div class="space-y-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <a href="{{ route('admin.auctions') }}" class="text-sm text-zinc-500 hover:underline">← Sales</a>
            <flux:heading size="xl" class="mt-2">Customer verification</flux:heading>
            <flux:text class="mt-1">Record optional ID / KRA verification decisions. Do not store ID numbers or document images here.</flux:text>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
            <thead class="bg-zinc-50 text-left dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 font-medium">Customer</th>
                    <th class="px-4 py-3 font-medium">Mobile</th>
                    <th class="px-4 py-3 font-medium">Current status</th>
                    <th class="px-4 py-3 font-medium text-right">Update</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($customers as $customer)
                    @php($status = $customer->customerControl?->verification_status ?? 'not_requested')
                    <tr wire:key="verification-customer-{{ $customer->id }}">
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ $customer->name }}</div>
                            <div class="text-xs text-zinc-500">{{ $customer->email }}</div>
                        </td>
                        <td class="px-4 py-3">{{ $customer->phone }}</td>
                        <td class="px-4 py-3">
                            <flux:badge size="sm" :color="match ($status) {
                                'verified' => 'green',
                                'requested' => 'amber',
                                'rejected' => 'red',
                                default => 'zinc',
                            }">
                                {{ str_replace('_', ' ', $status) }}
                            </flux:badge>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
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
                        <td colspan="4" class="px-4 py-8 text-center text-zinc-500">No mobile customers yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $customers->links() }}
    </div>
</div>
