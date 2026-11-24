<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $auction_id
 * @property int $round
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property string|null $outcome
 * @property int|null $winning_bid_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'auction_id',
    'round',
    'started_at',
    'ended_at',
    'outcome',
    'winning_bid_id',
])]
class AuctionRound extends Model
{
    public const OUTCOME_WINNER = 'winner';

    public const OUTCOME_NO_UNIQUE = 'no_unique';

    public const OUTCOME_VOIDED = 'voided';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'round' => 'integer',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
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
    public function winningBid(): BelongsTo
    {
        return $this->belongsTo(AuctionBid::class, 'winning_bid_id');
    }
}
