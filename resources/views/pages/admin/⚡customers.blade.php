<?php

use App\Models\Bidder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.app')]
#[Title('Admin: Customers')]
class extends Component {
    //
}; ?>

@php
    $customers = Bidder::query()
        ->select(['id', 'user_id', 'name', 'email', 'phone', 'whatsapp_number', 'created_at', 'updated_at'])
        ->with([
            'user:id,name,email,phone,phone_verified_at',
            'user.customerControl:id,user_id,self_excluded_until,verification_status',
            'latestBid',
            'latestBid.auction:id,name',
        ])
        ->withCount('bids')
        ->latest('updated_at')
        ->paginate(30);
@endphp

<div class="space-y-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <a href="{{ route('admin.auctions') }}" class="text-sm text-zinc-500 hover:underline">← Sales</a>
            <flux:heading size="xl" class="mt-2">Customers</flux:heading>
            <flux:text class="mt-1">Guest bidder profiles and verified mobile accounts. Guest profiles become accounts only after mobile OTP verification.</flux:text>
        </div>
        <flux:button href="{{ route('admin.customer-verifications') }}" variant="outline">
            Customer verification
        </flux:button>
    </div>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
            <thead class="bg-zinc-50 text-left dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 font-medium">Customer</th>
                    <th class="px-4 py-3 font-medium">Account</th>
                    <th class="px-4 py-3 font-medium">Bids</th>
                    <th class="px-4 py-3 font-medium">Last activity</th>
                    <th class="px-4 py-3 font-medium">Bidding controls</th>
                    <th class="px-4 py-3 font-medium">Verification</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($customers as $customer)
                    @php
                        $control = $customer->user?->customerControl;
                        $isVerifiedAccount = $customer->user?->phone_verified_at !== null;
                    @endphp
                    <tr wire:key="customer-{{ $customer->id }}">
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ $customer->name }}</div>
                            <div class="text-xs text-zinc-500">{{ $customer->phone }} · {{ $customer->email }}</div>
                            @if ($customer->whatsapp_number)
                                <div class="text-xs text-zinc-500">WhatsApp: {{ $customer->whatsapp_number }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <flux:badge size="sm" :color="$isVerifiedAccount ? 'green' : 'zinc'">
                                {{ $isVerifiedAccount ? 'Verified account' : 'Guest profile' }}
                            </flux:badge>
                        </td>
                        <td class="px-4 py-3">{{ $customer->bids_count }}</td>
                        <td class="px-4 py-3 text-xs text-zinc-600 dark:text-zinc-300">
                            @if ($customer->latestBid)
                                <div>{{ $customer->latestBid->auction?->name ?? 'Sale unavailable' }}</div>
                                <div>{{ $customer->latestBid->created_at->timezone('Africa/Nairobi')->format('d M Y H:i') }} EAT</div>
                            @else
                                <span>Profile created {{ $customer->created_at->timezone('Africa/Nairobi')->format('d M Y H:i') }} EAT</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($control?->self_excluded_until?->isFuture())
                                <flux:badge size="sm" color="red">
                                    Self-excluded until {{ $control->self_excluded_until->timezone('Africa/Nairobi')->format('d M Y') }}
                                </flux:badge>
                            @else
                                <span class="text-xs text-zinc-500">No active exclusion</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($isVerifiedAccount)
                                <flux:badge size="sm" :color="match ($control?->verification_status ?? 'not_requested') {
                                    'verified' => 'green',
                                    'requested' => 'amber',
                                    'rejected' => 'red',
                                    default => 'zinc',
                                }">
                                    {{ str_replace('_', ' ', $control?->verification_status ?? 'not_requested') }}
                                </flux:badge>
                            @else
                                <span class="text-xs text-zinc-500">Available after OTP</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-zinc-500">No customer profiles yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $customers->links() }}
    </div>
</div>
