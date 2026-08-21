<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $subcategory_id
 * @property string $slug
 * @property string $name
 * @property string $category
 * @property string|null $subcategory
 * @property string|null $gender_age
 * @property string|null $price_tier
 * @property string $description
 * @property int $price
 * @property int $stock
 * @property int $reserved_stock
 * @property string $image
 * @property bool $is_featured
 * @property bool $is_returnable
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'subcategory_id',
    'slug',
    'name',
    'category',
    'subcategory',
    'gender_age',
    'price_tier',
    'description',
    'price',
    'stock',
    'reserved_stock',
    'image',
    'is_featured',
    'is_returnable',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_featured' => false,
        'is_returnable' => true,
        'stock' => 0,
        'reserved_stock' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'stock' => 'integer',
            'reserved_stock' => 'integer',
            'is_featured' => 'boolean',
            'is_returnable' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Subcategory, $this>
     */
    public function subcategoryRelation(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class, 'subcategory_id');
    }

    /**
     * @return HasMany<Auction, $this>
     */
    public function auctions(): HasMany
    {
        return $this->hasMany(Auction::class);
    }

    public function availableStock(): int
    {
        return max(0, $this->stock - $this->reserved_stock);
    }

    public function syncCategoryLabels(?Subcategory $subcategory = null): void
    {
        $subcategory ??= $this->subcategoryRelation;

        if ($subcategory === null) {
            return;
        }

        $subcategory->loadMissing('category');

        $this->forceFill([
            'category' => $subcategory->category->name,
            'subcategory' => $subcategory->name,
        ]);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeInStock(Builder $query): Builder
    {
        return $query->whereColumn('stock', '>', 'reserved_stock');
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($term): void {
            $builder
                ->where('name', 'like', "%{$term}%")
                ->orWhere('category', 'like', "%{$term}%")
                ->orWhere('subcategory', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%");
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function isLowStock(): bool
    {
        $available = $this->availableStock();

        return $available > 0 && $available < 10;
    }
}
