<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DestroySaleService
{
    public function __construct(
        private readonly CreateSaleFromProductService $createSaleFromProduct,
    ) {}

    public function handle(Auction $auction): void
    {
        DB::transaction(function () use ($auction): void {
            $auction = Auction::query()->lockForUpdate()->findOrFail($auction->id);

            $bids = AuctionBid::query()
                ->where('auction_id', $auction->id)
                ->lockForUpdate()
                ->get();

            $hasPaidParticipation = $bids->contains(
                fn (AuctionBid $bid): bool => $bid->status === 'accepted' || $bid->paid_at !== null,
            );

            if ($hasPaidParticipation) {
                throw ValidationException::withMessages([
                    'auction' => 'This sale has paid participation fees. Void it instead so bid and payment history remain for refunds.',
                ]);
            }

            $auction->result()->delete();

            if ($auction->status !== 'voided') {
                $this->createSaleFromProduct->releaseReservedStock($auction);
            }

            AuditLogger::log('auctions', 'sale deleted', 'deleted', $auction, [
                'auction_id' => $auction->id,
                'slug' => $auction->slug,
                'product_id' => $auction->product_id,
            ]);

            $auction->delete();
        });
    }
}
