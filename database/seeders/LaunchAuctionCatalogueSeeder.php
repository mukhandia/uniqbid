<?php

namespace Database\Seeders;

use App\Models\Auction;
use App\Models\Category;
use App\Models\Product;
use App\Models\Subcategory;
use App\Services\CreateSaleFromProductService;
use Illuminate\Database\Seeder;

class LaunchAuctionCatalogueSeeder extends Seeder
{
    /**
     * Idempotent launch auction products + live sales for Live Auctions.
     * Safe to run on staging/production: skips products that already have a blocking sale.
     */
    public function run(): void
    {
        $electronics = Category::query()->firstOrCreate(
            ['slug' => 'electronics'],
            ['name' => 'Electronics', 'sort_order' => 10, 'is_active' => true],
        );
        $vehicles = Category::query()->firstOrCreate(
            ['slug' => 'vehicles'],
            ['name' => 'Vehicles', 'sort_order' => 20, 'is_active' => true],
        );
        $home = Category::query()->firstOrCreate(
            ['slug' => 'home'],
            ['name' => 'Home', 'sort_order' => 30, 'is_active' => true],
        );
        $livestock = Category::query()->firstOrCreate(
            ['slug' => 'livestock'],
            ['name' => 'Livestock', 'sort_order' => 40, 'is_active' => true],
        );
        $housing = Category::query()->firstOrCreate(
            ['slug' => 'housing'],
            ['name' => 'Housing', 'sort_order' => 50, 'is_active' => true],
        );

        $subcategories = [
            'phones' => Subcategory::query()->firstOrCreate(
                ['category_id' => $electronics->id, 'slug' => 'phones'],
                ['name' => 'Phones', 'sort_order' => 1, 'is_active' => true],
            ),
            'tv-audio' => Subcategory::query()->firstOrCreate(
                ['category_id' => $electronics->id, 'slug' => 'tv-audio'],
                ['name' => 'TV & Audio', 'sort_order' => 2, 'is_active' => true],
            ),
            'motorbikes' => Subcategory::query()->firstOrCreate(
                ['category_id' => $vehicles->id, 'slug' => 'motorbikes'],
                ['name' => 'Motorbikes', 'sort_order' => 1, 'is_active' => true],
            ),
            'energy' => Subcategory::query()->firstOrCreate(
                ['category_id' => $home->id, 'slug' => 'energy'],
                ['name' => 'Energy', 'sort_order' => 1, 'is_active' => true],
            ),
            'cooking' => Subcategory::query()->firstOrCreate(
                ['category_id' => $home->id, 'slug' => 'cooking'],
                ['name' => 'Cooking', 'sort_order' => 2, 'is_active' => true],
            ),
            'cattle' => Subcategory::query()->firstOrCreate(
                ['category_id' => $livestock->id, 'slug' => 'cattle'],
                ['name' => 'Cattle', 'sort_order' => 1, 'is_active' => true],
            ),
            'rent' => Subcategory::query()->firstOrCreate(
                ['category_id' => $housing->id, 'slug' => 'rent-support'],
                ['name' => 'Rent support', 'sort_order' => 1, 'is_active' => true],
            ),
        ];

        $launchProducts = [
            [
                'slug' => 'auction-motorbike-boxer-150',
                'name' => 'Motorbike',
                'subcategory' => $subcategories['motorbikes'],
                'price' => 195000,
                'bid_fee' => 100,
                'max_bid' => 25000,
                'image' => '/images/auctions/boxer-150.png',
                'description' => 'Brand-new Bajaj Boxer 150cc motorbike with logbook pathway support. Nairobi collection from our yard.',
                'sort' => 10,
                'ends_in_hours' => 30,
                'is_featured' => true,
            ],
            [
                'slug' => 'auction-iphone',
                'name' => 'iPhone',
                'subcategory' => $subcategories['phones'],
                'price' => 145000,
                'bid_fee' => 50,
                'max_bid' => 15000,
                'image' => '/images/auctions/iphone-15-pro.png',
                'description' => 'Sealed iPhone suitable for the Kenya market, with standard Apple warranty options.',
                'sort' => 20,
                'ends_in_hours' => 28,
            ],
            [
                'slug' => 'auction-samsung',
                'name' => 'Samsung',
                'subcategory' => $subcategories['phones'],
                'price' => 72000,
                'bid_fee' => 40,
                'max_bid' => 10000,
                'image' => '/images/auctions/samsung-tv-55.png',
                'description' => 'Current-generation Samsung smartphone, sealed, with local warranty support.',
                'sort' => 30,
                'ends_in_hours' => 26,
            ],
            [
                'slug' => 'auction-tv-woofer',
                'name' => 'TV & Woofer',
                'subcategory' => $subcategories['tv-audio'],
                'price' => 78000,
                'bid_fee' => 30,
                'max_bid' => 9000,
                'image' => '/images/auctions/samsung-tv-55.png',
                'description' => 'Smart TV and woofer entertainment package for living-room setups. Nairobi delivery available.',
                'sort' => 40,
                'ends_in_hours' => 24,
            ],
            [
                'slug' => 'auction-dairy-cow',
                'name' => 'Dairy Cow',
                'subcategory' => $subcategories['cattle'],
                'price' => 120000,
                'bid_fee' => 80,
                'max_bid' => 20000,
                'image' => '/images/auctions/boxer-150.png',
                'description' => 'Healthy dairy cow selected for Kenyan smallholder farming. Collection arrangements coordinated after the successful buy.',
                'sort' => 50,
                'ends_in_hours' => 36,
            ],
            [
                'slug' => 'auction-rent-deposit',
                'name' => 'Rent + 2 month deposit',
                'subcategory' => $subcategories['rent'],
                'price' => 75000,
                'bid_fee' => 50,
                'max_bid' => 12000,
                'image' => '/images/auctions/macbook-air-m3.png',
                'description' => 'Rent support package covering rent plus a two-month deposit equivalent, paid out after a successful buy and verification.',
                'sort' => 60,
                'ends_in_hours' => 32,
            ],
            [
                'slug' => 'auction-solar-kit',
                'name' => 'Solar Kit',
                'subcategory' => $subcategories['energy'],
                'price' => 55000,
                'bid_fee' => 35,
                'max_bid' => 8000,
                'image' => '/images/auctions/ps5-slim.png',
                'description' => 'Home solar kit sized for lighting and small appliance loads. Installation guidance included.',
                'sort' => 70,
                'ends_in_hours' => 22,
            ],
            [
                'slug' => 'auction-gas-kit-hustler',
                'name' => 'Gas Kit (Hustler)',
                'subcategory' => $subcategories['cooking'],
                'price' => 15000,
                'bid_fee' => 20,
                'max_bid' => 4000,
                'image' => '/images/auctions/gas-cooker.png',
                'description' => 'Hustler gas kit for everyday cooking. Compact, practical, and ready for collection or Nairobi delivery.',
                'sort' => 80,
                'ends_in_hours' => 20,
            ],
        ];

        $saleService = app(CreateSaleFromProductService::class);
        $created = 0;
        $skipped = 0;

        foreach ($launchProducts as $row) {
            /** @var Subcategory $subcategory */
            $subcategory = $row['subcategory'];
            $subcategory->loadMissing('category');

            $product = Product::query()->updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'subcategory_id' => $subcategory->id,
                    'name' => $row['name'],
                    'category' => $subcategory->category->name,
                    'subcategory' => $subcategory->name,
                    'gender_age' => 'Unisex',
                    'price_tier' => 'Mid-range',
                    'description' => $row['description'],
                    'price' => $row['price'],
                    'stock' => 5,
                    'reserved_stock' => 0,
                    'image' => $row['image'],
                    'is_featured' => false,
                    'is_returnable' => false,
                    'channel' => Product::CHANNEL_AUCTION,
                    'auction_sort_order' => $row['sort'],
                ],
            );

            if ($product->currentSale !== null) {
                $skipped++;

                continue;
            }

            $auction = $saleService->handle($product->fresh(), [
                'bid_fee' => $row['bid_fee'],
                'min_bid' => 1,
                'max_bid' => $row['max_bid'],
                'ends_in_hours' => $row['ends_in_hours'],
                'reserve_stock' => true,
                'is_featured' => (bool) ($row['is_featured'] ?? false),
            ]);

            $auction->forceFill([
                'display_order' => $row['sort'],
                'duration_hours' => (float) $row['ends_in_hours'],
            ])->save();

            $created++;
        }

        Auction::ensureHomepageHero();

        $this->command->info("Launch auction catalogue: created {$created} sale(s), skipped {$skipped} (already live).");
    }
}
