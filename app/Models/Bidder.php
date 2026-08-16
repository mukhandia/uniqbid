<?php

namespace App\Models;

use Database\Factories\BidderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'email', 'phone', 'whatsapp_number', 'marketing_consent', 'marketing_consented_at'])]
class Bidder extends Model
{
    /** @use HasFactory<BidderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'marketing_consent' => 'boolean',
            'marketing_consented_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<AuctionBid, $this>
     */
    public function bids(): HasMany
    {
        return $this->hasMany(AuctionBid::class);
    }
}
