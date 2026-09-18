<?php

use App\Models\OrderItem;
use App\Models\Product;
use App\Services\GuestListingService;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Layout('layouts.app')]
#[Title('Admin: Guest listings')]
class extends Component {
    use WithPagination;

    #[Url(except: 'pending')]
    public string $status = 'pending';

    public string $rejectionReason = '';

    public function mount(): void
    {
        abort_unless(
            auth()->user()?->is_admin
                || auth()->user()?->can('guest-listings.view')
                || auth()->user()?->can('guest-listings.manage'),
            403,
        );
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function approve(int $productId, GuestListingService $listings): void
    {
        $this->authorizeManage();

        $product = Product::query()->guestListings()->findOrFail($productId);

        try {
            $listings->approve($product);
        } catch (ValidationException $exception) {
            Flux::toast(variant: 'danger', text: collect($exception->errors())->flatten()->first() ?? 'Unable to approve.');

            return;
        }

        Flux::toast(variant: 'success', text: 'Listing approved and published to the shop.');
    }

    public function reject(int $productId, GuestListingService $listings): void
    {
        $this->authorizeManage();

        $product = Product::query()->guestListings()->findOrFail($productId);

        try {
            $listings->reject($product, $this->rejectionReason);
        } catch (ValidationException $exception) {
            Flux::toast(variant: 'danger', text: collect($exception->errors())->flatten()->first() ?? 'Unable to reject.');

            return;
        }

        $this->rejectionReason = '';
        Flux::toast(variant: 'success', text: 'Listing rejected.');
    }

    public function simulateSellerPayout(int $orderItemId): void
    {
        $this->authorizeManage();

        $item = OrderItem::query()
            ->where('seller_payout_status', OrderItem::PAYOUT_PENDING)
            ->findOrFail($orderItemId);

        $item->update([
            'seller_payout_status' => OrderItem::PAYOUT_PAID,
            'seller_payout_paid_at' => now(),
        ]);

        Flux::toast(
            variant: 'success',
            text: 'Simulated M-Pesa payout of '.Money::kes($item->seller_payout_amount).' to seller.',
        );
    }

    public function with(): array
    {
        $listings = Product::query()
            ->guestListings()
            ->with('seller')
            ->when(
                $this->status !== 'all',
                fn ($query) => $query->where('approval_status', $this->status),
            )
            ->latest('id')
            ->paginate(20);

        $pendingPayouts = OrderItem::query()
            ->where('seller_payout_status', OrderItem::PAYOUT_PENDING)
            ->with(['order', 'seller'])
            ->latest('id')
            ->limit(20)
            ->get();

        return [
            'listings' => $listings,
            'pendingPayouts' => $pendingPayouts,
            'commissionPercent' => app(GuestListingService::class)->commissionPercent(),
            'canManage' => auth()->user()?->is_admin || auth()->user()?->can('guest-listings.manage'),
        ];
    }

    private function authorizeManage(): void
    {
        abort_unless(
            auth()->user()?->is_admin || auth()->user()?->can('guest-listings.manage'),
            403,
        );
    }
}; ?>

<div class="space-y-8">
    <div>
        <flux:heading size="xl">Guest shop listings</flux:heading>
        <flux:text class="mt-1">
            Approve guest products before they appear in the shop. Commission is currently {{ $commissionPercent }}%
            (<a href="{{ route('admin.settings') }}" class="font-medium text-navy underline" wire:navigate>Settings</a>).
            After a customer pays, simulate the seller M-Pesa payout below.
        </flux:text>
    </div>

    <div class="flex flex-wrap items-end gap-3">
        <div class="w-52">
            <flux:field>
                <flux:label>Status</flux:label>
                <flux:select wire:model.live="status">
                    <flux:select.option value="pending">Pending</flux:select.option>
                    <flux:select.option value="approved">Approved</flux:select.option>
                    <flux:select.option value="rejected">Rejected</flux:select.option>
                    <flux:select.option value="all">All</flux:select.option>
                </flux:select>
            </flux:field>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
            <thead class="bg-zinc-50 text-left dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 font-semibold">Product</th>
                    <th class="px-4 py-3 font-semibold">Seller</th>
                    <th class="px-4 py-3 font-semibold">Price</th>
                    <th class="px-4 py-3 font-semibold">Status</th>
                    <th class="px-4 py-3 font-semibold"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($listings as $listing)
                    <tr wire:key="guest-listing-{{ $listing->id }}">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <img src="{{ $listing->image }}" alt="" class="size-12 rounded-md object-cover">
                                <div>
                                    <div class="font-medium">{{ $listing->name }}</div>
                                    <div class="text-xs text-zinc-500">{{ $listing->category }} · {{ $listing->subcategory }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div>{{ $listing->seller?->name ?? '—' }}</div>
                            <div class="text-xs text-zinc-500">{{ $listing->seller?->phone }}</div>
                        </td>
                        <td class="px-4 py-3 tabular-nums">{{ Money::kes($listing->price) }}</td>
                        <td class="px-4 py-3">
                            <flux:badge :color="$listing->approval_status === 'approved' ? 'green' : ($listing->approval_status === 'rejected' ? 'red' : 'amber')">
                                {{ $listing->approvalLabel() }}
                            </flux:badge>
                        </td>
                        <td class="px-4 py-3">
                            @if ($canManage)
                                <div class="flex flex-wrap justify-end gap-2">
                                    @if ($listing->approval_status === 'pending' || $listing->approval_status === 'rejected')
                                        <flux:button size="sm" wire:click="approve({{ $listing->id }})" wire:confirm="Approve and publish to the shop?">
                                            Approve
                                        </flux:button>
                                    @endif
                                    @if ($listing->approval_status === 'pending' || $listing->approval_status === 'approved')
                                        <flux:button size="sm" variant="danger" wire:click="reject({{ $listing->id }})" wire:confirm="Reject this listing?">
                                            Reject
                                        </flux:button>
                                    @endif
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-zinc-500">No guest listings for this filter.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $listings->links() }}

    <div class="space-y-3">
        <flux:heading size="lg">Pending seller payouts (M-Pesa mock)</flux:heading>
        <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 text-left dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Order</th>
                        <th class="px-4 py-3 font-semibold">Item</th>
                        <th class="px-4 py-3 font-semibold">Seller</th>
                        <th class="px-4 py-3 font-semibold">Commission</th>
                        <th class="px-4 py-3 font-semibold">Payout</th>
                        <th class="px-4 py-3 font-semibold"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($pendingPayouts as $item)
                        <tr wire:key="payout-{{ $item->id }}">
                            <td class="px-4 py-3 font-mono text-xs">{{ $item->order?->reference }}</td>
                            <td class="px-4 py-3">{{ $item->product_name }}</td>
                            <td class="px-4 py-3">{{ $item->seller?->name ?? '—' }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ Money::kes($item->commission_amount) }}</td>
                            <td class="px-4 py-3 tabular-nums font-semibold">{{ Money::kes($item->seller_payout_amount) }}</td>
                            <td class="px-4 py-3 text-right">
                                @if ($canManage)
                                    <flux:button size="sm" wire:click="simulateSellerPayout({{ $item->id }})" wire:confirm="Simulate M-Pesa payout to the seller?">
                                        Simulate payout
                                    </flux:button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-zinc-500">No pending seller payouts.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
