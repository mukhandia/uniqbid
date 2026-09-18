<?php

namespace App\Services;

use App\Models\AuctionResult;
use App\Models\Order;
use App\Models\Shipment;
use App\Support\AuditLogger;
use Illuminate\Validation\ValidationException;

class LogisticsService
{
    /**
     * @param  array{
     *     method: string,
     *     status?: string,
     *     recipient_name?: string|null,
     *     recipient_phone?: string|null,
     *     address?: string|null,
     *     fee?: int,
     *     notes?: string|null
     * }  $data
     */
    public function createShipmentForOrder(Order $order, array $data): Shipment
    {
        return $this->createShipment($order, $data);
    }

    /**
     * @param  array{
     *     method: string,
     *     status?: string,
     *     recipient_name?: string|null,
     *     recipient_phone?: string|null,
     *     address?: string|null,
     *     fee?: int,
     *     notes?: string|null
     * }  $data
     */
    public function createShipmentForAuctionResult(AuctionResult $result, array $data): Shipment
    {
        return $this->createShipment($result, $data);
    }

    /**
     * Keep logistics in sync with shop order fulfilment (order is source of truth).
     */
    public function syncFromOrder(Order $order): ?Shipment
    {
        if ($order->status === Order::STATUS_PAID) {
            return null;
        }

        $active = $this->activeShipmentFor($order);

        if ($order->status === Order::STATUS_CANCELLED) {
            if ($active === null) {
                return null;
            }

            return $this->updateShipment($active, [
                'method' => $active->method,
                'status' => Shipment::STATUS_CANCELLED,
                'recipient_name' => $active->recipient_name,
                'recipient_phone' => $active->recipient_phone,
                'address' => $active->address,
                'fee' => $active->fee,
                'notes' => $active->notes,
            ]);
        }

        $status = $this->shipmentStatusFromOrder($order);
        $method = $this->shipmentMethodFromOrder($order);

        if ($active === null) {
            $order->loadMissing(['items.seller']);

            return $this->createShipmentForOrder($order, [
                'method' => $method,
                'status' => $status,
                'recipient_name' => $order->customer_name,
                'recipient_phone' => $order->customer_phone,
                'address' => $order->delivery_address,
                'fee' => (int) $order->delivery_fee,
                'notes' => $this->notesForOrder($order),
            ]);
        }

        return $this->updateShipment($active, [
            'method' => $active->method,
            'status' => $status,
            'recipient_name' => $active->recipient_name ?: $order->customer_name,
            'recipient_phone' => $active->recipient_phone ?: $order->customer_phone,
            'address' => $active->address ?: $order->delivery_address,
            'fee' => $active->fee,
            'notes' => $active->notes,
        ]);
    }

    /**
     * Keep logistics in sync with auction successful-buy fulfilment.
     * Redeem marks an existing shipment Redeemed (or records a redeemed shipment).
     */
    public function syncFromAuctionResult(AuctionResult $result): ?Shipment
    {
        if ($result->fulfilment_status === AuctionResult::FULFILMENT_REDEEMED) {
            return $this->markAuctionResultRedeemed($result);
        }

        if ($result->fulfilment_status === AuctionResult::FULFILMENT_PENDING) {
            return null;
        }

        $status = $this->shipmentStatusFromAuctionResult($result);
        $method = $this->shipmentMethodFromAuctionResult($result);
        $active = $this->activeShipmentFor($result);

        $result->loadMissing('auctionBid.bidder');
        $bidder = $result->auctionBid?->bidder;

        $recipientName = $bidder !== null ? $bidder->name : $result->buyer_display;
        $recipientPhone = $bidder?->phone;

        if ($active === null) {
            return $this->createShipmentForAuctionResult($result, [
                'method' => $method,
                'status' => $status,
                'recipient_name' => $recipientName,
                'recipient_phone' => $recipientPhone,
                'address' => null,
                'fee' => 0,
                'notes' => 'Auto-created from successful buy '.$result->slug,
            ]);
        }

        return $this->updateShipment($active, [
            'method' => $active->method,
            'status' => $status,
            'recipient_name' => $active->recipient_name ?: $recipientName,
            'recipient_phone' => $active->recipient_phone ?: $recipientPhone,
            'address' => $active->address,
            'fee' => $active->fee,
            'notes' => $active->notes,
        ]);
    }

    private function markAuctionResultRedeemed(AuctionResult $result): Shipment
    {
        $result->loadMissing('auctionBid.bidder');
        $bidder = $result->auctionBid?->bidder;
        $recipientName = $bidder !== null ? $bidder->name : $result->buyer_display;
        $recipientPhone = $bidder?->phone;

        $shipment = Shipment::query()
            ->where('shippable_type', $result->getMorphClass())
            ->where('shippable_id', $result->getKey())
            ->whereNotIn('status', [Shipment::STATUS_CANCELLED])
            ->latest('id')
            ->first();

        if ($shipment === null) {
            return $this->createShipmentForAuctionResult($result, [
                'method' => Shipment::METHOD_WESTLANDS_COLLECTION,
                'status' => Shipment::STATUS_REDEEMED,
                'recipient_name' => $recipientName,
                'recipient_phone' => $recipientPhone,
                'address' => null,
                'fee' => 0,
                'notes' => 'Redeemed successful buy '.$result->slug,
            ]);
        }

        return $this->updateShipment($shipment, [
            'method' => $shipment->method,
            'status' => Shipment::STATUS_REDEEMED,
            'recipient_name' => $shipment->recipient_name ?: $recipientName,
            'recipient_phone' => $shipment->recipient_phone ?: $recipientPhone,
            'address' => $shipment->address,
            'fee' => $shipment->fee,
            'notes' => $shipment->notes,
        ]);
    }

    /**
     * @param  array{
     *     method: string,
     *     status?: string,
     *     recipient_name?: string|null,
     *     recipient_phone?: string|null,
     *     address?: string|null,
     *     fee?: int,
     *     notes?: string|null
     * }  $data
     */
    public function updateShipment(Shipment $shipment, array $data): Shipment
    {
        $payload = $this->validatedPayload($data);
        $shipment->loadMissing('shippable');

        if ($shipment->shippable instanceof Order || $shipment->shippable instanceof AuctionResult) {
            $this->assertStatusCompatibleWithShippable($shipment->shippable, $payload['status']);
        }

        $shipment->fill($payload);
        $this->applyStatusTimestamps($shipment, $payload['status']);
        $shipment->save();

        $fresh = $shipment->fresh('shippable');

        AuditLogger::log('logistics', 'shipment updated', 'updated', $fresh, [
            'status' => $fresh->status,
            'method' => $fresh->method,
        ]);

        return $fresh;
    }

    /**
     * Statuses that do not conflict with the linked order or successful buy.
     *
     * @return list<string>
     */
    public function allowedStatusesForShippable(Order|AuctionResult $shippable): array
    {
        if ($shippable instanceof Order) {
            if ($shippable->status === Order::STATUS_PAID) {
                return [Shipment::STATUS_PENDING];
            }

            return [$this->shipmentStatusFromOrder($shippable)];
        }

        if ($shippable->fulfilment_status === AuctionResult::FULFILMENT_REDEEMED) {
            return [Shipment::STATUS_REDEEMED];
        }

        if ($shippable->fulfilment_status === AuctionResult::FULFILMENT_PENDING) {
            return [Shipment::STATUS_PENDING];
        }

        return [$this->shipmentStatusFromAuctionResult($shippable)];
    }

    public function assertStatusCompatibleWithShippable(Order|AuctionResult $shippable, string $status): void
    {
        $allowed = $this->allowedStatusesForShippable($shippable);

        if (in_array($status, $allowed, true)) {
            return;
        }

        $labels = array_map(
            fn (string $value): string => (new Shipment(['status' => $value]))->statusLabel(),
            $allowed,
        );

        $source = $shippable instanceof Order
            ? 'order ('.$shippable->statusLabel().')'
            : 'sale ('.$shippable->fulfilmentLabel().')';

        throw ValidationException::withMessages([
            'status' => 'Shipment status conflicts with the linked '.$source.'. Allowed: '.implode(', ', $labels).'. Update fulfilment on the order or sale instead.',
            'shipmentStatus' => 'Shipment status conflicts with the linked '.$source.'. Allowed: '.implode(', ', $labels).'. Update fulfilment on the order or sale instead.',
        ]);
    }

    public function activeShipmentFor(Order|AuctionResult $shippable): ?Shipment
    {
        return Shipment::query()
            ->where('shippable_type', $shippable->getMorphClass())
            ->where('shippable_id', $shippable->getKey())
            ->whereNotIn('status', [Shipment::STATUS_CANCELLED, Shipment::STATUS_REDEEMED])
            ->latest('id')
            ->first();
    }

    public function shipmentMethodFromOrder(Order $order): string
    {
        return match ($order->delivery_method) {
            Order::DELIVERY_COLLECTION => Shipment::METHOD_WESTLANDS_COLLECTION,
            Order::DELIVERY_UPCOUNTRY => Shipment::METHOD_UPCOUNTRY,
            default => Shipment::METHOD_NAIROBI_DELIVERY,
        };
    }

    public function shipmentStatusFromOrder(Order $order): string
    {
        return match ($order->status) {
            Order::STATUS_PROCESSING => Shipment::STATUS_PENDING,
            Order::STATUS_DISPATCHED => $order->delivery_method === Order::DELIVERY_COLLECTION
                ? Shipment::STATUS_READY
                : Shipment::STATUS_DISPATCHED,
            Order::STATUS_DELIVERED => $order->delivery_method === Order::DELIVERY_COLLECTION
                ? Shipment::STATUS_COLLECTED
                : Shipment::STATUS_DELIVERED,
            Order::STATUS_CANCELLED => Shipment::STATUS_CANCELLED,
            default => Shipment::STATUS_PENDING,
        };
    }

    public function shipmentMethodFromAuctionResult(AuctionResult $result): string
    {
        return match ($result->fulfilment_status) {
            AuctionResult::FULFILMENT_COLLECTION_READY,
            AuctionResult::FULFILMENT_COLLECTED => Shipment::METHOD_WESTLANDS_COLLECTION,
            default => Shipment::METHOD_NAIROBI_DELIVERY,
        };
    }

    public function shipmentStatusFromAuctionResult(AuctionResult $result): string
    {
        return match ($result->fulfilment_status) {
            AuctionResult::FULFILMENT_COLLECTION_READY => Shipment::STATUS_READY,
            AuctionResult::FULFILMENT_DISPATCHED => Shipment::STATUS_DISPATCHED,
            AuctionResult::FULFILMENT_COLLECTED => Shipment::STATUS_COLLECTED,
            AuctionResult::FULFILMENT_DELIVERED => Shipment::STATUS_DELIVERED,
            default => Shipment::STATUS_PENDING,
        };
    }

    /**
     * @param  array{
     *     method: string,
     *     status?: string,
     *     recipient_name?: string|null,
     *     recipient_phone?: string|null,
     *     address?: string|null,
     *     fee?: int,
     *     notes?: string|null
     * }  $data
     */
    private function createShipment(Order|AuctionResult $shippable, array $data): Shipment
    {
        $payload = $this->validatedPayload($data);
        $this->assertStatusCompatibleWithShippable($shippable, $payload['status']);

        $shipment = new Shipment($payload);
        $shipment->shippable()->associate($shippable);
        $this->applyStatusTimestamps($shipment, $payload['status']);
        $shipment->save();

        $fresh = $shipment->fresh('shippable');

        AuditLogger::log('logistics', 'shipment created', 'created', $fresh, [
            'status' => $fresh->status,
            'method' => $fresh->method,
            'shippable_type' => $fresh->shippable_type,
            'shippable_id' => $fresh->shippable_id,
        ]);

        return $fresh;
    }

    /**
     * @param  array{
     *     method: string,
     *     status?: string,
     *     recipient_name?: string|null,
     *     recipient_phone?: string|null,
     *     address?: string|null,
     *     fee?: int,
     *     notes?: string|null
     * }  $data
     * @return array{
     *     method: string,
     *     status: string,
     *     recipient_name: string|null,
     *     recipient_phone: string|null,
     *     address: string|null,
     *     fee: int,
     *     notes: string|null
     * }
     */
    private function validatedPayload(array $data): array
    {
        $methods = [
            Shipment::METHOD_NAIROBI_DELIVERY,
            Shipment::METHOD_WESTLANDS_COLLECTION,
            Shipment::METHOD_UPCOUNTRY,
        ];

        $statuses = [
            Shipment::STATUS_PENDING,
            Shipment::STATUS_READY,
            Shipment::STATUS_DISPATCHED,
            Shipment::STATUS_DELIVERED,
            Shipment::STATUS_COLLECTED,
            Shipment::STATUS_CANCELLED,
            Shipment::STATUS_REDEEMED,
        ];

        $method = (string) $data['method'];
        $status = (string) ($data['status'] ?? Shipment::STATUS_PENDING);

        if (! in_array($method, $methods, true)) {
            throw ValidationException::withMessages([
                'method' => 'Choose a valid delivery method.',
            ]);
        }

        if (! in_array($status, $statuses, true)) {
            throw ValidationException::withMessages([
                'status' => 'Choose a valid shipment status.',
            ]);
        }

        return [
            'method' => $method,
            'status' => $status,
            'recipient_name' => filled($data['recipient_name'] ?? null) ? (string) $data['recipient_name'] : null,
            'recipient_phone' => filled($data['recipient_phone'] ?? null) ? (string) $data['recipient_phone'] : null,
            'address' => filled($data['address'] ?? null) ? (string) $data['address'] : null,
            'fee' => max(0, (int) ($data['fee'] ?? 0)),
            'notes' => filled($data['notes'] ?? null) ? (string) $data['notes'] : null,
        ];
    }

    private function notesForOrder(Order $order): string
    {
        $notes = 'Auto-created from order '.$order->reference;

        $guestLines = $order->items
            ->filter(fn ($item): bool => $item->seller_user_id !== null)
            ->map(function ($item): string {
                $seller = $item->seller?->name ?? 'guest seller';

                return $item->product_name.' (seller: '.$seller.')';
            })
            ->values();

        if ($guestLines->isEmpty()) {
            return $notes;
        }

        return $notes.'. Guest listing: '.$guestLines->implode('; ');
    }

    private function applyStatusTimestamps(Shipment $shipment, string $status): void
    {
        if ($status === Shipment::STATUS_DISPATCHED && $shipment->dispatched_at === null) {
            $shipment->dispatched_at = now();
        }

        if ($status === Shipment::STATUS_DELIVERED && $shipment->delivered_at === null) {
            $shipment->delivered_at = now();
            $shipment->dispatched_at ??= now();
        }

        if ($status === Shipment::STATUS_COLLECTED && $shipment->collected_at === null) {
            $shipment->collected_at = now();
        }
    }
}
