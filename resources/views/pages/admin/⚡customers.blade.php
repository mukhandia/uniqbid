<?php

use App\Models\Bidder;
use App\Services\CustomerAdminService;
use App\Support\CustomerSearch;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Layout('layouts.app')]
#[Title('Admin: Customers')]
class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $whatsapp = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->reset('name', 'phone', 'email', 'whatsapp');
        $this->resetValidation();

        $this->modal('customer-form')->show();
    }

    public function createCustomer(CustomerAdminService $customers): void
    {
        $bidder = $customers->create([
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'whatsapp' => $this->whatsapp,
        ], auth()->user());

        $this->reset('name', 'phone', 'email', 'whatsapp');
        $this->modal('customer-form')->close();

        Flux::toast(variant: 'success', text: $bidder->name.' added.');

        $this->redirectRoute('admin.customers.show', $bidder, navigate: true);
    }

    public function with(): array
    {
        $term = trim($this->search);

        $customers = Bidder::query()
            ->select(['id', 'user_id', 'name', 'email', 'phone', 'whatsapp_number', 'created_at', 'updated_at'])
            ->with([
                'user:id,name,email,phone,phone_verified_at',
                'user.customerControl:id,user_id,self_excluded_until,verification_status',
                'latestBid',
                'latestBid.auction:id,name',
            ])
            ->withCount('bids')
            ->when($term !== '', function (Builder $query) use ($term): void {
                $like = '%'.$term.'%';

                $query->where(function (Builder $inner) use ($like, $term): void {
                    $inner->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', $like)->orWhere('email', 'like', $like));

                    foreach (CustomerSearch::phonePatterns($term) as $pattern) {
                        $inner->orWhere('phone', 'like', $pattern)->orWhere('whatsapp_number', 'like', $pattern);
                    }
                });
            })
            ->latest('updated_at')
            ->paginate(30);

        return [
            'customers' => $customers,
        ];
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Customers</flux:heading>
            <flux:text class="mt-1">Bidder profiles and customer accounts. Open a customer to view their bids, edit their details, or delete them.</flux:text>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button href="{{ route('admin.customer-verifications') }}" variant="outline" wire:navigate>
                Customer verification
            </flux:button>
            <flux:button wire:click="openCreate" icon="plus" class="bg-accent! text-accent-foreground!" data-test="add-customer">
                Add customer
            </flux:button>
        </div>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="sm:w-80">
            <flux:field>
                <flux:label>Search</flux:label>
                <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" placeholder="Name, phone, email or WhatsApp" data-test="customer-search" />
            </flux:field>
        </div>
        @if ($search !== '')
            <flux:button type="button" variant="ghost" wire:click="clearSearch">Clear search</flux:button>
        @endif
        <flux:text class="sm:ms-auto">
            {{ $customers->total() }} {{ $customers->total() === 1 ? 'customer' : 'customers' }}
        </flux:text>
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
                            <a href="{{ route('admin.customers.show', $customer) }}" class="font-medium text-navy hover:underline" wire:navigate>{{ $customer->name }}</a>
                            <div class="text-xs text-zinc-500">{{ $customer->phone }}{{ $customer->email ? ' · '.$customer->email : '' }}</div>
                            @if ($customer->whatsapp_number)
                                <div class="text-xs text-zinc-500">WhatsApp: {{ $customer->whatsapp_number }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <flux:badge size="sm" :color="$isVerifiedAccount ? 'green' : ($customer->user ? 'amber' : 'zinc')">
                                {{ $isVerifiedAccount ? 'Verified account' : ($customer->user ? 'Account, not signed in yet' : 'Guest profile') }}
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
                            @if ($customer->user)
                                <flux:badge size="sm" :color="match ($control?->verification_status ?? 'not_requested') {
                                    'verified' => 'green',
                                    'requested', 'submitted' => 'amber',
                                    'rejected' => 'red',
                                    default => 'zinc',
                                }">
                                    {{ str_replace('_', ' ', $control?->verification_status ?? 'not_requested') }}
                                </flux:badge>
                            @else
                                <span class="text-xs text-zinc-500">Available once they have an account</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-zinc-500">{{ $search !== '' ? 'No customers match "'.$search.'".' : 'No customer profiles yet.' }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $customers->links() }}
    </div>

    <flux:modal name="customer-form" class="w-full max-w-lg">
        <form wire:submit="createCustomer" class="space-y-5">
            <div>
                <flux:heading size="lg">Add customer</flux:heading>
                <flux:text class="mt-1">Creates their account. They sign in with this mobile number and a one-time code; no password is needed.</flux:text>
            </div>

            <flux:input wire:model="name" label="Full name" required />
            <flux:input wire:model="phone" label="Mobile number" placeholder="07XX or 01XX XXX XXX" required />
            <flux:input wire:model="email" type="email" label="Email (optional)" />
            <flux:input wire:model="whatsapp" label="WhatsApp number (optional)" placeholder="07XX or 01XX XXX XXX" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" class="bg-accent! text-accent-foreground!">Add customer</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
