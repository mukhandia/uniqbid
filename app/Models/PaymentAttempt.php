<?php

namespace App\Models;

use Database\Factories\PaymentAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $auction_bid_id
 * @property string $provider
 * @property string $reference
 * @property string|null $checkout_request_id
 * @property string|null $merchant_request_id
 * @property string|null $mpesa_receipt
 * @property int $amount
 * @property string $status
 * @property string|null $failure_reason
 * @property array<string, mixed>|null $request_payload
 * @property array<string, mixed>|null $response_payload
 */
#[Fillable([
    'auction_bid_id',
    'provider',
    'reference',
    'checkout_request_id',
    'merchant_request_id',
    'mpesa_receipt',
    'amount',
    'status',
    'failure_reason',
    'request_payload',
    'response_payload',
])]
class PaymentAttempt extends Model
{
    /** @use HasFactory<PaymentAttemptFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'request_payload' => 'array',
            'response_payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<AuctionBid, $this>
     */
    public function auctionBid(): BelongsTo
    {
        return $this->belongsTo(AuctionBid::class);
    }
}
