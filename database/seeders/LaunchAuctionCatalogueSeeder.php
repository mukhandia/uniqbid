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
        $education = Category::query()->firstOrCreate(
            ['slug' => 'education'],
            ['name' => 'Education', 'sort_order' => 60, 'is_active' => true],
        );
        $lifestyle = Category::query()->firstOrCreate(
            ['slug' => 'lifestyle-vouchers'],
            ['name' => 'Lifestyle & Vouchers', 'sort_order' => 70, 'is_active' => true],
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
            'water' => Subcategory::query()->firstOrCreate(
                ['category_id' => $home->id, 'slug' => 'water-storage'],
                ['name' => 'Water storage', 'sort_order' => 3, 'is_active' => true],
            ),
            'cattle' => Subcategory::query()->firstOrCreate(
                ['category_id' => $livestock->id, 'slug' => 'cattle'],
                ['name' => 'Cattle', 'sort_order' => 1, 'is_active' => true],
            ),
            'rent' => Subcategory::query()->firstOrCreate(
                ['category_id' => $housing->id, 'slug' => 'rent-support'],
                ['name' => 'Rent support', 'sort_order' => 1, 'is_active' => true],
            ),
            'fees' => Subcategory::query()->firstOrCreate(
                ['category_id' => $education->id, 'slug' => 'school-fees'],
                ['name' => 'School fees', 'sort_order' => 1, 'is_active' => true],
            ),
            'mystery' => Subcategory::query()->firstOrCreate(
                ['category_id' => $lifestyle->id, 'slug' => 'mystery-boxes'],
                ['name' => 'Mystery boxes', 'sort_order' => 1, 'is_active' => true],
            ),
            'dollar-shop' => Subcategory::query()->firstOrCreate(
                ['category_id' => $lifestyle->id, 'slug' => 'dollar-shop'],
                ['name' => '1 Dollar Shop', 'sort_order' => 2, 'is_active' => true],
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
                'slug' => 'auction-iphone-16',
                'name' => 'iPhone 16',
                'subcategory' => $subcategories['phones'],
                'price' => 145000,
                'bid_fee' => 50,
                'max_bid' => 15000,
                'image' => '/images/auctions/iphone-16.png',
                'description' => 'Sealed iPhone 16 suitable for the Kenya market, with standard Apple warranty options.',
                'sort' => 20,
                'ends_in_hours' => 28,
            ],
            [
                'slug' => 'auction-iphone-17',
                'name' => 'iPhone 17',
                'subcategory' => $subcategories['phones'],
                'price' => 175000,
                'bid_fee' => 60,
                'max_bid' => 18000,
                'image' => '/images/auctions/iphone-17.png',
                'description' => 'Sealed iPhone 17 suitable for the Kenya market, with standard Apple warranty options.',
                'sort' => 22,
                'ends_in_hours' => 29,
            ],
            [
                'slug' => 'auction-samsung-s24',
                'name' => 'Samsung S24',
                'subcategory' => $subcategories['phones'],
                'price' => 95000,
                'bid_fee' => 45,
                'max_bid' => 12000,
                'image' => '/images/auctions/samsung-s24.png',
                'description' => 'Samsung Galaxy S24, sealed, with local warranty support.',
                'sort' => 30,
                'ends_in_hours' => 26,
            ],
            [
                'slug' => 'auction-samsung-s25',
                'name' => 'Samsung S25',
                'subcategory' => $subcategories['phones'],
                'price' => 115000,
                'bid_fee' => 50,
                'max_bid' => 14000,
                'image' => '/images/auctions/samsung-s25.png',
                'description' => 'Samsung Galaxy S25, sealed, with local warranty support.',
                'sort' => 32,
                'ends_in_hours' => 27,
            ],
            [
                'slug' => 'auction-tv-woofer',
                'name' => 'TV & Woofer',
                'subcategory' => $subcategories['tv-audio'],
                'price' => 78000,
                'bid_fee' => 30,
                'max_bid' => 9000,
                'image' => '/images/auctions/tv-woofer.png',
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
                'image' => '/images/auctions/dairy-cow.png',
                'description' => 'Healthy dairy cow selected for Kenyan smallholder farming. Collection arrangements coordinated after the successful buy.',
                'sort' => 50,
                'ends_in_hours' => 36,
            ],
            [
                'slug' => 'auction-rent-deposit',
                'name' => '2 Month Rent + Deposit',
                'subcategory' => $subcategories['rent'],
                'price' => 75000,
                'bid_fee' => 50,
                'max_bid' => 12000,
                'image' => '/images/auctions/rent-keys.png',
                'description' => 'Housing support covering two months of rent plus deposit equivalent, paid out after a successful buy and verification.',
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
                'image' => '/images/auctions/solar-kit.png',
                'description' => 'Home solar kit with panel, battery, and inverter for lighting and small appliance loads. Installation guidance included.',
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
            [
                'slug' => 'auction-school-fee',
                'name' => 'School Fee',
                'subcategory' => $subcategories['fees'],
                'price' => 50000,
                'bid_fee' => 40,
                'max_bid' => 10000,
                'image' => '/images/auctions/school-fee.png',
                'description' => 'School fee support package paid out to a verified school account after a successful buy.',
                'sort' => 90,
                'ends_in_hours' => 34,
            ],
            [
                'slug' => 'auction-water-tank',
                'name' => 'Water Tank',
                'subcategory' => $subcategories['water'],
                'price' => 35000,
                'bid_fee' => 25,
                'max_bid' => 7000,
                'image' => '/images/auctions/water-tank.png',
                'description' => 'Domestic water storage tank ready for installation. Nairobi delivery available.',
                'sort' => 100,
                'ends_in_hours' => 25,
            ],
            [
                'slug' => 'auction-magic-box',
                'name' => 'Magic Box',
                'subcategory' => $subcategories['mystery'],
                'price' => 25000,
                'bid_fee' => 30,
                'max_bid' => 6000,
                'image' => '/images/auctions/magic-box.png',
                'description' => 'Curated mystery box with everyday essentials and surprise lifestyle items. Contents revealed after a successful buy.',
                'sort' => 110,
                'ends_in_hours' => 21,
            ],
            [
                'slug' => 'auction-1-dollar-shop',
                'name' => '1 Dollar Shop',
                'subcategory' => $subcategories['dollar-shop'],
                'price' => 15000,
                'bid_fee' => 20,
                'max_bid' => 3000,
                'image' => '/images/auctions/dollar-shop.png',
                'description' => '1 Dollar Shop voucher bundle for everyday household picks. Redeemed after a successful buy.',
                'sort' => 120,
                'ends_in_hours' => 19,
            ],
        ];

        $saleService = app(CreateSaleFromProductService::class);
        $created = 0;
        $skipped = 0;

        foreach ($launchProducts as $row) {
            /** @var Subcategory $subcategory */
            $subcategory = $row['subcategory'];
            $subcategory->loadMissing('category');

            $existing = Product::query()->where('slug', $row['slug'])->first();

            $stock = 5;
            $reservedStock = 0;

            if ($existing !== null) {
                $stock = max(5, (int) $existing->stock);
                $reservedStock = (int) $existing->reserved_stock;
            }

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
                    'stock' => $stock,
                    'reserved_stock' => $reservedStock,
                    'image' => $row['image'],
                    'is_featured' => false,
                    'is_returnable' => false,
                    'channel' => Product::CHANNEL_AUCTION,
                    'auction_sort_order' => $row['sort'],
                ],
            );

            $currentSale = $product->fresh()->currentSale;

            if ($currentSale !== null) {
                $currentSale->forceFill([
                    'name' => $product->name,
                    'category' => $product->category,
                    'retail_value' => $product->price,
                    'description' => $product->description,
                    'bid_fee' => $row['bid_fee'],
                    'min_bid' => 1,
                    'max_bid' => $row['max_bid'],
                    'display_order' => $row['sort'],
                ])->save();

                $currentSale->images()->updateOrCreate(
                    ['sort_order' => 0],
                    [
                        'url' => $row['image'],
                        'label' => $product->name,
                    ],
                );
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

        $this->retireDeprecatedLaunchProducts();
        $this->renameLegacyLaunchTitles();

        $this->command->info("Launch auction catalogue: created {$created} sale(s), refreshed images on {$skipped} live sale(s).");
    }

    private function renameLegacyLaunchTitles(): void
    {
        Product::query()
            ->where(function ($query): void {
                $query->where('slug', 'auction-rent-deposit')
                    ->orWhere('name', 'Rent + 2 month deposit');
            })
            ->update([
                'name' => '2 Month Rent + Deposit',
                'image' => '/images/auctions/rent-keys.png',
            ]);

        Auction::query()
            ->where(function ($query): void {
                $query->where('name', 'Rent + 2 month deposit')
                    ->orWhere('name', '2 Month Rent + Deposit');
            })
            ->where('status', 'live')
            ->whereNull('settled_at')
            ->each(function (Auction $sale): void {
                $sale->forceFill(['name' => '2 Month Rent + Deposit'])->save();

                $sale->images()->updateOrCreate(
                    ['sort_order' => 0],
                    [
                        'url' => '/images/auctions/rent-keys.png',
                        'label' => '2 Month Rent + Deposit',
                    ],
                );
            });
    }

    private function retireDeprecatedLaunchProducts(): void
    {
        $deprecated = ['auction-iphone', 'auction-samsung', 'rent-2-month-deposit'];

        foreach ($deprecated as $slug) {
            $product = Product::query()->where('slug', $slug)->first();

            if ($product === null) {
                continue;
            }

            $product->auctions()
                ->whereNull('settled_at')
                ->where('status', 'live')
                ->whereDoesntHave('bids', fn ($query) => $query->where('status', 'accepted'))
                ->each(function (Auction $sale): void {
                    $sale->forceFill([
                        'status' => 'voided',
                        'settled_at' => $sale->settled_at ?? now(),
                    ])->save();
                });

            $product->forceFill([
                'channel' => Product::CHANNEL_NONE,
                'is_featured' => false,
                'name' => str_starts_with($product->name, '[Retired]')
                    ? $product->name
                    : '[Retired] '.$product->name,
            ])->save();
        }

        // Also retire any leftover live sales still titled with the generic single-SKU names.
        Auction::query()
            ->whereNull('settled_at')
            ->where('status', 'live')
            ->whereIn('name', ['iPhone', 'Samsung'])
            ->whereDoesntHave('bids', fn ($query) => $query->where('status', 'accepted'))
            ->each(function (Auction $sale): void {
                $sale->forceFill([
                    'status' => 'voided',
                    'settled_at' => now(),
                ])->save();

                if ($sale->product_id) {
                    Product::query()->whereKey($sale->product_id)->update([
                        'channel' => Product::CHANNEL_NONE,
                        'is_featured' => false,
                    ]);
                }
            });
    }
}
