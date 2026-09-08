<?php

namespace Database\Seeders;

use App\Models\Auction;
use App\Models\AuctionResult;
use App\Models\Category;
use App\Models\Product;
use App\Models\Subcategory;
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
                    'image' => $product['image'] ?? $placeholderImages[$index % count($placeholderImages)],
                    'is_featured' => (bool) ($product['is_featured'] ?? false),
                    'is_returnable' => (bool) ($product['is_returnable'] ?? true),
                    'channel' => Product::CHANNEL_SHOP,
                ]
            );
        }

        $this->call(LaunchAuctionCatalogueSeeder::class);

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
}
