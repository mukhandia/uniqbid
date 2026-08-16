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
                    $successfulBid = AuctionBid::query()
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

            $successfulBid = $this->lowestUniqueBid($acceptedBids);

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
                        'slug' => $auction->slug.'-result',
                        'name' => $auction->name,
                        'category' => $auction->category,
                        'image' => $auction->primaryImageUrl() ?: '/images/auctions/iphone-15-pro.png',
                        'sale_price' => $successfulBid->amount,
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

            $nextBid = $this->lowestUniqueBid($acceptedBids);

            if (! $nextBid) {
                return null;
            }

            $auction->result->update([
                'sale_price' => $nextBid->amount,
                'buyer_display' => $this->maskBuyer($nextBid),
                'sold_on' => now()->toDateString(),
            ]);

            $this->notifications->successfulBuyer($auction, $nextBid);

            return $nextBid;
        });
    }

    /**
     * @param  Collection<int, AuctionBid>  $acceptedBids
     */
    private function lowestUniqueBid($acceptedBids): ?AuctionBid
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
