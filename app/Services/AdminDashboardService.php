<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\AuctionResult;
use App\Models\Order;
use App\Models\Product;
use App\Models\SupportTicket;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class AdminDashboardService
{
    private const DISPLAY_TZ = 'Africa/Nairobi';

    /**
     * @return array{
     *     liveSales: int,
     *     endingSoon: int,
     *     unsettledSales: int,
     *     ordersToPack: int,
     *     unreadTickets: int,
     *     openTickets: int,
     *     shopSalesToday: int,
     *     participationFeesToday: int,
     *     newAccounts7d: int
     * }
     */
    public function snapshot(): array
    {
        $today = $this->startOfToday();
        $weekAgo = $this->startOfToday()->subDays(7);

        return [
            'liveSales' => Auction::query()->live()->count(),
            'endingSoon' => Auction::query()->live()->where('ends_at', '<=', now()->addHours(6))->count(),
            'unsettledSales' => Auction::query()->endedUnsettled()->count(),
            'ordersToPack' => Order::query()->awaitingFulfilment()->count(),
            'unreadTickets' => SupportTicket::query()->unread()->count(),
            'openTickets' => SupportTicket::query()->where('status', '!=', SupportTicket::STATUS_CLOSED)->count(),
            'shopSalesToday' => (int) Order::query()->paidNotCancelled()->where('paid_at', '>=', $today)->sum('total'),
            'participationFeesToday' => $this->participationFeesSince($today),
            'newAccounts7d' => User::query()->where('is_admin', false)->where('created_at', '>=', $weekAgo)->count(),
        ];
    }

    /**
     * @return array<string, Collection<int, mixed>>
     */
    public function attention(): array
    {
        return [
            'unsettledSales' => Auction::query()
                ->endedUnsettled()
                ->orderBy('ends_at')
                ->limit(8)
                ->get(['id', 'slug', 'name', 'ends_at', 'status']),
            'awaitingSalePayment' => AuctionResult::query()
                ->awaitingSalePayment()
                ->whereNotNull('auction_id')
                ->with(['auction:id,slug,name'])
                ->latest('sold_on')
                ->limit(8)
                ->get(),
            'overdueFulfilment' => AuctionResult::query()
                ->overdueFulfilment()
                ->whereNotNull('auction_id')
                ->with(['auction:id,slug,name'])
                ->orderBy('fulfilment_due_at')
                ->limit(8)
                ->get(),
            'ordersToPack' => Order::query()
                ->awaitingFulfilment()
                ->withCount('items')
                ->latest()
                ->limit(8)
                ->get([
                    'id',
                    'reference',
                    'customer_name',
                    'customer_phone',
                    'total',
                    'status',
                    'delivery_method',
                    'created_at',
                ]),
            'unreadTickets' => SupportTicket::query()
                ->unread()
                ->latest()
                ->limit(8)
                ->get(['id', 'name', 'subject', 'status', 'created_at']),
            'verificationsRequested' => User::query()
                ->where('is_admin', false)
                ->whereHas('customerControl', fn ($query) => $query->where('verification_status', 'requested'))
                ->with('customerControl:id,user_id,verification_status,verification_requested_at')
                ->latest()
                ->limit(8)
                ->get(['id', 'name', 'email', 'phone']),
            'outOfStock' => Product::query()
                ->outOfStock()
                ->latest()
                ->limit(8)
                ->get(['id', 'slug', 'name', 'stock', 'reserved_stock']),
            'reservedStock' => Product::query()
                ->reservedForSales()
                ->orderByDesc('reserved_stock')
                ->limit(8)
                ->get(['id', 'slug', 'name', 'stock', 'reserved_stock']),
            'pendingGuestListings' => Product::query()
                ->pendingGuestApproval()
                ->with(['seller:id,name,phone'])
                ->latest()
                ->limit(8)
                ->get(['id', 'slug', 'name', 'price', 'seller_user_id', 'approval_status', 'created_at']),
        ];
    }

    /**
     * @return Collection<int, Auction>
     */
    public function liveSales(): Collection
    {
        return Auction::query()
            ->live()
            ->withCount([
                'bids as accepted_bids_count' => fn ($query) => $query->where('status', 'accepted'),
                'bids as pending_bids_count' => fn ($query) => $query->where('status', 'pending_payment'),
            ])
            ->orderBy('ends_at')
            ->limit(8)
            ->get(['id', 'slug', 'name', 'ends_at', 'is_featured', 'bid_fee']);
    }

    /**
     * @return Collection<int, Order>
     */
    public function recentOrders(): Collection
    {
        return Order::query()
            ->withCount('items')
            ->latest()
            ->limit(8)
            ->get(['id', 'reference', 'customer_name', 'total', 'status', 'created_at']);
    }

    private function startOfToday(): CarbonInterface
    {
        return now()->timezone(self::DISPLAY_TZ)->startOfDay();
    }

    private function participationFeesSince(CarbonInterface $from): int
    {
        $bidsTable = (new AuctionBid)->getTable();
        $auctionsTable = (new Auction)->getTable();

        return (int) AuctionBid::query()
            ->where($bidsTable.'.status', 'accepted')
            ->where($bidsTable.'.paid_at', '>=', $from)
            ->join($auctionsTable, $auctionsTable.'.id', '=', $bidsTable.'.auction_id')
            ->sum($auctionsTable.'.bid_fee');
    }
}
