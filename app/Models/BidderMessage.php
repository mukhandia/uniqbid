<?php

namespace App\Models;

use Database\Factories\BidderMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $auction_id
 * @property int $bidder_id
 * @property int|null $sent_by
 * @property string $phone
 * @property string $body
 * @property string $status
 * @property Carbon|null $delivered_at
 * @property string|null $failure_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['auction_id', 'bidder_id', 'sent_by', 'phone', 'body', 'status', 'delivered_at', 'failure_reason'])]
class BidderMessage extends Model
{
    /** @use HasFactory<BidderMessageFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    public const MAX_LENGTH = 1000;

    protected function casts(): array
    {
        return [
            'delivered_at' => 'datetime',
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
     * @return BelongsTo<Bidder, $this>
     */
    public function bidder(): BelongsTo
    {
        return $this->belongsTo(Bidder::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DELIVERED => 'Delivered',
            self::STATUS_FAILED => 'Failed',
            default => 'Pending',
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            self::STATUS_DELIVERED => 'green',
            self::STATUS_FAILED => 'red',
            default => 'amber',
        };
    }
}
