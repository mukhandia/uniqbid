<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Subcategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ShopCatalogueSeeder extends Seeder
{
    private const PLACEHOLDER_IMAGES = [
        '/images/products/leather-cardholder.png',
        '/images/products/silk-scarf.png',
        '/images/products/executive-hamper.png',
        '/images/products/maasai-bracelet.png',
        '/images/products/leather-belt.png',
        '/images/products/coffee-gift.png',
        '/images/products/wooden-watch.png',
        '/images/products/corporate-hamper.png',
    ];

    /**
     * Insert-only: creates shop categories, subcategories and products from
     * catalogue_launch.json. Existing rows (matched by slug) are never modified,
     * so admin edits are preserved if this is run again.
     */
    public function run(): void
    {
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
            $category = Category::query()->firstOrCreate(
                ['slug' => (string) $row['slug']],
                [
                    'name' => (string) $row['name'],
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                    'is_active' => true,
                ],
            );
            $categoriesByName[(string) $row['name']] = $category;
        }

        $subcategoriesByKey = [];

        foreach ($subcategoryRows as $row) {
            $category = $categoriesByName[(string) $row['category']] ?? null;

            if ($category === null) {
                continue;
            }

            $subcategoriesByKey[$row['category'].'|'.$row['name']] = Subcategory::query()->firstOrCreate(
                [
                    'category_id' => $category->id,
                    'slug' => (string) $row['slug'],
                ],
                [
                    'name' => (string) $row['name'],
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                    'is_active' => true,
                ],
            );
        }

        $created = 0;

        foreach ($productRows as $index => $row) {
            $categoryName = (string) $row['category'];
            $subcategoryName = (string) ($row['subcategory'] ?? '');
            $key = $categoryName.'|'.$subcategoryName;
            $subcategory = $subcategoriesByKey[$key] ?? null;

            if ($subcategory === null && isset($categoriesByName[$categoryName])) {
                $subcategory = Subcategory::query()->firstOrCreate(
                    [
                        'category_id' => $categoriesByName[$categoryName]->id,
                        'slug' => Str::slug($subcategoryName ?: 'general'),
                    ],
                    [
                        'name' => $subcategoryName !== '' ? $subcategoryName : 'General',
                        'sort_order' => 999,
                        'is_active' => true,
                    ],
                );
                $subcategoriesByKey[$key] = $subcategory;
            }

            $isFeatured = (bool) ($row['is_featured'] ?? false);
            $isReturnable = (bool) ($row['is_returnable'] ?? true);

            $product = Product::query()->firstOrCreate(
                ['slug' => (string) $row['slug']],
                [
                    'subcategory_id' => $subcategory?->id,
                    'name' => (string) $row['name'],
                    'category' => $categoryName,
                    'subcategory' => $subcategoryName,
                    'gender_age' => $row['gender_age'] ?? 'Unisex',
                    'price_tier' => $row['price_tier'] ?? 'Affordable',
                    'price' => (int) $row['price'],
                    'stock' => (int) ($row['stock'] ?? ($isFeatured ? 25 : random_int(8, 40))),
                    'reserved_stock' => 0,
                    'description' => trim(implode(' ', array_filter([
                        $row['name'].'.',
                        $subcategoryName !== '' ? "From our {$subcategoryName} range." : null,
                        "Part of UniqBid's {$categoryName} shop selection.",
                        $isReturnable
                            ? 'Eligible for return within 7 days if unused and in original packaging.'
                            : 'Marked non-returnable under the Refund Policy (personalised or hygiene-restricted item).',
                    ]))),
                    'image' => $row['image'] ?? self::PLACEHOLDER_IMAGES[$index % count(self::PLACEHOLDER_IMAGES)],
                    'is_featured' => $isFeatured,
                    'is_returnable' => $isReturnable,
                    'channel' => Product::CHANNEL_SHOP,
                ],
            );

            if ($product->wasRecentlyCreated) {
                $created++;
            }
        }

        $this->command->info('Shop catalogue: '.count($categoriesByName).' categories, '.count($subcategoriesByKey).' subcategories, '.$created.' new product(s).');
    }
}
