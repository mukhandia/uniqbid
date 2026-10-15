<?php

namespace App\Models;

use Database\Factories\BidderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $email
 * @property string $phone
 * @property string|null $whatsapp_number
 * @property bool $marketing_consent
 * @property Carbon|null $marketing_consented_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'name', 'email', 'phone', 'whatsapp_number', 'marketing_consent', 'marketing_consented_at'])]
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

    /**
     * @return HasOne<AuctionBid, $this>
     */
    public function latestBid(): HasOne
    {
        return $this->hasOne(AuctionBid::class)->latestOfMany();
    }

    /**
     * @return HasMany<BidderMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(BidderMessage::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<Bidder>  $query
     * @return Builder<Bidder>
     */
    public function scopeMarketingConsent(Builder $query): Builder
    {
        return $query->where('marketing_consent', true);
    }

    /**
     * @param  Builder<Bidder>  $query
     * @return Builder<Bidder>
     */
    public function scopeGuest(Builder $query): Builder
    {
        return $query->whereNull('user_id');
    }
}
