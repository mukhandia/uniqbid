<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Support\Money;
use Illuminate\Support\Facades\Log;

class NotificationPlaceholderService
{
    public function paymentSucceeded(AuctionBid $bid): void
    {
        $bid->loadMissing(['auction', 'bidder']);

        $this->send(
            channel: 'sms_whatsapp_placeholder',
            to: $bid->bidder->phone,
            template: 'bid_payment_succeeded',
            message: "UniqBid: Payment received for {$bid->auction->name}. Ref {$bid->reference}. Your bid of ".Money::kes($bid->amount).' is recorded.',
            context: [
                'bid_id' => $bid->id,
                'email' => $bid->bidder->email,
            ],
        );
    }

    public function paymentFailed(AuctionBid $bid): void
    {
        $bid->loadMissing(['auction', 'bidder']);

        $this->send(
            channel: 'sms_whatsapp_placeholder',
            to: $bid->bidder->phone,
            template: 'bid_payment_failed',
            message: "UniqBid: Payment for {$bid->auction->name} was not completed. No fee was charged. You can try again from the sale page.",
            context: [
                'bid_id' => $bid->id,
                'email' => $bid->bidder->email,
            ],
        );
    }

    public function successfulBuyer(Auction $auction, AuctionBid $bid): void
    {
        $bid->loadMissing('bidder');

        $this->send(
            channel: 'sms_whatsapp_placeholder',
            to: $bid->bidder->phone,
            template: 'successful_buyer',
            message: 'UniqBid: Your bid of '.Money::kes($bid->amount)." secured {$auction->name}. Pay this bid amount on collection/delivery within 7 days. Contact support for pickup instructions.",
            context: [
                'auction_id' => $auction->id,
                'bid_id' => $bid->id,
                'email' => $bid->bidder->email,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function send(string $channel, string $to, string $template, string $message, array $context = []): void
    {
        Log::info('notification_placeholder', [
            'channel' => $channel,
            'to' => $to,
            'template' => $template,
            'message' => $message,
            ...$context,
        ]);
    }
}
