<?php

namespace App\Models;

use Database\Factories\AuctionResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    'buyer_display',
    'sold_on',
])]
class AuctionResult extends Model
{
    /** @use HasFactory<AuctionResultFactory> */
    use HasFactory;

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

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
