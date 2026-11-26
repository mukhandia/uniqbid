<?php

namespace App\Models;

use App\Support\Countdown;
use Carbon\CarbonInterface;
use Database\Factories\AuctionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
 * @property int $current_round
 * @property float $duration_hours
 * @property int $display_order
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
    'current_round',
    'duration_hours',
    'display_order',
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
            'current_round' => 'integer',
            'duration_hours' => 'float',
            'display_order' => 'integer',
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
     * @return HasMany<AuctionRound, $this>
     */
    public function rounds(): HasMany
    {
        return $this->hasMany(AuctionRound::class)->orderBy('round');
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
     * Open live sales that belong to auction-channel products (public Live Auctions catalogue).
     *
     * @param  Builder<Auction>  $query
     * @return Builder<Auction>
     */
    public function scopeLiveOnAuctionChannel(Builder $query): Builder
    {
        return $query
            ->live()
            ->whereNotNull('product_id')
            ->whereHas('product', function (Builder $product): void {
                $product->whereIn('channel', [Product::CHANNEL_AUCTION, Product::CHANNEL_BOTH]);
            });
    }

    /** Public auction detail pages: currently live sales and settled sale records. */
    public function scopePubliclyIndexable(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $public): void {
                $public->where(function (Builder $live): void {
                    $live->where('status', 'live')->where('ends_at', '>', now());
                })->orWhereNotNull('settled_at');
            })
            ->whereNotIn('status', ['voided', 'draft'])
            ->whereHas('product', function (Builder $product): void {
                $product->whereIn('channel', [Product::CHANNEL_AUCTION, Product::CHANNEL_BOTH]);
            });
    }

    /**
     * @param  Builder<Auction>  $query
     * @return Builder<Auction>
     */
    public function scopeEndedUnsettled(Builder $query): Builder
    {
        return $query
            ->whereNull('settled_at')
            ->where('ends_at', '<=', now())
            ->whereNotIn('status', ['voided', 'draft']);
    }

    /**
     * @param  Builder<Auction>  $query
     * @return Builder<Auction>
     */
    public function scopeBlockingNewSale(Builder $query): Builder
    {
        return $query
            ->whereNull('settled_at')
            ->whereNotIn('status', ['voided', 'draft']);
    }

    public function isOpenLive(): bool
    {
        return $this->status === 'live' && $this->ends_at->isFuture();
    }

    public function needsSettle(): bool
    {
        return $this->settled_at === null
            && $this->ends_at->isPast()
            && ! in_array($this->status, ['voided', 'draft'], true);
    }

    /**
     * @param  Builder<Auction>  $query
     * @return Builder<Auction>
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * @param  Builder<Auction>  $query
     * @return Builder<Auction>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($term): void {
            $builder
                ->where('name', 'like', '%'.$term.'%')
                ->orWhere('slug', 'like', '%'.$term.'%')
                ->orWhere('category', 'like', '%'.$term.'%')
                ->orWhere('description', 'like', '%'.$term.'%');
        });
    }

    /**
     * @param  Builder<Auction>  $query
     * @return Builder<Auction>
     */
    public function scopeAdminStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            'live' => $query->live(),
            'ending_soon' => $query->live()->where('ends_at', '<=', now()->addHours(6)),
            'needs_settle' => $query->endedUnsettled(),
            'to_process' => $query->whereHas('result', function (Builder $result): void {
                $result->where(function (Builder $inner): void {
                    $inner->where('sale_payment_status', AuctionResult::PAYMENT_AWAITING)
                        ->orWhereNotIn('fulfilment_status', [
                            AuctionResult::FULFILMENT_COLLECTED,
                            AuctionResult::FULFILMENT_DELIVERED,
                            AuctionResult::FULFILMENT_REDEEMED,
                        ]);
                });
            }),
            'settled' => $query->whereNotNull('settled_at'),
            'voided' => $query->where('status', 'voided'),
            'draft' => $query->where('status', 'draft'),
            'homepage' => $query->featured(),
            default => $query,
        };
    }

    /**
     * @param  Builder<Auction>  $query
     * @return Builder<Auction>
     */
    public function scopeAdminCategory(Builder $query, string $category): Builder
    {
        if ($category === '') {
            return $query;
        }

        return $query->where('category', $category);
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

    public function countdownLabel(?CarbonInterface $now = null): string
    {
        return Countdown::label($this->ends_at, $now);
    }

    public function makeHomepageHero(): void
    {
        DB::transaction(function (): void {
            if (! $this->isOpenLive()) {
                return;
            }

            static::query()
                ->whereKeyNot($this->id)
                ->where('is_featured', true)
                ->update(['is_featured' => false]);

            if (! $this->is_featured) {
                $this->forceFill(['is_featured' => true])->save();
            }
        });
    }

    public function removeFromHomepageHero(): void
    {
        if ($this->is_featured) {
            $this->forceFill(['is_featured' => false])->save();
        }

        static::ensureHomepageHero(exceptId: $this->id);
    }

    /**
     * Keep at most one homepage hero, and only for an open live sale.
     * If a featured sale is no longer live, promote another live sale automatically.
     */
    public static function ensureHomepageHero(?int $exceptId = null): ?self
    {
        return DB::transaction(function () use ($exceptId): ?self {
            $staleFeatured = static::query()
                ->where('is_featured', true)
                ->where(function (Builder $query): void {
                    $query->where('status', '!=', 'live')
                        ->orWhere('ends_at', '<=', now());
                })
                ->lockForUpdate()
                ->pluck('id');

            if ($staleFeatured->isNotEmpty()) {
                static::query()->whereIn('id', $staleFeatured)->update(['is_featured' => false]);
            }

            if ($exceptId !== null) {
                static::query()->whereKey($exceptId)->where('is_featured', true)->update(['is_featured' => false]);
            }

            $liveFeatured = static::query()
                ->liveOnAuctionChannel()
                ->featured()
                ->orderBy('display_order')
                ->orderBy('ends_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($liveFeatured->count() > 1) {
                $keep = $liveFeatured->first();

                static::query()
                    ->where('is_featured', true)
                    ->whereKeyNot($keep->id)
                    ->update(['is_featured' => false]);

                return $keep;
            }

            if ($liveFeatured->count() === 1) {
                return $liveFeatured->first();
            }

            $shouldPromote = $staleFeatured->isNotEmpty() || $exceptId !== null;

            if (! $shouldPromote) {
                return null;
            }

            $replacement = static::query()
                ->liveOnAuctionChannel()
                ->when($exceptId !== null, fn (Builder $query): Builder => $query->whereKeyNot($exceptId))
                ->orderBy('display_order')
                ->orderBy('ends_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($replacement === null) {
                return null;
            }

            static::query()
                ->where('is_featured', true)
                ->update(['is_featured' => false]);

            $replacement->forceFill(['is_featured' => true])->save();

            return $replacement->fresh();
        });
    }
}
