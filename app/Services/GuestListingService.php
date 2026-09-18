<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\Subcategory;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GuestListingService
{
    public function __construct(
        private readonly NotificationPlaceholderService $notifications,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     description: string,
     *     price: int,
     *     category_id: int,
     *     subcategory_id: int,
     *     image: string
     * }  $data
     */
    public function submit(User $seller, array $data): Product
    {
        if ($seller->is_admin) {
            throw ValidationException::withMessages([
                'name' => 'Staff accounts cannot submit guest shop listings.',
            ]);
        }

        $category = Category::query()->whereKey($data['category_id'])->where('is_active', true)->firstOrFail();
        $subcategory = Subcategory::query()
            ->whereKey($data['subcategory_id'])
            ->where('category_id', $category->id)
            ->where('is_active', true)
            ->firstOrFail();

        $baseSlug = Str::slug($data['name']);
        $slug = $baseSlug !== '' ? $baseSlug : 'guest-listing';
        $unique = $slug;
        $suffix = 1;

        while (Product::query()->where('slug', $unique)->exists()) {
            $unique = $slug.'-'.$suffix;
            $suffix++;
        }

        $product = Product::query()->create([
            'seller_user_id' => $seller->id,
            'subcategory_id' => $subcategory->id,
            'slug' => $unique,
            'name' => $data['name'],
            'category' => $category->name,
            'subcategory' => $subcategory->name,
            'gender_age' => 'Unisex',
            'price_tier' => 'Guest listing',
            'description' => $data['description'],
            'price' => $data['price'],
            'stock' => 1,
            'reserved_stock' => 0,
            'image' => $data['image'],
            'is_featured' => false,
            'is_returnable' => false,
            'channel' => Product::CHANNEL_NONE,
            'approval_status' => Product::APPROVAL_PENDING,
            'auction_sort_order' => 0,
        ]);

        AuditLogger::log('products', 'guest shop listing submitted', 'guest_listing_submitted', $product, [
            'seller_user_id' => $seller->id,
        ]);

        $this->notifications->guestListingSubmitted($product);

        return $product;
    }

    public function approve(Product $product): Product
    {
        if (! $product->isGuestListing()) {
            throw ValidationException::withMessages([
                'product' => 'Only guest listings can be approved.',
            ]);
        }

        return DB::transaction(function () use ($product): Product {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);

            $product->forceFill([
                'approval_status' => Product::APPROVAL_APPROVED,
                'rejection_reason' => null,
                'approved_at' => now(),
                'channel' => Product::CHANNEL_SHOP,
            ])->save();

            AuditLogger::log('products', 'guest shop listing approved', 'guest_listing_approved', $product);

            return $product->fresh();
        });
    }

    public function reject(Product $product, string $reason = ''): Product
    {
        if (! $product->isGuestListing()) {
            throw ValidationException::withMessages([
                'product' => 'Only guest listings can be rejected.',
            ]);
        }

        return DB::transaction(function () use ($product, $reason): Product {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);

            $product->forceFill([
                'approval_status' => Product::APPROVAL_REJECTED,
                'rejection_reason' => filled($reason) ? $reason : null,
                'approved_at' => null,
                'channel' => Product::CHANNEL_NONE,
            ])->save();

            AuditLogger::log('products', 'guest shop listing rejected', 'guest_listing_rejected', $product, [
                'reason' => $reason,
            ]);

            return $product->fresh();
        });
    }

    public function commissionPercent(): int
    {
        return SiteSetting::current()->guestShopCommissionPercent();
    }

    /**
     * @return array{commission: int, payout: int, percent: int}
     */
    public function splitAmounts(int $lineTotal): array
    {
        $percent = $this->commissionPercent();
        $commission = (int) floor(($lineTotal * $percent) / 100);
        $payout = max(0, $lineTotal - $commission);

        return [
            'commission' => $commission,
            'payout' => $payout,
            'percent' => $percent,
        ];
    }
}
