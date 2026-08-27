<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\Product;
use App\Support\PlaceholderImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SiteSearchService
{
    public const MIN_LENGTH = 2;

    public const LIMIT = 8;

    /**
     * @return Collection<int, array{
     *     key: string,
     *     name: string,
     *     category: string,
     *     image: string,
     *     in_auction: bool,
     *     in_shop: bool,
     *     auction_slug: string|null,
     *     product_slug: string|null
     * }>
     */
    public function search(string $term): Collection
    {
        $term = trim($term);

        if (Str::length($term) < self::MIN_LENGTH) {
            return collect();
        }

        $auctions = Auction::query()
            ->live()
            ->with('images')
            ->search($term)
            ->orderBy('ends_at')
            ->limit(12)
            ->get();

        $products = Product::query()
            ->search($term)
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->limit(12)
            ->get();

        $extraAuctions = $products->isEmpty()
            ? $auctions->take(0)
            : Auction::query()
                ->live()
                ->with('images')
                ->whereIn('product_id', $products->modelKeys())
                ->when(
                    $auctions->isNotEmpty(),
                    fn (Builder $query): Builder => $query->whereNotIn('id', $auctions->modelKeys()),
                )
                ->orderBy('ends_at')
                ->get();

        $allAuctions = $auctions->concat($extraAuctions);
        $missingProductIds = $allAuctions
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->diff($products->modelKeys())
            ->values();

        $extraProducts = $missingProductIds->isEmpty()
            ? $products->take(0)
            : Product::query()->whereIn('id', $missingProductIds)->get();

        $productsById = $products->concat($extraProducts)->keyBy('id');

        $usedAuctionIds = [];
        $usedProductIds = [];
        $results = collect();

        foreach ($allAuctions as $auction) {
            if ($auction->product_id === null || isset($usedProductIds[$auction->product_id])) {
                continue;
            }

            $product = $productsById->get($auction->product_id);

            if (! $product instanceof Product) {
                continue;
            }

            $results->push($this->item($auction, $product));
            $usedAuctionIds[$auction->id] = true;
            $usedProductIds[$product->id] = true;
        }

        $remainingProducts = $products
            ->reject(fn (Product $product): bool => isset($usedProductIds[$product->id]))
            ->keyBy(fn (Product $product): string => Str::lower($product->name));

        foreach ($allAuctions as $auction) {
            if (isset($usedAuctionIds[$auction->id])) {
                continue;
            }

            $product = $remainingProducts->get(Str::lower($auction->name));

            if ($product instanceof Product && ! isset($usedProductIds[$product->id])) {
                $results->push($this->item($auction, $product));
                $usedAuctionIds[$auction->id] = true;
                $usedProductIds[$product->id] = true;

                continue;
            }

            $results->push($this->item($auction, null));
            $usedAuctionIds[$auction->id] = true;
        }

        foreach ($products as $product) {
            if (isset($usedProductIds[$product->id])) {
                continue;
            }

            $results->push($this->item(null, $product));
        }

        return $results
            ->sortBy([
                fn (array $item): int => ($item['in_auction'] && $item['in_shop']) ? 0 : 1,
                fn (array $item): string => Str::lower($item['name']),
            ])
            ->values()
            ->take(self::LIMIT);
    }

    /**
     * @return array{
     *     key: string,
     *     name: string,
     *     category: string,
     *     image: string,
     *     in_auction: bool,
     *     in_shop: bool,
     *     auction_slug: string|null,
     *     product_slug: string|null
     * }
     */
    private function item(?Auction $auction, ?Product $product): array
    {
        if ($product !== null) {
            $name = $product->name;
            $category = $product->category;
            $image = ($auction !== null ? $auction->primaryImageUrl() : '') ?: $product->image;
            $key = 'product-'.$product->id;
        } else {
            if ($auction === null) {
                throw new \InvalidArgumentException('A search result needs an auction or a product.');
            }

            $name = $auction->name;
            $category = $auction->category;
            $image = $auction->primaryImageUrl();
            $key = 'auction-'.$auction->id;
        }

        if ($image === '') {
            $image = PlaceholderImage::make($name);
        }

        return [
            'key' => $key,
            'name' => $name,
            'category' => $category,
            'image' => $image,
            'in_auction' => $auction !== null,
            'in_shop' => $product !== null,
            'auction_slug' => $auction?->slug,
            'product_slug' => $product?->slug,
        ];
    }
}
