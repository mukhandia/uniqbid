<?php

namespace Database\Seeders;

use App\Models\Auction;
use App\Models\AuctionResult;
use App\Models\Product;
use App\Support\PlaceholderImage;
use Illuminate\Database\Seeder;

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
                    ['label' => 'iPhone 15 Pro', 'bg' => '#1a3a6b'],
                    ['label' => 'Back View', 'bg' => '#213f70'],
                    ['label' => 'In Hand', 'bg' => '#2a4a80'],
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
                'images' => [['label' => 'Samsung QLED 55', 'bg' => '#0f2a4a']],
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
                'images' => [['label' => 'Boxer 150cc', 'bg' => '#1a2f5a']],
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
                'images' => [['label' => 'PS5 Slim', 'bg' => '#1e3a68']],
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
                'images' => [['label' => 'MacBook Air M3', 'bg' => '#132a52']],
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
                'images' => [['label' => 'Gas Cooker', 'bg' => '#1c3560']],
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
                    'url' => PlaceholderImage::make($image['label'], $image['bg']),
                    'label' => $image['label'],
                    'sort_order' => $index,
                ]);
            }
        }

        $products = [
            ['slug' => 'p1', 'name' => 'Leather Cardholder (Black)', 'category' => "Men's Accessories", 'price' => 2400, 'stock' => 12, 'label' => 'Cardholder', 'bg' => '#1a2f5a', 'description' => 'Full-grain leather cardholder, handmade in Kariokor.', 'is_featured' => true],
            ['slug' => 'p2', 'name' => 'Silk Neck Scarf', 'category' => "Women's Accessories", 'price' => 1800, 'stock' => 8, 'label' => 'Silk Scarf', 'bg' => '#2a4a80', 'description' => '100% mulberry silk, printed in Nairobi.', 'is_featured' => true],
            ['slug' => 'p3', 'name' => 'Executive Hamper (Premium)', 'category' => 'Hampers', 'price' => 8500, 'stock' => 4, 'label' => 'Executive Hamper', 'bg' => '#132a52', 'description' => 'Wine, chocolates, artisan snacks, and Kenyan coffee.', 'is_featured' => true],
            ['slug' => 'p4', 'name' => 'Beaded Bracelet (Maasai)', 'category' => "Women's Accessories", 'price' => 950, 'stock' => 22, 'label' => 'Maasai Bracelet', 'bg' => '#213f70', 'description' => 'Authentic Maasai beaded bracelet, handmade.'],
            ['slug' => 'p5', 'name' => 'Leather Belt (Brown)', 'category' => "Men's Accessories", 'price' => 2100, 'stock' => 15, 'label' => 'Leather Belt', 'bg' => '#1a2f5a', 'description' => 'Full-grain leather with brass buckle.', 'is_featured' => true],
            ['slug' => 'p6', 'name' => 'Gift Box: Coffee & Mug', 'category' => 'Gifts', 'price' => 1650, 'stock' => 30, 'label' => 'Coffee Gift', 'bg' => '#1e3a68', 'description' => '250g Kenyan AA coffee with branded mug.'],
            ['slug' => 'p7', 'name' => 'Wooden Watch', 'category' => "Men's Accessories", 'price' => 4200, 'stock' => 6, 'label' => 'Wooden Watch', 'bg' => '#0f2a4a', 'description' => 'Ebony wooden watch, Kenyan-made.'],
            ['slug' => 'p8', 'name' => 'Corporate Hamper', 'category' => 'Hampers', 'price' => 5400, 'stock' => 9, 'label' => 'Corporate Hamper', 'bg' => '#1c3560', 'description' => 'Perfect for teams: snacks, tea, notebook, pen.'],
        ];

        foreach ($products as $product) {
            Product::query()->updateOrCreate(
                ['slug' => $product['slug']],
                [
                    'name' => $product['name'],
                    'category' => $product['category'],
                    'price' => $product['price'],
                    'stock' => $product['stock'],
                    'description' => $product['description'],
                    'image' => PlaceholderImage::make($product['label'], $product['bg']),
                    'is_featured' => $product['is_featured'] ?? false,
                ]
            );
        }

        $results = [
            ['slug' => 's1', 'name' => 'iPhone 14 128GB', 'label' => 'iPhone 14', 'bg' => '#1a3a6b', 'sale_price' => 3421, 'buyer' => 'Wanjiru M.', 'date' => '2026-07-18', 'category' => 'Electronics'],
            ['slug' => 's2', 'name' => 'LG 43" Smart TV', 'label' => 'LG TV 43', 'bg' => '#0f2a4a', 'sale_price' => 1207, 'buyer' => 'Kevin O.', 'date' => '2026-07-15', 'category' => 'Electronics'],
            ['slug' => 's3', 'name' => 'Fridge (Hisense 200L)', 'label' => 'Hisense Fridge', 'bg' => '#1c3560', 'sale_price' => 890, 'buyer' => 'Aisha H.', 'date' => '2026-07-12', 'category' => 'Home'],
            ['slug' => 's4', 'name' => 'PS4 Pro Bundle', 'label' => 'PS4 Pro', 'bg' => '#1e3a68', 'sale_price' => 2015, 'buyer' => 'Brian K.', 'date' => '2026-07-10', 'category' => 'Electronics'],
            ['slug' => 's5', 'name' => 'MacBook Pro 13"', 'label' => 'MacBook Pro', 'bg' => '#132a52', 'sale_price' => 5487, 'buyer' => 'Fatuma A.', 'date' => '2026-07-05', 'category' => 'Electronics'],
            ['slug' => 's6', 'name' => 'Bajaj Boxer 150', 'label' => 'Boxer 150', 'bg' => '#1a2f5a', 'sale_price' => 8320, 'buyer' => 'Peter N.', 'date' => '2026-06-28', 'category' => 'Vehicles'],
            ['slug' => 's7', 'name' => 'Ramtons Cooker', 'label' => 'Cooker', 'bg' => '#1c3560', 'sale_price' => 1024, 'buyer' => 'Grace W.', 'date' => '2026-06-24', 'category' => 'Home'],
            ['slug' => 's8', 'name' => 'iPad Air', 'label' => 'iPad Air', 'bg' => '#1a3a6b', 'sale_price' => 2765, 'buyer' => 'Samuel K.', 'date' => '2026-06-19', 'category' => 'Electronics'],
        ];

        foreach ($results as $result) {
            AuctionResult::query()->updateOrCreate(
                ['slug' => $result['slug']],
                [
                    'name' => $result['name'],
                    'category' => $result['category'],
                    'image' => PlaceholderImage::make($result['label'], $result['bg']),
                    'sale_price' => $result['sale_price'],
                    'buyer_display' => $result['buyer'],
                    'sold_on' => $result['date'],
                ]
            );
        }
    }
}
