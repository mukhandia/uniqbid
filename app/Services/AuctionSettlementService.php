<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\AuctionResult;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuctionSettlementService
{
    public function __construct(
        private readonly NotificationPlaceholderService $notifications,
    ) {}

    /**
     * @return array{auction: Auction, successful_bid: ?AuctionBid}
     */
    public function settle(Auction $auction, ?User $settledBy = null): array
    {
        return DB::transaction(function () use ($auction, $settledBy): array {
            $auction = Auction::query()->lockForUpdate()->with(['images', 'result'])->findOrFail($auction->id);

            if ($auction->settled_at !== null) {
                $successfulBid = null;

                if ($auction->result) {
                    $successfulBid = $auction->result->auction_bid_id
                        ? AuctionBid::query()->find($auction->result->auction_bid_id)
                        : AuctionBid::query()
                            ->where('auction_id', $auction->id)
                            ->where('status', 'accepted')
                            ->where('amount', $auction->result->sale_price)
                            ->first();
                }

                return [
                    'auction' => $auction,
                    'successful_bid' => $successfulBid,
                ];
            }

            if ($auction->ends_at->isFuture()) {
                throw ValidationException::withMessages([
                    'auction' => 'This sale has not ended yet.',
                ]);
            }

            $acceptedBids = AuctionBid::query()
                ->with('bidder')
                ->where('auction_id', $auction->id)
                ->where('status', 'accepted')
                ->orderBy('amount')
                ->orderBy('id')
                ->get();

            $successfulBid = $this->lowestUniqueAmong($acceptedBids);

            if (! $successfulBid && $auction->no_unique_bid_rule === 'extend') {
                $auction->forceFill([
                    'ends_at' => now()->addMinutes($auction->extension_minutes),
                    'status' => 'live',
                ])->save();

                return [
                    'auction' => $auction->fresh(),
                    'successful_bid' => null,
                ];
            }

            $auction->forceFill([
                'status' => 'closed',
                'settled_at' => now(),
                'settled_by' => $settledBy?->id,
            ])->save();

            if ($successfulBid) {
                AuctionResult::query()->updateOrCreate(
                    ['auction_id' => $auction->id],
                    [
                        'auction_bid_id' => $successfulBid->id,
                        'slug' => $auction->slug.'-result',
                        'name' => $auction->name,
                        'category' => $auction->category,
                        'image' => $auction->primaryImageUrl() ?: '/images/auctions/iphone-15-pro.png',
                        'sale_price' => $successfulBid->amount,
                        'sale_payment_status' => 'awaiting_payment',
                        'sale_payment_paid_at' => null,
                        'fulfilment_status' => 'pending',
                        'fulfilment_due_at' => now()->addDays(7),
                        'buyer_display' => $this->maskBuyer($successfulBid),
                        'sold_on' => now()->toDateString(),
                    ],
                );

                $this->notifications->successfulBuyer($auction, $successfulBid);
            }

            return [
                'auction' => $auction->fresh(['result']),
                'successful_bid' => $successfulBid,
            ];
        });
    }

    public function settleEnded(): int
    {
        $ended = Auction::query()
            ->whereNull('settled_at')
            ->where('ends_at', '<=', now())
            ->get();

        $count = 0;

        foreach ($ended as $auction) {
            $this->settle($auction);
            $count++;
        }

        return $count;
    }

    public function reofferToNextBidder(Auction $auction): ?AuctionBid
    {
        return DB::transaction(function () use ($auction): ?AuctionBid {
            $auction = Auction::query()->lockForUpdate()->with('result')->findOrFail($auction->id);

            if (! $auction->result) {
                throw ValidationException::withMessages([
                    'auction' => 'This sale has no successful bid to re-offer.',
                ]);
            }

            $acceptedBids = AuctionBid::query()
                ->with('bidder')
                ->where('auction_id', $auction->id)
                ->where('status', 'accepted')
                ->where('amount', '>', $auction->result->sale_price)
                ->orderBy('amount')
                ->orderBy('id')
                ->get();

            $nextBid = $this->lowestUniqueAmong($acceptedBids);

            if (! $nextBid) {
                return null;
            }

            $auction->result->update([
                'auction_bid_id' => $nextBid->id,
                'sale_price' => $nextBid->amount,
                'sale_payment_status' => 'awaiting_payment',
                'sale_payment_paid_at' => null,
                'fulfilment_status' => 'pending',
                'fulfilment_due_at' => now()->addDays(7),
                'buyer_display' => $this->maskBuyer($nextBid),
                'sold_on' => now()->toDateString(),
            ]);

            $this->notifications->successfulBuyer($auction, $nextBid);

            return $nextBid;
        });
    }

    /**
     * Live view of accepted unique bids for admin monitoring. Ties never lead.
     *
     * @return array{
     *     accepted_count: int,
     *     unique_count: int,
     *     amount_counts: array<int, int>,
     *     leading_bid: ?AuctionBid
     * }
     */
    public function uniquenessSnapshot(Auction $auction): array
    {
        $acceptedBids = AuctionBid::query()
            ->with('bidder')
            ->where('auction_id', $auction->id)
            ->where('status', 'accepted')
            ->orderBy('amount')
            ->orderBy('id')
            ->get();

        $amountCounts = [];

        foreach ($acceptedBids->countBy(fn (AuctionBid $bid): int => $bid->amount) as $amount => $count) {
            $amountCounts[(int) $amount] = (int) $count;
        }

        return [
            'accepted_count' => $acceptedBids->count(),
            'unique_count' => collect($amountCounts)
                ->filter(fn (int $count): bool => $count === 1)
                ->count(),
            'amount_counts' => $amountCounts,
            'leading_bid' => $this->lowestUniqueAmong($acceptedBids),
        ];
    }

    /**
     * @param  Collection<int, AuctionBid>  $acceptedBids
     */
    private function lowestUniqueAmong(Collection $acceptedBids): ?AuctionBid
    {
        $counts = $acceptedBids->countBy(fn (AuctionBid $bid): int => $bid->amount);

        $uniqueAmounts = $counts
            ->filter(fn (int $count): bool => $count === 1)
            ->keys()
            ->sort()
            ->values();

        if ($uniqueAmounts->isEmpty()) {
            return null;
        }

        $lowestUnique = (int) $uniqueAmounts->first();

        return $acceptedBids->firstWhere('amount', $lowestUnique);
    }

    private function maskBuyer(AuctionBid $bid): string
    {
        $first = Str::of($bid->bidder->name)->trim()->before(' ')->title()->toString();
        $phoneTail = Str::substr($bid->bidder->phone, -2);

        return trim($first.' '.$phoneTail.'.');
    }
}
