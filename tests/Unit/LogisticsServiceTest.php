<?php

use App\Models\AuctionResult;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\LogisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('logistics rejects shipment status that conflicts with a redeemed sale', function () {
    $result = AuctionResult::factory()->create([
        'fulfilment_status' => AuctionResult::FULFILMENT_REDEEMED,
        'buyer_display' => 'Grace W.',
    ]);

    $shipment = app(LogisticsService::class)->createShipmentForAuctionResult($result, [
        'method' => Shipment::METHOD_WESTLANDS_COLLECTION,
        'status' => Shipment::STATUS_REDEEMED,
        'recipient_name' => 'Grace W.',
    ]);

    expect(fn () => app(LogisticsService::class)->updateShipment($shipment, [
        'method' => $shipment->method,
        'status' => Shipment::STATUS_READY,
        'recipient_name' => $shipment->recipient_name,
        'recipient_phone' => $shipment->recipient_phone,
        'address' => $shipment->address,
        'fee' => $shipment->fee,
        'notes' => $shipment->notes,
    ]))->toThrow(ValidationException::class);

    expect($shipment->fresh()->status)->toBe(Shipment::STATUS_REDEEMED);
});

test('logistics rejects shipment status that conflicts with a delivered order', function () {
    $order = Order::factory()->delivered()->create([
        'delivery_method' => Order::DELIVERY_NAIROBI,
    ]);

    $shipment = app(LogisticsService::class)->createShipmentForOrder($order, [
        'method' => Shipment::METHOD_NAIROBI_DELIVERY,
        'status' => Shipment::STATUS_DELIVERED,
        'recipient_name' => $order->customer_name,
    ]);

    expect(fn () => app(LogisticsService::class)->updateShipment($shipment, [
        'method' => $shipment->method,
        'status' => Shipment::STATUS_PENDING,
        'recipient_name' => $shipment->recipient_name,
        'recipient_phone' => $shipment->recipient_phone,
        'address' => $shipment->address,
        'fee' => $shipment->fee,
        'notes' => $shipment->notes,
    ]))->toThrow(ValidationException::class);

    expect($shipment->fresh()->status)->toBe(Shipment::STATUS_DELIVERED);
});

test('logistics allows fee updates when status still matches the order', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_PROCESSING,
        'delivery_method' => Order::DELIVERY_NAIROBI,
    ]);

    $shipment = app(LogisticsService::class)->createShipmentForOrder($order, [
        'method' => Shipment::METHOD_NAIROBI_DELIVERY,
        'status' => Shipment::STATUS_PENDING,
        'recipient_name' => $order->customer_name,
        'fee' => 0,
    ]);

    $updated = app(LogisticsService::class)->updateShipment($shipment, [
        'method' => $shipment->method,
        'status' => Shipment::STATUS_PENDING,
        'recipient_name' => $shipment->recipient_name,
        'recipient_phone' => $shipment->recipient_phone,
        'address' => $shipment->address,
        'fee' => 350,
        'notes' => 'Courier quote',
    ]);

    expect($updated->fee)->toBe(350)
        ->and($updated->status)->toBe(Shipment::STATUS_PENDING)
        ->and($updated->notes)->toBe('Courier quote');
});
