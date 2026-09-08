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
        ];

        $method = (string) ($data['method'] ?? '');
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
