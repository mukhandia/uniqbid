<?php

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property int|null $product_id
 * @property int|null $seller_user_id
 * @property string $product_name
 * @property string $product_slug
 * @property string $category
 * @property int $unit_price
 * @property int $quantity
 * @property int $line_total
 * @property int $commission_amount
 * @property int $seller_payout_amount
 * @property string $seller_payout_status
 * @property Carbon|null $seller_payout_paid_at
 * @property bool $is_returnable
 */
#[Fillable([
    'order_id',
    'product_id',
    'seller_user_id',
    'product_name',
    'product_slug',
    'category',
    'unit_price',
    'quantity',
    'line_total',
    'commission_amount',
    'seller_payout_amount',
    'seller_payout_status',
    'seller_payout_paid_at',
    'is_returnable',
])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    public const PAYOUT_NONE = 'none';

    public const PAYOUT_PENDING = 'pending';

    public const PAYOUT_PAID = 'paid';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'commission_amount' => 0,
        'seller_payout_amount' => 0,
        'seller_payout_status' => self::PAYOUT_NONE,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'integer',
            'quantity' => 'integer',
            'line_total' => 'integer',
            'commission_amount' => 'integer',
            'seller_payout_amount' => 'integer',
            'seller_payout_paid_at' => 'datetime',
            'is_returnable' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_user_id');
    }
}
