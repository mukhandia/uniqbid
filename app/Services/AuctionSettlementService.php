<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\AuctionResult;
use App\Models\AuctionRound;
use App\Models\Product;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuctionSettlementService
{
    public function __construct(
        private readonly NotificationPlaceholderService $notifications,
        private readonly CreateSaleFromProductService $createSaleFromProduct,
    ) {}

    /**
     * @return array{auction: Auction, successful_bid: ?AuctionBid, advanced_round: bool, next_sale: ?Auction}
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
                    'advanced_round' => false,
                    'next_sale' => null,
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

            if (! $successfulBid) {
                $this->advanceRound($auction);
                $auction = $auction->fresh(['rounds']);
                $this->logActivity($auction, 'round advanced', 'advanced_round');

                return [
                    'auction' => $auction,
                    'successful_bid' => null,
                    'advanced_round' => true,
                    'next_sale' => null,
                ];
            }

            $this->closeRound($auction, AuctionRound::OUTCOME_WINNER, $successfulBid);

            $durationHours = max(0.25, (float) ($auction->duration_hours ?: 24));

            $auction->forceFill([
                'status' => 'closed',
                'settled_at' => now(),
                'settled_by' => $settledBy?->id,
                'is_featured' => false,
            ])->save();

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

            $this->createSaleFromProduct->consumeStockForSettledSale($auction);

            $this->notifications->successfulBuyer($auction, $successfulBid);
            $auction = $auction->fresh(['result', 'rounds']);
            $this->logActivity($auction, 'sale settled', 'settled');

            $nextSale = $this->startNextSaleAfterSettle($auction, $durationHours);

            return [
                'auction' => $auction,
                'successful_bid' => $successfulBid,
                'advanced_round' => false,
                'next_sale' => $nextSale,
            ];
        });
    }

    public function settleEnded(): int
    {
        $ended = Auction::query()
            ->whereNull('settled_at')
            ->where('ends_at', '<=', now())
            ->whereNotIn('status', ['voided', 'draft'])
            ->get();

        $count = 0;

        foreach ($ended as $auction) {
            $this->settle($auction);
            $count++;
        }

        Auction::ensureHomepageHero();

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

            $this->logActivity($auction->fresh(), 'sale re-offered', 'reoffered');

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

    private function advanceRound(Auction $auction): void
    {
        $this->closeRound($auction, AuctionRound::OUTCOME_NO_UNIQUE);

        $nextRound = $auction->current_round + 1;
        $durationHours = max(0.25, (float) $auction->duration_hours);

        $auction->forceFill([
            'current_round' => $nextRound,
            'ends_at' => now()->addHours($durationHours),
            'status' => 'live',
            'settled_at' => null,
            'settled_by' => null,
        ])->save();

        AuctionRound::query()->create([
            'auction_id' => $auction->id,
            'round' => $nextRound,
            'started_at' => now(),
            'ended_at' => null,
            'outcome' => null,
            'winning_bid_id' => null,
        ]);
    }

    private function closeRound(Auction $auction, string $outcome, ?AuctionBid $winningBid = null): void
    {
        $round = AuctionRound::query()->firstOrCreate(
            [
                'auction_id' => $auction->id,
                'round' => $auction->current_round,
            ],
            [
                'started_at' => $auction->created_at ?? now(),
                'ended_at' => null,
                'outcome' => null,
                'winning_bid_id' => null,
            ],
        );

        $round->forceFill([
            'ended_at' => now(),
            'outcome' => $outcome,
            'winning_bid_id' => $winningBid?->id,
        ])->save();
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

    private function startNextSaleAfterSettle(Auction $settledAuction, float $durationHours): ?Auction
    {
        if ($settledAuction->product_id === null) {
            return null;
        }

        $product = Product::query()->find($settledAuction->product_id);

        if ($product === null) {
            return null;
        }

        $nextSale = $this->createSaleFromProduct->handle($product, [
            'ends_in_hours' => $durationHours,
            'reserve_stock' => true,
            'is_featured' => false,
        ]);

        $this->logActivity($nextSale, 'sale auto-started after settle', 'auto_started', [
            'previous_sale_id' => $settledAuction->id,
        ]);

        return $nextSale;
    }

    private function maskBuyer(AuctionBid $bid): string
    {
        $first = Str::of($bid->bidder->name)->trim()->before(' ')->title()->toString();
        $phoneTail = Str::substr($bid->bidder->phone, -2);

        return trim($first.' '.$phoneTail.'.');
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function logActivity(Auction $auction, string $description, string $event, array $properties = []): void
    {
        AuditLogger::log('auctions', $description, $event, $auction, array_merge([
            'auction_id' => $auction->id,
            'current_round' => $auction->current_round,
            'status' => $auction->status,
        ], $properties));
    }
}
