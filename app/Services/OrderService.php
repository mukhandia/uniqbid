<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private readonly CartService $cart,
        private readonly AuctionBidService $phones,
        private readonly NotificationPlaceholderService $notifications,
        private readonly LogisticsService $logistics,
        private readonly GuestListingService $guestListings,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     phone: string,
     *     email?: string|null,
     *     delivery_method: string,
     *     delivery_address?: string|null,
     *     user?: User|null,
     *     await_mpesa?: bool
     * }  $details
     */
    public function placeFromCart(array $details): Order
    {
        $items = $this->cart->items();

        if ($items === []) {
            throw ValidationException::withMessages([
                'cart' => 'Your cart is empty.',
            ]);
        }

        $awaiting = ($details['await_mpesa'] ?? false) === true;

        return DB::transaction(function () use ($details, $items, $awaiting): Order {
            $phone = $this->phones->normalizeKenyanPhone($details['phone']);
            $subtotal = 0;
            $prepared = [];

            foreach ($items as $item) {
                /** @var Product|null $product */
                $product = Product::query()->lockForUpdate()->find($item['product_id']);

                if ($product === null) {
                    continue;
                }

                $qty = (int) $item['qty'];

                $lineTotal = $product->price * $qty;
                $subtotal += $lineTotal;
                $prepared[] = [
                    'product' => $product,
                    'qty' => $qty,
                    'line_total' => $lineTotal,
                ];
            }

            if ($prepared === []) {
                throw ValidationException::withMessages([
                    'cart' => 'Your cart is empty.',
                ]);
            }

            $order = Order::query()->create([
                'reference' => $this->generateReference(),
                'user_id' => ($details['user'] ?? null)?->id,
                'customer_name' => $details['name'],
                'customer_phone' => $phone,
                'customer_email' => $details['email'] ?? null,
                'delivery_method' => $details['delivery_method'],
                'delivery_address' => $details['delivery_method'] === Order::DELIVERY_COLLECTION
                    ? null
                    : ($details['delivery_address'] ?? null),
                'subtotal' => $subtotal,
                'delivery_fee' => 0,
                'total' => $subtotal,
                'status' => $awaiting ? Order::STATUS_AWAITING_PAYMENT : Order::STATUS_PAID,
                'payment_provider' => $awaiting ? 'mpesa_daraja_stk_push' : 'mpesa_demo',
                'payment_reference' => $awaiting ? null : 'MPESA'.random_int(100000, 999999),
                'paid_at' => $awaiting ? null : now(),
            ]);

            foreach ($prepared as $row) {
                /** @var Product $product */
                $product = $row['product'];
                $commission = 0;
                $payout = 0;
                $payoutStatus = OrderItem::PAYOUT_NONE;
                $sellerId = null;

                if ($product->isGuestListing() && $product->seller_user_id !== null) {
                    $split = $this->guestListings->splitAmounts($row['line_total']);
                    $commission = $split['commission'];
                    $payout = $split['payout'];
                    $payoutStatus = $awaiting ? OrderItem::PAYOUT_NONE : OrderItem::PAYOUT_PENDING;
                    $sellerId = $product->seller_user_id;
                }

                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'seller_user_id' => $sellerId,
                    'product_name' => $product->name,
                    'product_slug' => $product->slug,
                    'category' => $product->category,
                    'unit_price' => $product->price,
                    'quantity' => $row['qty'],
                    'line_total' => $row['line_total'],
                    'commission_amount' => $commission,
                    'seller_payout_amount' => $payout,
                    'seller_payout_status' => $payoutStatus,
                    'is_returnable' => $product->is_returnable,
                ]);

                if ($awaiting) {
                    $product->increment('reserved_stock', $row['qty']);
                } else {
                    $product->decrement('stock', $row['qty']);
                }
            }

            if ($awaiting) {
                PaymentAttempt::query()->create([
                    'order_id' => $order->id,
                    'provider' => 'mpesa_daraja_stk_push',
                    'reference' => $this->generatePaymentReference(),
                    'amount' => $subtotal,
                    'status' => 'initiated',
                    'request_payload' => [
                        'purpose' => 'shop_checkout',
                        'phone' => $phone,
                        'stock_held' => true,
                    ],
                ]);

                return $order->load('items');
            }

            $this->cart->clear();

            $this->notifications->notifyOrderPlaced($order);

            return $order->load('items');
        });
    }

    /**
     * @param  array<string, mixed>|null  $responsePayload
     */
    public function confirmShopPayment(PaymentAttempt $attempt, ?array $responsePayload = null): Order
    {
        return DB::transaction(function () use ($attempt, $responsePayload): Order {
            $locked = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ($locked->order_id === null) {
                throw ValidationException::withMessages([
                    'payment' => 'This payment is not a shop order.',
                ]);
            }

            $order = Order::query()->lockForUpdate()->with('items')->findOrFail($locked->order_id);

            if ($locked->status === 'succeeded' && $order->status === Order::STATUS_PAID) {
                return $order->fresh('items');
            }

            if (! in_array($locked->status, ['initiated', 'failed'], true)) {
                return $order->fresh('items');
            }

            $payload = $locked->request_payload ?? [];
            $held = ($payload['stock_held'] ?? false) === true;
            $payload['stock_held'] = false;

            if ($held) {
                $this->consumeReservedStock($order);
            } else {
                $this->takeStock($order);
            }

            $locked->update([
                'status' => 'succeeded',
                'failure_reason' => null,
                'request_payload' => $payload,
                'response_payload' => $responsePayload ?? $locked->response_payload,
            ]);

            foreach ($order->items as $item) {
                if ($item->seller_user_id !== null && $item->seller_payout_amount > 0 && $item->seller_payout_status === OrderItem::PAYOUT_NONE) {
                    $item->update(['seller_payout_status' => OrderItem::PAYOUT_PENDING]);
                }
            }

            $order->update([
                'status' => Order::STATUS_PAID,
                'payment_provider' => 'mpesa_daraja_stk_push',
                'payment_reference' => $locked->mpesa_receipt ?: $locked->reference,
                'paid_at' => $order->paid_at ?? now(),
                'cancelled_at' => null,
                'cancel_reason' => null,
            ]);

            $fresh = $order->fresh('items');

            AuditLogger::log('orders', 'shop payment confirmed', 'shop_payment', $fresh, [
                'reference' => $fresh->reference,
                'amount' => $fresh->total,
            ]);

            $this->notifications->notifyOrderPlaced($fresh);

            return $fresh;
        });
    }

    /**
     * @param  array<string, mixed>|null  $responsePayload
     */
    public function markShopPaymentFailed(PaymentAttempt $attempt, string $message, ?array $responsePayload = null): Order
    {
        return DB::transaction(function () use ($attempt, $message, $responsePayload): Order {
            $locked = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ($locked->order_id === null) {
                throw ValidationException::withMessages([
                    'payment' => 'This payment is not a shop order.',
                ]);
            }

            $order = Order::query()->lockForUpdate()->with('items')->findOrFail($locked->order_id);

            if (in_array($locked->status, ['succeeded', 'failed'], true)) {
                return $order->fresh('items');
            }

            $payload = $locked->request_payload ?? [];

            if (($payload['stock_held'] ?? false) === true) {
                $this->releaseReservedStock($order);
                $payload['stock_held'] = false;
            }

            $locked->update([
                'status' => 'failed',
                'failure_reason' => $message,
                'request_payload' => $payload,
                'response_payload' => $responsePayload,
            ]);

            if ($order->status === Order::STATUS_AWAITING_PAYMENT) {
                $order->update([
                    'status' => Order::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                    'cancel_reason' => $message,
                ]);
            }

            return $order->fresh('items');
        });
    }

    public function cancelBeforeDispatch(Order $order, string $reason = 'Customer cancelled before dispatch'): Order
    {
        if (! $order->canCancelBeforeDispatch()) {
            throw ValidationException::withMessages([
                'order' => 'This order can no longer be cancelled. After dispatch, return rights in the Refund Policy apply.',
            ]);
        }

        return DB::transaction(function () use ($order, $reason): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! $order->canCancelBeforeDispatch()) {
                throw ValidationException::withMessages([
                    'order' => 'This order can no longer be cancelled.',
                ]);
            }

            foreach ($order->items as $item) {
                if ($item->product_id) {
                    Product::query()->whereKey($item->product_id)->increment('stock', $item->quantity);
                }
            }

            $order->update([
                'status' => Order::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            $fresh = $order->fresh('items');
            $this->logistics->syncFromOrder($fresh);
            $this->notifications->notifyOrderCancelled($fresh);

            return $fresh;
        });
    }

    public function markProcessing(Order $order): Order
    {
        return $this->transition($order, function (Order $locked): void {
            if (! $locked->canMarkProcessing()) {
                throw ValidationException::withMessages([
                    'order' => 'Only paid orders can be marked as processing.',
                ]);
            }

            $locked->update(['status' => Order::STATUS_PROCESSING]);
        });
    }

    public function markDispatched(Order $order): Order
    {
        return $this->transition($order, function (Order $locked): void {
            if (! $locked->canMarkDispatched()) {
                throw ValidationException::withMessages([
                    'order' => 'This order cannot be marked as dispatched.',
                ]);
            }

            $locked->update([
                'status' => Order::STATUS_DISPATCHED,
                'dispatched_at' => $locked->dispatched_at ?? now(),
            ]);
        }, notify: 'dispatched');
    }

    public function markDelivered(Order $order): Order
    {
        return $this->transition($order, function (Order $locked): void {
            if (! $locked->canMarkDelivered()) {
                throw ValidationException::withMessages([
                    'order' => 'Mark the order dispatched before marking it delivered.',
                ]);
            }

            $locked->update([
                'status' => Order::STATUS_DELIVERED,
                'delivered_at' => now(),
            ]);
        }, notify: 'delivered');
    }

    /**
     * @param  callable(Order): void  $callback
     */
    private function transition(Order $order, callable $callback, ?string $notify = null): Order
    {
        return DB::transaction(function () use ($order, $callback, $notify): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            $callback($locked);

            $fresh = $locked->fresh('items');

            AuditLogger::log('orders', 'order status updated', 'status_updated', $fresh, [
                'reference' => $fresh->reference,
                'status' => $fresh->status,
            ]);

            $this->logistics->syncFromOrder($fresh);

            if ($notify === 'dispatched') {
                $this->notifications->notifyOrderDispatched($fresh);
            }

            if ($notify === 'delivered') {
                $this->notifications->notifyOrderDelivered($fresh);
            }

            return $fresh;
        });
    }

    public function findByReference(string $reference): ?Order
    {
        return Order::query()
            ->with('items')
            ->where('reference', strtoupper(trim($reference)))
            ->first();
    }

    private function generateReference(): string
    {
        do {
            $reference = 'ORD'.now()->format('ymd').strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        } while (Order::query()->where('reference', $reference)->exists());

        return $reference;
    }

    private function generatePaymentReference(): string
    {
        do {
            $reference = 'SHP-'.str()->upper(str()->random(10));
        } while (PaymentAttempt::query()->where('reference', $reference)->exists());

        return $reference;
    }

    private function consumeReservedStock(Order $order): void
    {
        foreach ($order->items as $item) {
            if ($item->product_id === null) {
                continue;
            }

            $product = Product::query()->lockForUpdate()->find($item->product_id);

            if ($product === null) {
                continue;
            }

            $product->decrement('stock', $item->quantity);
            $release = min($item->quantity, $product->reserved_stock);

            if ($release > 0) {
                $product->decrement('reserved_stock', $release);
            }
        }
    }

    private function takeStock(Order $order): void
    {
        foreach ($order->items as $item) {
            if ($item->product_id === null) {
                continue;
            }

            $product = Product::query()->lockForUpdate()->find($item->product_id);

            if ($product === null) {
                continue;
            }

            $product->decrement('stock', $item->quantity);
        }
    }

    private function releaseReservedStock(Order $order): void
    {
        foreach ($order->items as $item) {
            if ($item->product_id === null) {
                continue;
            }

            $product = Product::query()->lockForUpdate()->find($item->product_id);

            if ($product === null) {
                continue;
            }

            $release = min($item->quantity, $product->reserved_stock);

            if ($release > 0) {
                $product->decrement('reserved_stock', $release);
            }
        }
    }
}
