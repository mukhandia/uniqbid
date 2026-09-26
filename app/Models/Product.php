<?php

namespace App\Models;

use Database\Factories\ProductFactory;
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
 * @property int|null $subcategory_id
 * @property int|null $seller_user_id
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
 * @property string $channel
 * @property string $approval_status
 * @property string|null $rejection_reason
 * @property Carbon|null $approved_at
 * @property Carbon|null $admin_seen_at
 * @property int $auction_sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'subcategory_id',
    'seller_user_id',
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
    'channel',
    'approval_status',
    'rejection_reason',
    'approved_at',
    'admin_seen_at',
    'auction_sort_order',
])]
class Product extends Model
{
    public const CHANNEL_NONE = 'none';

    public const CHANNEL_SHOP = 'shop';

    public const CHANNEL_AUCTION = 'auction';

    public const CHANNEL_BOTH = 'both';

    public const APPROVAL_APPROVED = 'approved';

    public const APPROVAL_PENDING = 'pending';

    public const APPROVAL_REJECTED = 'rejected';

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
        'channel' => self::CHANNEL_NONE,
        'approval_status' => self::APPROVAL_APPROVED,
        'auction_sort_order' => 0,
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
            'auction_sort_order' => 'integer',
            'approved_at' => 'datetime',
            'admin_seen_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_user_id');
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

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasOne<Auction, $this>
     */
    public function currentSale(): HasOne
    {
        return $this->hasOne(Auction::class)->ofMany(
            ['id' => 'max'],
            function (Builder $query): void {
                $query->whereNull('settled_at')
                    ->whereNotIn('status', ['voided', 'draft']);
            },
        );
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

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->where('stock', '<=', 0);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeInShop(Builder $query): Builder
    {
        return $query
            ->whereIn('channel', [self::CHANNEL_SHOP, self::CHANNEL_BOTH])
            ->where('approval_status', self::APPROVAL_APPROVED);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeGuestListings(Builder $query): Builder
    {
        return $query->whereNotNull('seller_user_id');
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopePendingGuestApproval(Builder $query): Builder
    {
        return $query->guestListings()->where('approval_status', self::APPROVAL_PENDING);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeUnseenPendingGuestApproval(Builder $query): Builder
    {
        return $query->pendingGuestApproval()->whereNull('admin_seen_at');
    }

    public function isGuestListing(): bool
    {
        return $this->seller_user_id !== null;
    }

    public function isPendingApproval(): bool
    {
        return $this->approval_status === self::APPROVAL_PENDING;
    }

    public function markAdminSeen(): void
    {
        if ($this->admin_seen_at !== null) {
            return;
        }

        $this->forceFill(['admin_seen_at' => now()])->save();
    }

    public function approvalLabel(): string
    {
        return match ($this->approval_status) {
            self::APPROVAL_PENDING => 'Pending approval',
            self::APPROVAL_REJECTED => 'Rejected',
            default => 'Approved',
        };
    }

    /**
     * Seller-facing status for guest shop listings (view-only monitoring).
     */
    public function guestListingStatusLabel(): string
    {
        return match (true) {
            $this->approval_status === self::APPROVAL_PENDING => 'Pending approval',
            $this->approval_status === self::APPROVAL_REJECTED => 'Rejected',
            $this->availableStock() <= 0 => 'Sold',
            default => 'Live in shop',
        };
    }

    public function guestListingStatusColor(): string
    {
        return match ($this->guestListingStatusLabel()) {
            'Pending approval' => 'amber',
            'Rejected' => 'red',
            'Sold' => 'zinc',
            default => 'green',
        };
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeInAuctionChannel(Builder $query): Builder
    {
        return $query->whereIn('channel', [self::CHANNEL_AUCTION, self::CHANNEL_BOTH]);
    }

    public function isInShop(): bool
    {
        return in_array($this->channel, [self::CHANNEL_SHOP, self::CHANNEL_BOTH], true);
    }

    public function isInAuctionChannel(): bool
    {
        return in_array($this->channel, [self::CHANNEL_AUCTION, self::CHANNEL_BOTH], true);
    }

    public function assignToShop(): void
    {
        $this->forceFill([
            'channel' => $this->isInAuctionChannel() ? self::CHANNEL_BOTH : self::CHANNEL_SHOP,
        ])->save();
    }

    public function assignToAuctionChannel(): void
    {
        $this->forceFill([
            'channel' => $this->isInShop() ? self::CHANNEL_BOTH : self::CHANNEL_AUCTION,
        ])->save();
    }

    public function removeFromShop(): void
    {
        $this->forceFill([
            'channel' => match ($this->channel) {
                self::CHANNEL_BOTH => self::CHANNEL_AUCTION,
                self::CHANNEL_SHOP => self::CHANNEL_NONE,
                default => $this->channel,
            },
        ])->save();
    }

    public function removeFromAuctionChannel(): void
    {
        $this->forceFill([
            'channel' => match ($this->channel) {
                self::CHANNEL_BOTH => self::CHANNEL_SHOP,
                self::CHANNEL_AUCTION => self::CHANNEL_NONE,
                default => $this->channel,
            },
        ])->save();
    }

    public function channelLabel(): string
    {
        return match ($this->channel) {
            self::CHANNEL_SHOP => 'Shop',
            self::CHANNEL_AUCTION => 'Auction',
            self::CHANNEL_BOTH => 'Shop & Auction',
            default => 'Unassigned',
        };
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeReservedForSales(Builder $query): Builder
    {
        return $query->where('reserved_stock', '>', 0);
    }
}
