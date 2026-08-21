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
        $auctions = [
            [
                'slug' => 'iphone-15-pro',
                'name' => 'iPhone 15 Pro 256GB (Titanium Blue)',
                'category' => 'Electronics',
                'bid_fee' => 50,
                'min_bid' => 1,
                'max_bid' => 15000,
                'bid_increment' => 1,
                'ends_in_hours' => 6,
                'bids_count' => 428,
                'description' => 'Brand-new, sealed iPhone 15 Pro 256GB in Titanium Blue. 1-year Apple Kenya warranty. Delivered nationwide or collect from our Nairobi office.',
                'retail_value' => 189000,
                'similar_sales' => [4210, 3850, 5120, 4600],
                'is_featured' => true,
                'images' => [
                    ['label' => 'iPhone 15 Pro', 'path' => '/images/auctions/iphone-15-pro.png'],
                ],
            ],
            [
                'slug' => 'samsung-tv-55',
                'name' => 'Samsung 55" 4K Smart TV (QLED)',
                'category' => 'Electronics',
                'bid_fee' => 30,
                'min_bid' => 1,
                'max_bid' => 8000,
                'bid_increment' => 1,
                'ends_in_hours' => 2,
                'bids_count' => 312,
                'description' => '2024 model Samsung QLED 55" 4K Smart TV. Sealed, with 2-year warranty. Free delivery within Nairobi.',
                'retail_value' => 89000,
                'similar_sales' => [2410, 1980, 3050],
                'images' => [
                    ['label' => 'Samsung QLED 55', 'path' => '/images/auctions/samsung-tv-55.png'],
                ],
            ],
            [
                'slug' => 'mkopa-boda',
                'name' => 'Boxer 150cc Motorbike (New, Registered)',
                'category' => 'Vehicles',
                'bid_fee' => 100,
                'min_bid' => 1,
                'max_bid' => 25000,
                'bid_increment' => 1,
                'ends_in_hours' => 23,
                'bids_count' => 891,
                'description' => 'Brand-new Bajaj Boxer 150cc with logbook, insurance, and helmet included. Ready to ride from our Nairobi yard.',
                'retail_value' => 175000,
                'similar_sales' => [8920, 7500, 9100],
                'images' => [
                    ['label' => 'Boxer 150cc', 'path' => '/images/auctions/boxer-150.png'],
                ],
            ],
            [
                'slug' => 'ps5',
                'name' => 'PlayStation 5 Slim Disc Edition',
                'category' => 'Electronics',
                'bid_fee' => 40,
                'min_bid' => 1,
                'max_bid' => 10000,
                'bid_increment' => 1,
                'ends_in_hours' => 11,
                'bids_count' => 256,
                'description' => 'Sealed PS5 Slim Disc Edition with 1 DualSense controller. 1-year warranty.',
                'retail_value' => 82000,
                'similar_sales' => [3120, 2890, 3400],
                'images' => [
                    ['label' => 'PS5 Slim', 'path' => '/images/auctions/ps5-slim.png'],
                ],
            ],
            [
                'slug' => 'macbook-air-m3',
                'name' => 'MacBook Air M3 13" 256GB (Midnight)',
                'category' => 'Electronics',
                'bid_fee' => 60,
                'min_bid' => 1,
                'max_bid' => 18000,
                'bid_increment' => 1,
                'ends_in_hours' => 30,
                'bids_count' => 189,
                'description' => 'Sealed MacBook Air M3 13" 256GB in Midnight. Apple Kenya warranty.',
                'retail_value' => 165000,
                'similar_sales' => [5100, 4650, 5800],
                'images' => [
                    ['label' => 'MacBook Air M3', 'path' => '/images/auctions/macbook-air-m3.png'],
                ],
            ],
            [
                'slug' => 'gas-cooker',
                'name' => 'Ramtons 4-Burner Gas Cooker with Oven',
                'category' => 'Home',
                'bid_fee' => 20,
                'min_bid' => 1,
                'max_bid' => 4000,
                'bid_increment' => 1,
                'ends_in_hours' => 0.5,
                'bids_count' => 542,
                'description' => 'Ramtons 4-burner gas cooker with electric oven. New, boxed, with delivery.',
                'retail_value' => 42000,
                'similar_sales' => [980, 1120, 1450],
                'images' => [
                    ['label' => 'Gas Cooker', 'path' => '/images/auctions/gas-cooker.png'],
                ],
            ],
        ];

        foreach ($auctions as $data) {
            $images = $data['images'];
            $hours = $data['ends_in_hours'];
            unset($data['images'], $data['ends_in_hours']);

            $auction = Auction::query()->updateOrCreate(
                ['slug' => $data['slug']],
                [
                    ...$data,
                    'ends_at' => now()->addHours($hours),
                    'status' => 'live',
                    'is_featured' => $data['is_featured'] ?? false,
                ]
            );

            $auction->images()->delete();

            foreach ($images as $index => $image) {
                $auction->images()->create([
                    'url' => $image['path'],
                    'label' => $image['label'],
                    'sort_order' => $index,
                ]);
            }
        }

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
                ]
            );
        }

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
    }
}
