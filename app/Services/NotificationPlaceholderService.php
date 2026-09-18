<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\Order;
use App\Models\Product;
use App\Models\SiteSetting;
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

    public function notifyOrderPlaced(Order $order): void
    {
        $this->send(
            channel: 'sms_whatsapp_placeholder',
            to: $order->customer_phone,
            template: 'shop_order_placed',
            message: "UniqBid: Order {$order->reference} confirmed for ".Money::kes($order->total).'. Track it any time on the order tracking page.',
            context: [
                'order_id' => $order->id,
                'email' => $order->customer_email,
            ],
        );
    }

    public function notifyOrderCancelled(Order $order): void
    {
        $this->send(
            channel: 'sms_whatsapp_placeholder',
            to: $order->customer_phone,
            template: 'shop_order_cancelled',
            message: "UniqBid: Order {$order->reference} was cancelled before dispatch. A refund to the original payment method will follow once live payments are connected.",
            context: [
                'order_id' => $order->id,
                'email' => $order->customer_email,
            ],
        );
    }

    public function notifyOrderDispatched(Order $order): void
    {
        $message = $order->delivery_method === Order::DELIVERY_COLLECTION
            ? "UniqBid: Order {$order->reference} is ready for collection at the Westlands office."
            : "UniqBid: Order {$order->reference} has been dispatched. Track it on the order tracking page.";

        $this->send(
            channel: 'sms_whatsapp_placeholder',
            to: $order->customer_phone,
            template: 'shop_order_dispatched',
            message: $message,
            context: [
                'order_id' => $order->id,
                'email' => $order->customer_email,
            ],
        );
    }

    public function notifyOrderDelivered(Order $order): void
    {
        $message = $order->delivery_method === Order::DELIVERY_COLLECTION
            ? "UniqBid: Order {$order->reference} was collected. Thank you."
            : "UniqBid: Order {$order->reference} was marked delivered. Eligible unused items may be returned within 7 days.";

        $this->send(
            channel: 'sms_whatsapp_placeholder',
            to: $order->customer_phone,
            template: 'shop_order_delivered',
            message: $message,
            context: [
                'order_id' => $order->id,
                'email' => $order->customer_email,
            ],
        );
    }

    public function guestListingSubmitted(Product $product): void
    {
        $product->loadMissing('seller');

        $adminEmail = SiteSetting::current()->email ?: 'support@uniqbid.co.ke';
        $seller = $product->seller->name ?? 'Guest seller';
        $phone = $product->seller->phone ?? 'n/a';

        $this->send(
            channel: 'admin_email_placeholder',
            to: $adminEmail,
            template: 'guest_listing_submitted',
            message: "UniqBid admin: New guest shop listing “{$product->name}” from {$seller} ({$phone}) is awaiting approval.",
            context: [
                'product_id' => $product->id,
                'seller_user_id' => $product->seller_user_id,
                'admin_url' => route('admin.guest-listings', absolute: true),
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
