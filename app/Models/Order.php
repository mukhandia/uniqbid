<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
