<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Subcategory;
use Illuminate\Database\Seeder;

class SyncCatalogueTaxonomySeeder extends Seeder
{
    /**
     * Idempotent: upsert categories and subcategories from catalogue_launch.json.
     */
    public function run(): void
    {
        $cataloguePath = database_path('data/catalogue_launch.json');
        $decoded = is_file($cataloguePath)
            ? json_decode((string) file_get_contents($cataloguePath), true)
            : null;

        /** @var list<array<string, mixed>> $categoryRows */
        $categoryRows = is_array($decoded) && isset($decoded['categories']) && is_array($decoded['categories'])
            ? $decoded['categories']
            : [];

        /** @var list<array<string, mixed>> $subcategoryRows */
        $subcategoryRows = is_array($decoded) && isset($decoded['subcategories']) && is_array($decoded['subcategories'])
            ? $decoded['subcategories']
            : [];

        $categoriesByName = [];

        foreach ($categoryRows as $row) {
            $category = Category::query()->updateOrCreate(
                ['slug' => (string) $row['slug']],
                [
                    'name' => (string) $row['name'],
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                    'is_active' => true,
                ],
            );
            $categoriesByName[$category->name] = $category;
        }

        $createdSubs = 0;

        foreach ($subcategoryRows as $row) {
            $category = $categoriesByName[(string) $row['category']] ?? null;

            if ($category === null) {
                continue;
            }

            Subcategory::query()->updateOrCreate(
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
            $createdSubs++;
        }

        $this->command->info('Catalogue taxonomy: '.count($categoriesByName).' categories, '.$createdSubs.' subcategories upserted.');
    }
}
