<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public const UPCOUNTRY_DELIVERY_FEE = 350;

    public function __construct(
        private readonly CartService $cart,
        private readonly AuctionBidService $phones,
        private readonly NotificationPlaceholderService $notifications,
    ) {}

    public function deliveryFee(string $method): int
    {
        return match ($method) {
            Order::DELIVERY_UPCOUNTRY => self::UPCOUNTRY_DELIVERY_FEE,
            default => 0,
        };
    }

    /**
     * @param  array{
     *     name: string,
     *     phone: string,
     *     email?: string|null,
     *     delivery_method: string,
     *     delivery_address?: string|null,
     *     user?: User|null
     * }  $details
     */
    public function placeFromCart(array $details): Order
    {
        $lines = $this->cart->lines();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => 'Your cart is empty.',
            ]);
        }

        return DB::transaction(function () use ($details, $lines): Order {
            $phone = $this->phones->normalizeKenyanPhone($details['phone']);
            $subtotal = 0;
            $prepared = [];

            foreach ($lines as $line) {
                /** @var Product $product */
                $product = Product::query()->lockForUpdate()->findOrFail($line['product']->id);
                $qty = (int) $line['qty'];

                if ($product->stock < $qty) {
                    throw ValidationException::withMessages([
                        'cart' => "{$product->name} only has {$product->stock} left in stock.",
                    ]);
                }

                if ($product->availableStock() < $qty) {
                    throw ValidationException::withMessages([
                        'cart' => "{$product->name} only has {$product->availableStock()} available (some units are reserved for live sales).",
                    ]);
                }

                $lineTotal = $product->price * $qty;
                $subtotal += $lineTotal;
                $prepared[] = [
                    'product' => $product,
                    'qty' => $qty,
                    'line_total' => $lineTotal,
                ];
            }

            $deliveryFee = $this->deliveryFee($details['delivery_method']);
            $order = Order::query()->create([
                'reference' => $this->generateReference(),
                'user_id' => $details['user']?->id,
                'customer_name' => $details['name'],
                'customer_phone' => $phone,
                'customer_email' => $details['email'] ?? null,
                'delivery_method' => $details['delivery_method'],
                'delivery_address' => $details['delivery_method'] === Order::DELIVERY_COLLECTION
                    ? null
                    : ($details['delivery_address'] ?? null),
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'total' => $subtotal + $deliveryFee,
                'status' => Order::STATUS_PAID,
                'payment_provider' => 'mpesa_demo',
                'payment_reference' => 'MPESA'.random_int(100000, 999999),
                'paid_at' => now(),
            ]);

            foreach ($prepared as $row) {
                /** @var Product $product */
                $product = $row['product'];

                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_slug' => $product->slug,
                    'category' => $product->category,
                    'unit_price' => $product->price,
                    'quantity' => $row['qty'],
                    'line_total' => $row['line_total'],
                    'is_returnable' => $product->is_returnable,
                ]);

                $product->decrement('stock', $row['qty']);
            }

            $this->cart->clear();

            $this->notifications->notifyOrderPlaced($order);

            return $order->load('items');
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

            $this->notifications->notifyOrderCancelled($order);

            return $order->fresh('items');
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
}
