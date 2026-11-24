<?php

namespace Database\Seeders;

use App\Models\Auction;
use App\Models\AuctionResult;
use Illuminate\Database\Seeder;

class UniqBidDemoSeeder extends Seeder
{
    /**
     * Local/demo data only (includes sample past sales). Not for staging/production;
     * new servers use scripts/server-bootstrap.sh instead.
     */
    public function run(): void
    {
        $this->call(ShopCatalogueSeeder::class);
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
