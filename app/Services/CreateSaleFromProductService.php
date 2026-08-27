<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateSaleFromProductService
{
    /**
     * @param  array{
     *     bid_fee: int,
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
            $reserve = $options['reserve_stock'] ?? true;

            if ($reserve && $product->availableStock() < 1) {
                throw ValidationException::withMessages([
                    'product' => 'This product has no available stock to reserve for a sale.',
                ]);
            }

            $maxBid = (int) ($options['max_bid'] ?? max(100, (int) round($product->price * 0.5)));
            $minBid = (int) ($options['min_bid'] ?? 1);
            $hours = (float) ($options['ends_in_hours'] ?? 24);

            $slugBase = Str::slug($product->name).'-sale';
            $slug = $slugBase;
            $suffix = 1;

            while (Auction::query()->where('slug', $slug)->exists()) {
                $slug = $slugBase.'-'.$suffix;
                $suffix++;
            }

            $auction = Auction::query()->create([
                'product_id' => $product->id,
                'slug' => $slug,
                'name' => $product->name,
                'category' => $product->category,
                'description' => $product->description,
                'bid_fee' => (int) $options['bid_fee'],
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
            ]);

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

            return $auction->load('images', 'product');
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
}
