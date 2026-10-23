<?php

namespace App\Services;

use App\Models\AuctionResult;
use App\Models\Product;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AdminAttentionService
{
    public function canViewTickets(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->is_admin
            || $user->can('tickets.view')
            || $user->can('tickets.manage');
    }

    public function canViewGuestListings(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->is_admin
            || $user->can('guest-listings.view')
            || $user->can('guest-listings.manage');
    }

    public function canViewAuctions(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->is_admin
            || $user->can('auctions.view')
            || $user->can('auctions.manage');
    }

    /**
     * @return Builder<AuctionResult>
     */
    public function openRedeemReviews(): Builder
    {
        return AuctionResult::query()
            ->whereNotNull('redemption_requested_at')
            ->whereNull('redemption_review_read_at')
            ->where('fulfilment_status', AuctionResult::FULFILMENT_REDEEMED)
            ->where(function ($query): void {
                $query->whereNull('redemption_payout_status')
                    ->orWhere('redemption_payout_status', '!=', AuctionResult::REDEMPTION_PAYOUT_PAID);
            });
    }

    public function unreadTicketCount(?User $user): int
    {
        if (! $this->canViewTickets($user)) {
            return 0;
        }

        return SupportTicket::query()->unread()->count();
    }

    public function unseenGuestListingCount(?User $user): int
    {
        if (! $this->canViewGuestListings($user)) {
            return 0;
        }

        return Product::query()->unseenPendingGuestApproval()->count();
    }

    public function openRedeemReviewCount(?User $user): int
    {
        if (! $this->canViewAuctions($user)) {
            return 0;
        }

        return $this->openRedeemReviews()->count();
    }

    public function totalCount(?User $user): int
    {
        return $this->unreadTicketCount($user)
            + $this->unseenGuestListingCount($user)
            + $this->openRedeemReviewCount($user);
    }

    public function ariaLabel(?User $user): string
    {
        $unreadTickets = $this->unreadTicketCount($user);
        $unseenGuestListings = $this->unseenGuestListingCount($user);
        $redeemReviews = $this->openRedeemReviewCount($user);
        $parts = [];

        if ($unreadTickets > 0) {
            $parts[] = $unreadTickets === 1
                ? '1 unread support ticket'
                : $unreadTickets.' unread support tickets';
        }

        if ($unseenGuestListings > 0) {
            $parts[] = $unseenGuestListings === 1
                ? '1 unseen guest listing'
                : $unseenGuestListings.' unseen guest listings';
        }

        if ($redeemReviews > 0) {
            $parts[] = $redeemReviews === 1
                ? '1 redeem request'
                : $redeemReviews.' redeem requests';
        }

        return $parts === []
            ? 'No items needing attention'
            : implode(', ', $parts);
    }

    /**
     * @return Collection<int, array{
     *     type: string,
     *     id: int,
     *     title: string,
     *     subtitle: string,
     *     created_at: string
     * }>
     */
    public function items(?User $user, int $limit = 8): Collection
    {
        /** @var Collection<int, array{type: string, id: int, title: string, subtitle: string, created_at: string, sort_at: Carbon|null}> $items */
        $items = collect();

        if ($this->canViewTickets($user)) {
            SupportTicket::query()
                ->unread()
                ->latest()
                ->limit($limit)
                ->get(['id', 'name', 'subject', 'created_at'])
                ->each(function (SupportTicket $ticket) use ($items): void {
                    $items->push([
                        'type' => 'ticket',
                        'id' => $ticket->id,
                        'title' => $ticket->subject,
                        'subtitle' => $ticket->name.' · '.$ticket->reference(),
                        'created_at' => $ticket->created_at?->diffForHumans() ?? '',
                        'sort_at' => $ticket->created_at,
                    ]);
                });
        }

        if ($this->canViewGuestListings($user)) {
            Product::query()
                ->unseenPendingGuestApproval()
                ->with(['seller:id,name'])
                ->latest()
                ->limit($limit)
                ->get(['id', 'name', 'price', 'seller_user_id', 'created_at'])
                ->each(function (Product $listing) use ($items): void {
                    $items->push([
                        'type' => 'guest_listing',
                        'id' => $listing->id,
                        'title' => $listing->name,
                        'subtitle' => ($listing->seller->name ?? 'Guest seller').' · '.Money::kes($listing->price),
                        'created_at' => $listing->created_at?->diffForHumans() ?? '',
                        'sort_at' => $listing->created_at,
                    ]);
                });
        }

        if ($this->canViewAuctions($user)) {
            $this->openRedeemReviews()
                ->with(['auction:id,name', 'auctionBid.bidder:id,name'])
                ->latest('redemption_requested_at')
                ->limit($limit)
                ->get()
                ->each(function (AuctionResult $result) use ($items): void {
                    $items->push([
                        'type' => 'redeem_review',
                        'id' => (int) $result->auction_id,
                        'title' => $result->auction->name,
                        'subtitle' => $result->auctionBid->bidder->name.' · '.Money::kes((int) $result->redemption_amount),
                        'created_at' => $result->redemption_requested_at?->diffForHumans() ?? '',
                        'sort_at' => $result->redemption_requested_at,
                    ]);
                });
        }

        return $items
            ->sortByDesc(fn (array $item): int => $item['sort_at']?->getTimestamp() ?? 0)
            ->take($limit)
            ->map(fn (array $item): array => [
                'type' => $item['type'],
                'id' => $item['id'],
                'title' => $item['title'],
                'subtitle' => $item['subtitle'],
                'created_at' => $item['created_at'],
            ])
            ->values();
    }
}
