<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $reference
 * @property int|null $user_id
 * @property string $customer_name
 * @property string $customer_phone
 * @property string|null $customer_email
 * @property string $delivery_method
 * @property string|null $delivery_address
 * @property int $subtotal
 * @property int $delivery_fee
 * @property int $total
 * @property string $status
 * @property string $payment_provider
 * @property string|null $payment_reference
 * @property Carbon|null $paid_at
 * @property Carbon|null $dispatched_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'reference',
    'user_id',
    'customer_name',
    'customer_phone',
    'customer_email',
    'delivery_method',
    'delivery_address',
    'subtotal',
    'delivery_fee',
    'total',
    'status',
    'payment_provider',
    'payment_reference',
    'paid_at',
    'dispatched_at',
    'delivered_at',
    'cancelled_at',
    'cancel_reason',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    public const STATUS_PAID = 'paid';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_DISPATCHED = 'dispatched';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_CANCELLED = 'cancelled';

    public const DELIVERY_NAIROBI = 'nairobi';

    public const DELIVERY_COLLECTION = 'collection';

    public const DELIVERY_UPCOUNTRY = 'upcountry';

    /**
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_PAID => 'Paid: preparing',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_DISPATCHED => 'Dispatched',
            self::STATUS_DELIVERED => 'Delivered',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function deliveryMethods(): array
    {
        return [
            self::DELIVERY_NAIROBI => 'Delivery within Nairobi',
            self::DELIVERY_COLLECTION => 'Office collection (Westlands)',
            self::DELIVERY_UPCOUNTRY => 'Delivery outside Nairobi',
        ];
    }

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_PAID,
        'delivery_fee' => 0,
        'payment_provider' => 'mpesa_demo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'delivery_fee' => 'integer',
            'total' => 'integer',
            'paid_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function hasGuestListingItems(): bool
    {
        return $this->items->contains(fn (OrderItem $item): bool => $item->seller_user_id !== null);
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

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function canCancelBeforeDispatch(): bool
    {
        return in_array($this->status, [self::STATUS_PAID, self::STATUS_PROCESSING], true)
            && $this->dispatched_at === null;
    }

    public function canMarkProcessing(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function canMarkDispatched(): bool
    {
        return in_array($this->status, [self::STATUS_PAID, self::STATUS_PROCESSING], true);
    }

    public function canMarkDelivered(): bool
    {
        return $this->status === self::STATUS_DISPATCHED;
    }

    public function dispatchButtonLabel(): string
    {
        return $this->delivery_method === self::DELIVERY_COLLECTION
            ? 'Mark ready for collection'
            : 'Mark dispatched';
    }

    public function deliverButtonLabel(): string
    {
        return $this->delivery_method === self::DELIVERY_COLLECTION
            ? 'Mark collected'
            : 'Mark delivered';
    }

    /**
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopeAwaitingFulfilment(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PAID, self::STATUS_PROCESSING]);
    }

    /**
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopePaidNotCancelled(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_CANCELLED);
    }

    /**
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($term): void {
            $builder
                ->where('reference', 'like', '%'.$term.'%')
                ->orWhere('customer_name', 'like', '%'.$term.'%')
                ->orWhere('customer_phone', 'like', '%'.$term.'%')
                ->orWhere('customer_email', 'like', '%'.$term.'%')
                ->orWhere('payment_reference', 'like', '%'.$term.'%');
        });
    }

    /**
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopeAdminStatus(Builder $query, string $status): Builder
    {
        if ($status === 'attention') {
            return $query->awaitingFulfilment();
        }

        if ($status === 'all') {
            return $query;
        }

        if (array_key_exists($status, self::statuses())) {
            return $query->where('status', $status);
        }

        return $query->awaitingFulfilment();
    }

    /**
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopeAdminDelivery(Builder $query, string $method): Builder
    {
        if ($method === '' || ! array_key_exists($method, self::deliveryMethods())) {
            return $query;
        }

        return $query->where('delivery_method', $method);
    }

    /**
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopePlacedInPeriod(Builder $query, string $period): Builder
    {
        $start = match ($period) {
            'today' => now()->timezone('Africa/Nairobi')->startOfDay(),
            '7d' => now()->timezone('Africa/Nairobi')->startOfDay()->subDays(7),
            '30d' => now()->timezone('Africa/Nairobi')->startOfDay()->subDays(30),
            default => null,
        };

        if ($start === null) {
            return $query;
        }

        return $query->where('created_at', '>=', $start);
    }

    public function deliveryMethodLabel(): string
    {
        return match ($this->delivery_method) {
            self::DELIVERY_NAIROBI => 'Delivery within Nairobi',
            self::DELIVERY_COLLECTION => 'Office collection (Westlands)',
            self::DELIVERY_UPCOUNTRY => 'Delivery outside Nairobi',
            default => $this->delivery_method,
        };
    }

    public function deliveryChargeLabel(): string
    {
        return self::deliveryChargeLabelFor($this->delivery_method);
    }

    public static function deliveryChargeLabelFor(string $method): string
    {
        return $method === self::DELIVERY_UPCOUNTRY
            ? 'Charges apply'
            : 'Free';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PAID => 'Paid: preparing',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_DISPATCHED => 'Dispatched',
            self::STATUS_DELIVERED => 'Delivered',
            self::STATUS_CANCELLED => 'Cancelled',
            default => ucfirst($this->status),
        };
    }
}
