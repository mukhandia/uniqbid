<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class SyncCatalogueProductImagesSeeder extends Seeder
{
    /**
     * Idempotent: apply catalogue `image` paths onto existing shop products.
     * Safe for staging/production deploys.
     */
    public function run(): void
    {
        $cataloguePath = database_path('data/catalogue_launch.json');
        $decoded = is_file($cataloguePath)
            ? json_decode((string) file_get_contents($cataloguePath), true)
            : null;

        /** @var list<array<string, mixed>> $productRows */
        $productRows = is_array($decoded) && isset($decoded['products']) && is_array($decoded['products'])
            ? $decoded['products']
            : [];

        $updated = 0;
        $missing = 0;

        foreach ($productRows as $row) {
            $slug = (string) ($row['slug'] ?? '');
            $image = (string) ($row['image'] ?? '');

            if ($slug === '' || $image === '') {
                continue;
            }

            $product = Product::query()->where('slug', $slug)->first();

            if ($product === null) {
                $missing++;

                continue;
            }

            if ($product->image === $image) {
                continue;
            }

            $product->forceFill(['image' => $image])->save();
            $updated++;
        }

        $this->command->info("Catalogue product images: updated {$updated}, skipped missing products {$missing}.");
    }
}
