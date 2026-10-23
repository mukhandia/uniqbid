<?php

use App\Models\Auction;
use App\Models\AuctionResult;
use App\Models\Product;
use App\Models\SupportTicket;
use App\Services\AdminAttentionService;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public string $placement = 'up';

    public function openTicket(int $ticketId): void
    {
        $user = auth()->user();
        abort_unless(app(AdminAttentionService::class)->canViewTickets($user), 403);

        $ticket = SupportTicket::query()->findOrFail($ticketId);
        $ticket->markRead();
        $this->dispatch('attention-read');

        $this->redirect(route('admin.tickets.show', $ticket), navigate: true);
    }

    public function openGuestListing(int $productId): void
    {
        $user = auth()->user();
        abort_unless(app(AdminAttentionService::class)->canViewGuestListings($user), 403);

        $listing = Product::query()->pendingGuestApproval()->findOrFail($productId);
        $listing->markAdminSeen();
        $this->dispatch('attention-read');

        $this->redirect(route('admin.guest-listings', ['status' => 'pending']), navigate: true);
    }

    public function openRedeemReview(int $auctionId): void
    {
        $user = auth()->user();
        abort_unless(app(AdminAttentionService::class)->canViewAuctions($user), 403);

        $result = AuctionResult::query()->where('auction_id', $auctionId)->firstOrFail();

        if ($result->redemption_review_read_at === null) {
            $result->update(['redemption_review_read_at' => now()]);
        }

        $this->dispatch('attention-read');

        $auction = Auction::query()->findOrFail($auctionId);

        $this->redirect(route('admin.auction-bids', $auction), navigate: true);
    }

    #[On('attention-read')]
    public function refreshAttention(): void
    {
        //
    }

    public function with(): array
    {
        $attention = app(AdminAttentionService::class);
        $user = auth()->user();

        return [
            'totalCount' => $attention->totalCount($user),
            'ariaLabel' => $attention->ariaLabel($user),
            'items' => $attention->items($user),
            'canViewTickets' => $attention->canViewTickets($user),
            'canViewGuestListings' => $attention->canViewGuestListings($user),
        ];
    }
}; ?>

<div
    class="relative inline-flex"
    x-data="{
        open: false,
        placement: @js($placement),
        panelStyle: '',
        toggle() {
            this.open = ! this.open;
            if (this.open) this.position();
        },
        position() {
            const gap = 8;
            const edge = 8;
            const rect = this.$refs.trigger.getBoundingClientRect();
            const vw = window.innerWidth;
            const vh = window.innerHeight;
            const width = Math.min(352, vw - edge * 2);
            const left = vw < 640
                ? edge
                : Math.min(Math.max(this.placement === 'up' ? rect.left : rect.right - width, edge), vw - width - edge);
            const vertical = this.placement === 'up'
                ? `bottom: ${vh - rect.top + gap}px; max-height: ${rect.top - gap - edge}px;`
                : `top: ${rect.bottom + gap}px; max-height: ${vh - rect.bottom - gap - edge}px;`;
            this.panelStyle = `left: ${left}px; width: ${vw < 640 ? vw - edge * 2 : width}px; ${vertical}`;
        },
    }"
    @keydown.escape.window="open = false"
    @resize.window="open && position()"
    wire:poll.3s.visible
>
    <button
        type="button"
        x-ref="trigger"
        class="relative inline-flex size-10 items-center justify-center rounded-lg text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800"
        aria-label="{{ $ariaLabel }}"
        aria-expanded="false"
        x-bind:aria-expanded="open.toString()"
        data-test="admin-attention-bell"
        @click="toggle()"
    >
        <flux:icon.bell class="size-5" />
        @if ($totalCount > 0)
            <span class="absolute right-0.5 top-0.5 grid min-w-4 place-items-center rounded-full bg-red-600 px-1 text-[10px] font-semibold leading-4 text-white">
                {{ $totalCount > 9 ? '9+' : $totalCount }}
            </span>
        @endif
    </button>

    <div
        x-cloak
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        @click.outside="open = false"
        x-bind:style="panelStyle"
        @class([
            'fixed z-50 flex flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-900',
            'origin-bottom-left' => $placement === 'up',
            'origin-top-right' => $placement === 'down',
        ])
        data-test="admin-attention-panel"
    >
        <div class="shrink-0 border-b border-zinc-200 px-3 py-2 dark:border-zinc-700">
            <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Needs attention</div>
            <div class="text-xs text-zinc-500">Read opens the item and clears it from this bell.</div>
        </div>

        <ul class="min-h-0 max-h-80 flex-1 divide-y divide-zinc-100 overflow-y-auto dark:divide-zinc-800">
            @forelse ($items as $item)
                <li wire:key="attention-{{ $item['type'] }}-{{ $item['id'] }}" class="flex items-start justify-between gap-3 px-3 py-2.5">
                    <div class="min-w-0">
                        <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">
                            {{ match ($item['type']) {
                                'ticket' => 'Support ticket',
                                'guest_listing' => 'Guest listing',
                                default => 'Redeem requested',
                            } }}
                        </div>
                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $item['title'] }}</div>
                        <div class="text-xs text-zinc-500">{{ $item['subtitle'] }}</div>
                        @if ($item['created_at'] !== '')
                            <div class="text-[11px] text-zinc-400">{{ $item['created_at'] }}</div>
                        @endif
                    </div>
                    <button
                        type="button"
                        class="shrink-0 text-xs font-semibold text-navy hover:underline dark:text-sky-300"
                        wire:click="{{ match ($item['type']) {
                            'ticket' => 'openTicket('.$item['id'].')',
                            'guest_listing' => 'openGuestListing('.$item['id'].')',
                            default => 'openRedeemReview('.$item['id'].')',
                        } }}"
                        @click="open = false"
                    >
                        Read
                    </button>
                </li>
            @empty
                <li class="px-3 py-6 text-center text-sm text-zinc-500">All caught up. Nothing new.</li>
            @endforelse
        </ul>

        @if ($canViewTickets || $canViewGuestListings)
            <div class="flex shrink-0 flex-wrap gap-2 border-t border-zinc-200 px-3 py-2 dark:border-zinc-700">
                @if ($canViewTickets)
                    <a href="{{ route('admin.tickets') }}" wire:navigate class="text-xs font-medium text-navy hover:underline dark:text-sky-300" @click="open = false">
                        All tickets
                    </a>
                @endif
                @if ($canViewGuestListings)
                    <a href="{{ route('admin.guest-listings') }}" wire:navigate class="text-xs font-medium text-navy hover:underline dark:text-sky-300" @click="open = false">
                        Guest listings
                    </a>
                @endif
            </div>
        @endif
    </div>
</div>
