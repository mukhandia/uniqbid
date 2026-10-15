<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\Bidder;
use App\Models\BidderMessage;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Validation\ValidationException;

class BidderMessageService
{
    /**
     * Records an admin message to a bidder on a sale. Messages stay pending until an SMS provider is connected.
     */
    public function send(Auction $auction, Bidder $bidder, string $body, ?User $sender = null): BidderMessage
    {
        $hasBid = $auction->bids()->where('bidder_id', $bidder->id)->exists();

        if (! $hasBid) {
            throw ValidationException::withMessages([
                'messageBody' => 'This customer has not bid on this sale.',
            ]);
        }

        $message = BidderMessage::query()->create([
            'auction_id' => $auction->id,
            'bidder_id' => $bidder->id,
            'sent_by' => $sender?->id,
            'phone' => $bidder->phone,
            'body' => trim($body),
            'status' => BidderMessage::STATUS_PENDING,
        ]);

        AuditLogger::log('auctions', 'bidder messaged', 'bidder_messaged', $auction, [
            'bidder_message_id' => $message->id,
            'bidder_id' => $bidder->id,
            'phone' => $bidder->phone,
        ]);

        return $message;
    }
}
