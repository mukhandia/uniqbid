<?php

use App\Models\AuctionBid;
use App\Models\Bidder;
use App\Models\PaymentAttempt;
use App\Services\CustomerAccountService;
use App\Services\CustomerAdminService;
use App\Support\Money;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Layout('layouts.app')]
#[Title('Admin: Customer')]
class extends Component {
    use WithPagination;

    public Bidder $bidder;

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $whatsapp = '';

    public function mount(Bidder $bidder): void
    {
        $this->bidder = $bidder;
    }

    public function openEdit(): void
    {
        $this->name = $this->bidder->name;
        $this->phone = $this->localPhone($this->bidder->phone);
        $this->email = (string) ($this->bidder->user?->email ?? $this->bidder->email);
        $this->whatsapp = $this->localPhone($this->bidder->whatsapp_number);
        $this->resetValidation();

        $this->modal('customer-form')->show();
    }

    public function updateCustomer(CustomerAdminService $customers): void
    {
        $this->bidder = $customers->update($this->bidder, [
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'whatsapp' => $this->whatsapp,
        ], auth()->user());

        $this->modal('customer-form')->close();

        Flux::toast(variant: 'success', text: 'Customer details saved.');
    }

    public function deleteCustomer(CustomerAdminService $customers): void
    {
        $name = $this->bidder->name;

        $customers->delete($this->bidder, auth()->user());

        Flux::toast(variant: 'success', text: $name.' deleted.');

        $this->redirectRoute('admin.customers', navigate: true);
    }

    public function with(): array
    {
        $customers = app(CustomerAdminService::class);
        $accounts = app(CustomerAccountService::class);
        $this->bidder->loadMissing(['user.customerControl']);
        $user = $this->bidder->user;

        $bids = $this->bidder->bids()
            ->with(['auction:id,name,slug,status,settled_at', 'auction.result', 'paymentAttempts'])
            ->latest()
            ->paginate(15, pageName: 'bids');

        return [
            'user' => $user,
            'control' => $user?->customerControl,
            'bids' => $bids,
            'bidStatus' => fn (AuctionBid $bid): array => [
                'label' => $accounts->statusLabel($status = $accounts->classifyBid($bid)),
                'color' => match ($status) {
                    'successful', 'refund_successful' => 'green',
                    'open' => 'sky',
                    'pending_payment', 'awaiting_refund' => 'amber',
                    'failed', 'refund_failed' => 'red',
                    default => 'zinc',
                },
            ],
            'paidBidsCount' => $this->bidder->bids()->where('status', 'accepted')->count(),
            'feesPaid' => (int) PaymentAttempt::query()
                ->where('status', 'succeeded')
                ->whereHas('auctionBid', fn ($query) => $query->where('bidder_id', $this->bidder->id))
                ->sum('amount'),
            'messages' => $this->bidder->messages()->with('auction:id,name')->latest('id')->limit(10)->get(),
            'documentsCount' => $user?->verificationDocuments()->count() ?? 0,
            'deletionBlockers' => $customers->deletionBlockers($this->bidder),
        ];
    }

    private function localPhone(?string $phone): string
    {
        if (blank($phone)) {
            return '';
        }

        return str_starts_with($phone, '254') ? '0'.substr($phone, 3) : $phone;
    }
}; ?>

@php
    $isVerifiedAccount = $user?->phone_verified_at !== null;
    $verificationStatus = $control?->verification_status ?? 'not_requested';
@endphp

<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('admin.customers') }}" class="text-sm text-zinc-500 hover:underline" wire:navigate>← All customers</a>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <flux:heading size="xl">{{ $bidder->name }}</flux:heading>
                <flux:badge size="sm" :color="$isVerifiedAccount ? 'green' : ($user ? 'amber' : 'zinc')">
                    {{ $isVerifiedAccount ? 'Verified account' : ($user ? 'Account, not signed in yet' : 'Guest profile') }}
                </flux:badge>
            </div>
            <flux:text class="mt-1">Customer since {{ $bidder->created_at->timezone('Africa/Nairobi')->format('d M Y') }}</flux:text>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button wire:click="openEdit" icon="pencil-square" data-test="edit-customer">Edit</flux:button>
            <flux:modal.trigger name="delete-customer">
                <flux:button variant="danger" icon="trash" data-test="delete-customer">Delete</flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
        <div class="space-y-6">
            <div class="rounded-xl border border-zinc-200 bg-white p-4 sm:p-6 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="grid gap-4 text-sm sm:grid-cols-2">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Mobile</div>
                        <div class="mt-1 font-medium">{{ $bidder->phone }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Email</div>
                        <div class="mt-1 font-medium">{{ $user?->email ?? $bidder->email ?? 'Not provided' }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">WhatsApp</div>
                        <div class="mt-1 font-medium">{{ $bidder->whatsapp_number ?? 'Not provided' }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">ID / KRA verification</div>
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <flux:badge size="sm" :color="match ($verificationStatus) {
                                'verified' => 'green',
                                'requested', 'submitted' => 'amber',
                                'rejected' => 'red',
                                default => 'zinc',
                            }">{{ str_replace('_', ' ', $verificationStatus) }}</flux:badge>
                            @if ($documentsCount > 0)
                                <a href="{{ route('admin.customer-verifications', ['search' => $bidder->phone]) }}" class="text-xs text-navy underline" wire:navigate>
                                    {{ $documentsCount }} {{ Str::plural('document', $documentsCount) }}
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-center justify-between gap-3 border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    <flux:heading>Bids</flux:heading>
                    <flux:text>{{ $bids->total() }} total</flux:text>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                        <thead class="bg-zinc-50 text-left dark:bg-zinc-800">
                            <tr>
                                <th class="px-4 py-3 font-medium">Sale</th>
                                <th class="px-4 py-3 font-medium">Bid amount</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 font-medium">Placed</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse ($bids as $bid)
                                @php($status = $bidStatus($bid))
                                <tr wire:key="customer-bid-{{ $bid->id }}">
                                    <td class="px-4 py-3">
                                        @if ($bid->auction)
                                            <a href="{{ route('admin.auction-bids', $bid->auction) }}" class="font-medium text-navy hover:underline" wire:navigate>{{ $bid->auction->name }}</a>
                                        @else
                                            <span class="text-zinc-500">Sale unavailable</span>
                                        @endif
                                        <div class="font-mono text-xs text-zinc-500">{{ $bid->reference }}</div>
                                    </td>
                                    <td class="px-4 py-3">{{ Money::kes($bid->amount) }}</td>
                                    <td class="px-4 py-3"><flux:badge size="sm" :color="$status['color']">{{ $status['label'] }}</flux:badge></td>
                                    <td class="px-4 py-3 text-xs text-zinc-600 dark:text-zinc-300">{{ $bid->created_at->timezone('Africa/Nairobi')->format('d M Y H:i') }} EAT</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-zinc-500">No bids yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($bids->hasPages())
                    <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-700">{{ $bids->links() }}</div>
                @endif
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-4 sm:p-6 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading>Recent messages</flux:heading>
                @forelse ($messages as $message)
                    <div wire:key="customer-message-{{ $message->id }}" class="mt-3 border-t border-zinc-100 pt-3 text-sm first-of-type:border-0 dark:border-zinc-800">
                        <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-zinc-500">
                            <span>{{ $message->auction?->name ?? 'Sale unavailable' }} · {{ $message->created_at->timezone('Africa/Nairobi')->format('d M Y H:i') }} EAT</span>
                            <flux:badge size="sm" :color="$message->statusColor()">{{ $message->statusLabel() }}</flux:badge>
                        </div>
                        <p class="mt-1 whitespace-pre-line">{{ $message->body }}</p>
                    </div>
                @empty
                    <flux:text class="mt-2">No messages sent. Message bidders from a sale's activity page.</flux:text>
                @endforelse
            </div>
        </div>

        <div class="space-y-4">
            <div class="rounded-xl border border-zinc-200 bg-white p-4 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Summary</div>
                <dl class="mt-3 space-y-2">
                    <div class="flex justify-between gap-3"><dt class="text-zinc-500">Paid bids</dt><dd class="font-medium">{{ $paidBidsCount }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-zinc-500">Fees paid</dt><dd class="font-medium">{{ Money::kes($feesPaid) }}</dd></div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-zinc-500">Self-exclusion</dt>
                        <dd class="text-right font-medium">
                            {{ $control?->self_excluded_until?->isFuture() ? 'Until '.$control->self_excluded_until->timezone('Africa/Nairobi')->format('d M Y') : 'None' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-zinc-500">Fee limit</dt>
                        <dd class="text-right font-medium">
                            {{ $control?->participation_fee_limit !== null ? Money::kes($control->participation_fee_limit).' '.$control->limit_period : 'None' }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>

    <flux:modal name="customer-form" class="w-full max-w-lg">
        <form wire:submit="updateCustomer" class="space-y-5">
            <div>
                <flux:heading size="lg">Edit customer</flux:heading>
                <flux:text class="mt-1">Changing the mobile number means they must confirm the new number with a one-time code next time they sign in.</flux:text>
            </div>

            <flux:error name="customer" />
            <flux:input wire:model="name" label="Full name" required />
            <flux:input wire:model="phone" label="Mobile number" placeholder="07XX or 01XX XXX XXX" required />
            <flux:input wire:model="email" type="email" label="Email (optional)" />
            <flux:input wire:model="whatsapp" label="WhatsApp number (optional)" placeholder="07XX or 01XX XXX XXX" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" class="bg-accent! text-accent-foreground!">Save changes</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="delete-customer" class="w-full max-w-md">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">Delete {{ $bidder->name }}?</flux:heading>
                @if ($deletionBlockers === [])
                    <flux:text class="mt-2">
                        This removes their profile, account, unpaid bids, messages and verification documents. This cannot be undone.
                    </flux:text>
                @else
                    <flux:text class="mt-2">This customer can't be deleted:</flux:text>
                    <ul class="mt-2 list-disc space-y-1 ps-5 text-sm text-red-600 dark:text-red-400" data-test="delete-blockers">
                        @foreach ($deletionBlockers as $blocker)
                            <li>{{ $blocker }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <flux:error name="customer" />
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ $deletionBlockers === [] ? 'Cancel' : 'Close' }}</flux:button>
                </flux:modal.close>
                @if ($deletionBlockers === [])
                    <flux:button variant="danger" wire:click="deleteCustomer">Delete customer</flux:button>
                @endif
            </div>
        </div>
    </flux:modal>
</div>
