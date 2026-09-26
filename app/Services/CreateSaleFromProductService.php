<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\Product;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateSaleFromProductService
{
    /**
     * Default sale pricing from product retail value:
     * bid fee 0.1%, min bid 1, max bid 10%.
     *
     * @return array{bid_fee: int, min_bid: int, max_bid: int}
     */
    public static function pricingFromProduct(Product $product): array
    {
        $price = max(0, (int) $product->price);

        return [
            'bid_fee' => max(1, (int) round($price * 0.001)),
            'min_bid' => 1,
            'max_bid' => max(1, (int) round($price * 0.10)),
        ];
    }

    /**
     * @param  array{
     *     bid_fee?: int,
     *     min_bid?: int,
     *     max_bid?: int,
     *     bid_increment?: int,
     *     ends_in_hours?: int|float,
     *     is_featured?: bool,
     *     reserve_stock?: bool
     * }  $options
     */
    public function handle(Product $product, array $options): Auction
    {
        return DB::transaction(function () use ($product, $options): Auction {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);

            $blockingSale = Auction::query()
                ->where('product_id', $product->id)
                ->blockingNewSale()
                ->latest('id')
                ->first();

            if ($blockingSale !== null) {
                throw ValidationException::withMessages([
                    'product' => $blockingSale->isOpenLive()
                        ? 'This product already has a live sale. Open that sale instead of creating another.'
                        : 'Settle the ended sale for this product before creating a new one.',
                ]);
            }

            $reserve = $options['reserve_stock'] ?? true;
            $pricing = self::pricingFromProduct($product);

            $maxBid = (int) ($options['max_bid'] ?? $pricing['max_bid']);
            $minBid = (int) ($options['min_bid'] ?? $pricing['min_bid']);
            $bidFee = (int) ($options['bid_fee'] ?? $pricing['bid_fee']);
            $hours = (float) ($options['ends_in_hours'] ?? 24);

            $slugBase = Str::slug($product->name).'-sale';
            $slug = $slugBase;
            $suffix = 1;

            while (Auction::query()->where('slug', $slug)->exists()) {
                $slug = $slugBase.'-'.$suffix;
                $suffix++;
            }

            $displayOrder = (int) (Auction::query()->max('display_order') ?? 0) + 10;

            $auction = Auction::query()->create([
                'product_id' => $product->id,
                'slug' => $slug,
                'name' => $product->name,
                'category' => $product->category,
                'description' => $product->description,
                'bid_fee' => $bidFee,
                'min_bid' => $minBid,
                'max_bid' => max($minBid, $maxBid),
                'bid_increment' => (int) ($options['bid_increment'] ?? 1),
                'retail_value' => $product->price,
                'bids_count' => 0,
                'ends_at' => now()->addHours($hours),
                'status' => 'live',
                'is_featured' => false,
                'no_unique_bid_rule' => 'extend',
                'extension_minutes' => 60,
                'current_round' => 1,
                'duration_hours' => $hours,
                'display_order' => $displayOrder,
            ]);

            $auction->rounds()->create([
                'round' => 1,
                'started_at' => now(),
                'ended_at' => null,
                'outcome' => null,
                'winning_bid_id' => null,
            ]);

            $product->assignToAuctionChannel();

            if ((bool) ($options['is_featured'] ?? false)) {
                $auction->makeHomepageHero();
            }

            if (filled($product->image)) {
                $auction->images()->create([
                    'url' => $product->image,
                    'label' => $product->name,
                    'sort_order' => 0,
                ]);
            }

            if ($reserve) {
                $product->increment('reserved_stock');
            }

            $auction = $auction->load('images', 'product');

            AuditLogger::log('auctions', 'sale created', 'created', $auction, [
                'product_id' => $product->id,
                'bid_fee' => $auction->bid_fee,
                'ends_at' => $auction->ends_at->toIso8601String(),
            ]);

            return $auction;
        });
    }

    public function releaseReservedStock(Auction $auction): void
    {
        if ($auction->product_id === null) {
            return;
        }

        DB::transaction(function () use ($auction): void {
            $product = Product::query()->lockForUpdate()->find($auction->product_id);

            if ($product === null || $product->reserved_stock < 1) {
                return;
            }

            $product->decrement('reserved_stock');
        });
    }

    /**
     * Convert a live-sale reservation into a sold unit when a successful buy settles.
     * Stock may go negative when inventory was already at zero (outsourced fulfilment).
     */
    public function consumeStockForSettledSale(Auction $auction): void
    {
        if ($auction->product_id === null) {
            return;
        }

        DB::transaction(function () use ($auction): void {
            $product = Product::query()->lockForUpdate()->find($auction->product_id);

            if ($product === null) {
                return;
            }

            if ($product->reserved_stock >= 1) {
                $product->decrement('reserved_stock');
            }

            $product->decrement('stock');
        });
    }
}
