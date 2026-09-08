<?php

namespace Database\Seeders;

use App\Models\Auction;
use App\Models\AuctionResult;
use App\Models\Category;
use App\Models\Product;
use App\Models\Subcategory;
use App\Services\CreateSaleFromProductService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UniqBidDemoSeeder extends Seeder
{
    /**
     * Seed demo data matching the Lovable POC.
     */
    public function run(): void
    {
        // Older standalone demo sales are no longer seeded as live.
        // Live Auctions now only shows sales linked to auction-channel products
        // (see seedLaunchAuctionProducts below).

        $placeholderImages = [
            '/images/products/leather-cardholder.png',
            '/images/products/silk-scarf.png',
            '/images/products/executive-hamper.png',
            '/images/products/maasai-bracelet.png',
            '/images/products/leather-belt.png',
            '/images/products/coffee-gift.png',
            '/images/products/wooden-watch.png',
            '/images/products/corporate-hamper.png',
        ];

        $cataloguePath = database_path('data/catalogue_launch.json');
        $decoded = is_file($cataloguePath)
            ? json_decode((string) file_get_contents($cataloguePath), true)
            : null;
        $catalogue = is_array($decoded) ? $decoded : [];

        /** @var list<array<string, mixed>> $categoryRows */
        $categoryRows = isset($catalogue['categories']) && is_array($catalogue['categories'])
            ? $catalogue['categories']
            : [];
        /** @var list<array<string, mixed>> $subcategoryRows */
        $subcategoryRows = isset($catalogue['subcategories']) && is_array($catalogue['subcategories'])
            ? $catalogue['subcategories']
            : [];
        /** @var list<array<string, mixed>> $productRows */
        $productRows = isset($catalogue['products']) && is_array($catalogue['products'])
            ? $catalogue['products']
            : [];

        $categoriesByName = [];

        foreach ($categoryRows as $row) {
            $category = Category::query()->updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'name' => $row['name'],
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                    'is_active' => true,
                ]
            );
            $categoriesByName[$category->name] = $category;
        }

        $subcategoriesByKey = [];

        foreach ($subcategoryRows as $row) {
            $category = $categoriesByName[$row['category']] ?? null;

            if ($category === null) {
                continue;
            }

            $subcategory = Subcategory::query()->updateOrCreate(
                [
                    'category_id' => $category->id,
                    'slug' => $row['slug'],
                ],
                [
                    'name' => $row['name'],
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                    'is_active' => true,
                ]
            );
            $subcategoriesByKey[$category->name.'|'.$subcategory->name] = $subcategory;
        }

        foreach ($productRows as $index => $product) {
            $categoryName = $product['category'];
            $subcategoryName = $product['subcategory'] ?? '';
            $key = $categoryName.'|'.$subcategoryName;
            $subcategory = $subcategoriesByKey[$key] ?? null;

            if ($subcategory === null && isset($categoriesByName[$categoryName])) {
                $category = $categoriesByName[$categoryName];
                $subcategory = Subcategory::query()->firstOrCreate(
                    [
                        'category_id' => $category->id,
                        'slug' => Str::slug($subcategoryName ?: 'general'),
                    ],
                    [
                        'name' => $subcategoryName !== '' ? $subcategoryName : 'General',
                        'sort_order' => 999,
                        'is_active' => true,
                    ]
                );
                $subcategoriesByKey[$key] = $subcategory;
            }

            Product::query()->updateOrCreate(
                ['slug' => $product['slug']],
                [
                    'subcategory_id' => $subcategory?->id,
                    'name' => $product['name'],
                    'category' => $categoryName,
                    'subcategory' => $subcategoryName,
                    'gender_age' => $product['gender_age'] ?? 'Unisex',
                    'price_tier' => $product['price_tier'] ?? 'Affordable',
                    'price' => (int) $product['price'],
                    'stock' => ($product['is_featured'] ?? false) ? 25 : random_int(8, 40),
                    'reserved_stock' => 0,
                    'description' => trim(implode(' ', array_filter([
                        $product['name'].'.',
                        $subcategoryName !== '' ? "From our {$subcategoryName} range." : null,
                        "Part of UniqBid's {$categoryName} shop selection.",
                        ($product['is_returnable'] ?? true)
                            ? 'Eligible for return within 7 days if unused and in original packaging.'
                            : 'Marked non-returnable under the Refund Policy (personalised or hygiene-restricted item).',
                    ]))),
                    'image' => $placeholderImages[$index % count($placeholderImages)],
                    'is_featured' => (bool) ($product['is_featured'] ?? false),
                    'is_returnable' => (bool) ($product['is_returnable'] ?? true),
                    'channel' => Product::CHANNEL_SHOP,
                ]
            );
        }

        $this->seedLaunchAuctionProducts();

        $results = [
            [
                'slug' => 's1',
                'name' => 'iPhone 14 128GB',
                'image' => '/images/results/iphone-14.png',
                'sale_price' => 3421,
                'buyer' => 'Wanjiru M.',
                'date' => '2026-07-18',
                'category' => 'Electronics',
            ],
            [
                'slug' => 's2',
                'name' => 'LG 43" Smart TV',
                'image' => '/images/results/lg-tv-43.png',
                'sale_price' => 1207,
                'buyer' => 'Kevin O.',
                'date' => '2026-07-15',
                'category' => 'Electronics',
            ],
            [
                'slug' => 's3',
                'name' => 'Fridge (Hisense 200L)',
                'image' => '/images/results/hisense-fridge.png',
                'sale_price' => 890,
                'buyer' => 'Aisha H.',
                'date' => '2026-07-12',
                'category' => 'Home',
            ],
            [
                'slug' => 's4',
                'name' => 'PS4 Pro Bundle',
                'image' => '/images/results/ps4-pro.png',
                'sale_price' => 2015,
                'buyer' => 'Brian K.',
                'date' => '2026-07-10',
                'category' => 'Electronics',
            ],
            [
                'slug' => 's5',
                'name' => 'MacBook Pro 13"',
                'image' => '/images/results/macbook-pro.png',
                'sale_price' => 5487,
                'buyer' => 'Fatuma A.',
                'date' => '2026-07-05',
                'category' => 'Electronics',
            ],
            [
                'slug' => 's6',
                'name' => 'Bajaj Boxer 150',
                'image' => '/images/results/boxer-150.png',
                'sale_price' => 8320,
                'buyer' => 'Peter N.',
                'date' => '2026-06-28',
                'category' => 'Vehicles',
            ],
            [
                'slug' => 's7',
                'name' => 'Ramtons Cooker',
                'image' => '/images/results/gas-cooker.png',
                'sale_price' => 1024,
                'buyer' => 'Grace W.',
                'date' => '2026-06-24',
                'category' => 'Home',
            ],
            [
                'slug' => 's8',
                'name' => 'iPad Air',
                'image' => '/images/results/ipad-air.png',
                'sale_price' => 2765,
                'buyer' => 'Samuel K.',
                'date' => '2026-06-19',
                'category' => 'Electronics',
            ],
        ];

        foreach ($results as $result) {
            AuctionResult::query()->updateOrCreate(
                ['slug' => $result['slug']],
                [
                    'name' => $result['name'],
                    'category' => $result['category'],
                    'image' => $result['image'],
                    'sale_price' => $result['sale_price'],
                    'buyer_display' => $result['buyer'],
                    'sold_on' => $result['date'],
                ]
            );
        }

        Auction::ensureHomepageHero();
    }

    private function seedLaunchAuctionProducts(): void
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

        foreach ($launchProducts as $index => $row) {
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
                continue;
            }

            $auction = $saleService->handle($product, [
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
        }
    }
}
