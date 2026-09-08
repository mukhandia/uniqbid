<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $shippable_type
 * @property int $shippable_id
 * @property string $method
 * @property string $status
 * @property string|null $recipient_name
 * @property string|null $recipient_phone
 * @property string|null $address
 * @property int $fee
 * @property string|null $notes
 * @property CarbonInterface|null $dispatched_at
 * @property CarbonInterface|null $delivered_at
 * @property CarbonInterface|null $collected_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'shippable_type',
    'shippable_id',
    'method',
    'status',
    'recipient_name',
    'recipient_phone',
    'address',
    'fee',
    'notes',
    'dispatched_at',
    'delivered_at',
    'collected_at',
])]
class Shipment extends Model
{
    public const METHOD_NAIROBI_DELIVERY = 'nairobi_delivery';

    public const METHOD_WESTLANDS_COLLECTION = 'westlands_collection';

    public const METHOD_UPCOUNTRY = 'upcountry';

    public const STATUS_PENDING = 'pending';

    public const STATUS_READY = 'collection_ready';

    public const STATUS_DISPATCHED = 'dispatched';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_COLLECTED = 'collected';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fee' => 'integer',
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
            'collected_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function shippable(): MorphTo
    {
        return $this->morphTo();
    }

    public function methodLabel(): string
    {
        return match ($this->method) {
            self::METHOD_NAIROBI_DELIVERY => 'Nairobi delivery',
            self::METHOD_WESTLANDS_COLLECTION => 'Westlands collection',
            self::METHOD_UPCOUNTRY => 'Upcountry delivery',
            default => str_replace('_', ' ', $this->method),
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_READY => 'Ready for collection',
            self::STATUS_DISPATCHED => 'Dispatched',
            self::STATUS_DELIVERED => 'Delivered',
            self::STATUS_COLLECTED => 'Collected',
            self::STATUS_CANCELLED => 'Cancelled',
            default => str_replace('_', ' ', $this->status),
        };
    }
}
