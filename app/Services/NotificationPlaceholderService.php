<?php

namespace App\Services;

use App\Models\AuctionBid;
use App\Models\Order;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\SupportTicket;
use App\Support\Money;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class NotificationPlaceholderService
{
    public function __construct(private readonly MobileSasaSmsService $sms) {}

    public function paymentSucceeded(AuctionBid $bid): void
    {
        $bid->loadMissing(['auction', 'bidder']);

        $this->sendCustomerSms(
            to: (string) $bid->bidder->phone,
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

        $this->sendCustomerSms(
            to: (string) $bid->bidder->phone,
            template: 'bid_payment_failed',
            message: "UniqBid: Payment for {$bid->auction->name} was not completed. No fee was charged. You can try again from the sale page.",
            context: [
                'bid_id' => $bid->id,
                'email' => $bid->bidder->email,
            ],
        );
    }

    public function notifyOrderPlaced(Order $order): void
    {
        $this->sendCustomerSms(
            to: (string) $order->customer_phone,
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
        $this->sendCustomerSms(
            to: (string) $order->customer_phone,
            template: 'shop_order_cancelled',
            message: "UniqBid: Order {$order->reference} was cancelled before dispatch.",
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

        $this->sendCustomerSms(
            to: (string) $order->customer_phone,
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

        $this->sendCustomerSms(
            to: (string) $order->customer_phone,
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

        $this->logPlaceholder(
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

    public function supportTicketSubmitted(SupportTicket $ticket): void
    {
        $adminEmail = SiteSetting::current()->email ?: 'support@uniqbid.co.ke';
        $contact = $ticket->email ?: ($ticket->phone ?: 'n/a');

        $this->logPlaceholder(
            channel: 'admin_email_placeholder',
            to: $adminEmail,
            template: 'support_ticket_submitted',
            message: "UniqBid admin: New support ticket {$ticket->reference()} from {$ticket->name} ({$contact}) — {$ticket->subject}.",
            context: [
                'support_ticket_id' => $ticket->id,
                'admin_url' => route('admin.tickets.show', $ticket, absolute: true),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function sendCustomerSms(string $to, string $template, string $message, array $context = []): void
    {
        $this->logPlaceholder(
            channel: 'sms_whatsapp_placeholder',
            to: $to,
            template: $template,
            message: $message,
            context: $context,
        );

        if (! $this->sms->isReady() || blank($to)) {
            return;
        }

        try {
            $this->sms->send(
                phone: $to,
                message: $message,
                trackingId: $this->trackingId($template, $context),
            );
        } catch (ValidationException $exception) {
            Log::warning('mobilesasa.notification.failed', [
                'template' => $template,
                'to' => $to,
                'reason' => collect($exception->errors())->flatten()->first(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function trackingId(string $template, array $context): string
    {
        $suffix = match ($template) {
            'bid_payment_succeeded', 'bid_payment_failed' => (string) ($context['bid_id'] ?? str()->random(6)),
            'shop_order_placed', 'shop_order_cancelled', 'shop_order_dispatched', 'shop_order_delivered' => (string) ($context['order_id'] ?? str()->random(6)),
            default => str()->random(8),
        };

        return str()->upper(str($template)->replace('_', '-')->toString()).'-'.$suffix;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logPlaceholder(string $channel, string $to, string $template, string $message, array $context = []): void
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
