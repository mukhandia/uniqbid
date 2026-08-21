<?php

namespace App\Models;

use Database\Factories\AuctionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $product_id
 * @property string $slug
 * @property string $name
 * @property string $category
 * @property string $description
 * @property int $bid_fee
 * @property int $min_bid
 * @property int $max_bid
 * @property int $bid_increment
 * @property int $retail_value
 * @property int $bids_count
 * @property Carbon $ends_at
 * @property string $status
 * @property bool $is_featured
 * @property array<int, int>|null $similar_sales
 * @property Carbon|null $settled_at
 * @property int|null $settled_by
 * @property string $no_unique_bid_rule
 * @property int $extension_minutes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'product_id',
    'slug',
    'name',
    'category',
    'description',
    'bid_fee',
    'min_bid',
    'max_bid',
    'bid_increment',
    'retail_value',
    'bids_count',
    'ends_at',
    'status',
    'is_featured',
    'similar_sales',
    'settled_at',
    'settled_by',
    'no_unique_bid_rule',
    'extension_minutes',
])]
class Auction extends Model
{
    /** @use HasFactory<AuctionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bid_fee' => 'integer',
            'min_bid' => 'integer',
            'max_bid' => 'integer',
            'bid_increment' => 'integer',
            'retail_value' => 'integer',
            'bids_count' => 'integer',
            'ends_at' => 'datetime',
            'is_featured' => 'boolean',
            'similar_sales' => 'array',
            'settled_at' => 'datetime',
            'extension_minutes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<AuctionImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(AuctionImage::class)->orderBy('sort_order');
    }

    /**
     * @return HasOne<AuctionResult, $this>
     */
    public function result(): HasOne
    {
        return $this->hasOne(AuctionResult::class);
    }

    /**
     * @return HasMany<AuctionBid, $this>
     */
    public function bids(): HasMany
    {
        return $this->hasMany(AuctionBid::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function settledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }

    /**
     * @param  Builder<Auction>  $query
     * @return Builder<Auction>
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', 'live')->where('ends_at', '>', now());
    }

    /**
     * @param  Builder<Auction>  $query
     * @return Builder<Auction>
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function primaryImageUrl(): string
    {
        return (string) ($this->images->value('url') ?? '');
    }

    public function endsAtMs(): int
    {
        return (int) $this->ends_at->getTimestampMs();
    }
}
