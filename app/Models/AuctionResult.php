<?php

namespace App\Models;

use Database\Factories\AuctionResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $auction_id
 * @property string $slug
 * @property string $name
 * @property string|null $category
 * @property string $image
 * @property int $sale_price
 * @property string $buyer_display
 * @property Carbon $sold_on
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'auction_id',
    'auction_bid_id',
    'slug',
    'name',
    'category',
    'image',
    'sale_price',
    'sale_payment_status',
    'sale_payment_paid_at',
    'fulfilment_status',
    'fulfilment_due_at',
    'outcome_choice',
    'redemption_amount',
    'redemption_payout_status',
    'redeemed_at',
    'buyer_display',
    'sold_on',
])]
class AuctionResult extends Model
{
    /** @use HasFactory<AuctionResultFactory> */
    use HasFactory;

    public const PAYMENT_AWAITING = 'awaiting_payment';

    public const PAYMENT_PAID = 'paid';

    public const FULFILMENT_PENDING = 'pending';

    public const FULFILMENT_COLLECTION_READY = 'collection_ready';

    public const FULFILMENT_DISPATCHED = 'dispatched';

    public const FULFILMENT_COLLECTED = 'collected';

    public const FULFILMENT_DELIVERED = 'delivered';

    public const FULFILMENT_REDEEMED = 'redeemed';

    public const REDEMPTION_PAYOUT_PENDING = 'pending';

    public const REDEMPTION_PAYOUT_PAID = 'paid';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sale_price' => 'integer',
            'sold_on' => 'date',
            'sale_payment_paid_at' => 'datetime',
            'fulfilment_due_at' => 'datetime',
            'redemption_amount' => 'integer',
            'redeemed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Auction, $this>
     */
    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    /**
     * @return BelongsTo<AuctionBid, $this>
     */
    public function auctionBid(): BelongsTo
    {
        return $this->belongsTo(AuctionBid::class);
    }

    /**
     * @return MorphMany<Shipment, $this>
     */
    public function shipments(): MorphMany
    {
        return $this->morphMany(Shipment::class, 'shippable');
    }

    public function activeShipment(): ?Shipment
    {
        return $this->shipments()
            ->where('status', '!=', Shipment::STATUS_CANCELLED)
            ->latest('id')
            ->first();
    }

    /**
     * @param  Builder<AuctionResult>  $query
     * @return Builder<AuctionResult>
     */
    public function scopeAwaitingSalePayment(Builder $query): Builder
    {
        return $query->where('sale_payment_status', 'awaiting_payment');
    }

    /**
     * @param  Builder<AuctionResult>  $query
     * @return Builder<AuctionResult>
     */
    public function scopeIncompleteFulfilment(Builder $query): Builder
    {
        return $query->whereNotIn('fulfilment_status', ['collected', 'delivered', 'redeemed']);
    }

    /**
     * @param  Builder<AuctionResult>  $query
     * @return Builder<AuctionResult>
     */
    public function scopeOverdueFulfilment(Builder $query): Builder
    {
        return $query
            ->incompleteFulfilment()
            ->whereNotNull('fulfilment_due_at')
            ->where('fulfilment_due_at', '<', now());
    }

    /**
     * @param  Builder<AuctionResult>  $query
     * @return Builder<AuctionResult>
     */
    public function scopeNeedsProcessing(Builder $query): Builder
    {
        return $query->where(function (Builder $inner): void {
            $inner->where('sale_payment_status', self::PAYMENT_AWAITING)
                ->orWhereNotIn('fulfilment_status', [
                    self::FULFILMENT_COLLECTED,
                    self::FULFILMENT_DELIVERED,
                    self::FULFILMENT_REDEEMED,
                ]);
        });
    }

    public function isSalePaid(): bool
    {
        return $this->sale_payment_status === self::PAYMENT_PAID;
    }

    public function isPickedUp(): bool
    {
        return in_array($this->fulfilment_status, [
            self::FULFILMENT_COLLECTED,
            self::FULFILMENT_DELIVERED,
            self::FULFILMENT_REDEEMED,
        ], true);
    }

    public function salePaymentLabel(): string
    {
        return $this->isSalePaid() ? 'Sale paid' : 'Awaiting sale payment';
    }

    public function fulfilmentLabel(): string
    {
        return match ($this->fulfilment_status) {
            self::FULFILMENT_PENDING => 'Not picked up',
            self::FULFILMENT_COLLECTION_READY => 'Ready for collection',
            self::FULFILMENT_DISPATCHED => 'Dispatched',
            self::FULFILMENT_COLLECTED => 'Picked up',
            self::FULFILMENT_DELIVERED => 'Delivered',
            self::FULFILMENT_REDEEMED => 'Redeemed for cash',
            default => str_replace('_', ' ', (string) $this->fulfilment_status),
        };
    }

    public function canMarkPaid(): bool
    {
        return ! $this->isSalePaid();
    }

    public function canMarkCollectionReady(): bool
    {
        return $this->fulfilment_status === self::FULFILMENT_PENDING;
    }

    public function canMarkCollected(): bool
    {
        return in_array($this->fulfilment_status, [self::FULFILMENT_PENDING, self::FULFILMENT_COLLECTION_READY], true);
    }

    public function canMarkDispatched(): bool
    {
        return in_array($this->fulfilment_status, [self::FULFILMENT_PENDING, self::FULFILMENT_COLLECTION_READY], true);
    }

    public function canMarkDelivered(): bool
    {
        return $this->fulfilment_status === self::FULFILMENT_DISPATCHED;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
