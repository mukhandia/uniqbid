<?php

namespace App\Console\Commands;

use App\Models\Auction;
use App\Models\AuctionRound;
use App\Models\Product;
use App\Services\CreateSaleFromProductService;
use App\Services\DestroySaleService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

#[Signature('uniqbid:sync-live-catalogue')]
#[Description('Remove orphan/non-auction-channel live sales so Live Auctions matches Auction products')]
class SyncLiveAuctionCatalogueCommand extends Command
{
    public function handle(
        DestroySaleService $destroySale,
        CreateSaleFromProductService $createSale,
    ): int {
        $removed = 0;
        $voided = 0;

        $candidates = Auction::query()
            ->live()
            ->with('product')
            ->get()
            ->filter(function (Auction $auction): bool {
                if ($auction->product_id === null) {
                    return true;
                }

                $product = $auction->product;

                return ! $product instanceof Product || ! $product->isInAuctionChannel();
            });

        foreach ($candidates as $auction) {
            try {
                $destroySale->handle($auction);
                $removed++;
                $this->line("Deleted sale: {$auction->slug}");
            } catch (ValidationException) {
                AuctionRound::query()->updateOrCreate(
                    [
                        'auction_id' => $auction->id,
                        'round' => $auction->current_round,
                    ],
                    [
                        'started_at' => $auction->created_at ?? now(),
                        'ended_at' => now(),
                        'outcome' => AuctionRound::OUTCOME_VOIDED,
                        'winning_bid_id' => null,
                    ],
                );

                $auction->update([
                    'status' => 'voided',
                    'settled_at' => now(),
                    'is_featured' => false,
                ]);
                $createSale->releaseReservedStock($auction);
                $voided++;
                $this->line("Voided sale (paid bids kept): {$auction->slug}");
            }
        }

        Auction::ensureHomepageHero();

        $liveCount = Auction::query()->liveOnAuctionChannel()->count();
        $productCount = Product::query()->inAuctionChannel()->count();

        $this->info("Removed {$removed}, voided {$voided}. Live catalogue sales: {$liveCount}. Auction-channel products: {$productCount}.");

        return self::SUCCESS;
    }
}
