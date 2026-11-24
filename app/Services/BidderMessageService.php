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
    public function __construct(private readonly MobileSasaSmsService $sms) {}

    /**
     * Records an admin message to a bidder on a sale. When Mobile Sasa is connected, the SMS is
     * queued immediately. Otherwise the row stays pending until SMS is available.
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

        if (! $this->sms->isReady()) {
            return $message;
        }

        try {
            $this->sms->send(
                phone: (string) $bidder->phone,
                message: trim($body),
                trackingId: 'MSG-'.$message->id,
            );

            $message->update([
                'status' => BidderMessage::STATUS_DELIVERED,
                'delivered_at' => now(),
                'failure_reason' => null,
            ]);
        } catch (ValidationException $exception) {
            $message->update([
                'status' => BidderMessage::STATUS_FAILED,
                'failure_reason' => str((string) collect($exception->errors())->flatten()->first())
                    ->limit(250, '')
                    ->toString(),
            ]);
        }

        return $message->fresh() ?? $message;
    }
}
