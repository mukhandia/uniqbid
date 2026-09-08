<?php

use App\Models\AuctionResult;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\LogisticsService;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Layout('layouts.app')]
#[Title('Admin: Logistics')]
class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $status = '';

    public ?int $editingId = null;

    public string $shippableType = 'order';

    public string $shippableReference = '';

    public string $method = Shipment::METHOD_NAIROBI_DELIVERY;

    public string $shipmentStatus = Shipment::STATUS_PENDING;

    public string $recipientName = '';

    public string $recipientPhone = '';

    public string $address = '';

    public int $fee = 0;

    public string $notes = '';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->editingId = 0;
    }

    public function edit(int $shipmentId): void
    {
        $shipment = Shipment::query()->with('shippable')->findOrFail($shipmentId);
        $this->editingId = $shipment->id;
        $this->shippableType = $shipment->shippable_type === AuctionResult::class ? 'auction_result' : 'order';
        $this->shippableReference = match (true) {
            $shipment->shippable instanceof Order => (string) $shipment->shippable->reference,
            $shipment->shippable instanceof AuctionResult => (string) $shipment->shippable->slug,
            default => (string) $shipment->shippable_id,
        };
        $this->method = $shipment->method;
        $this->shipmentStatus = $shipment->status;
        $this->recipientName = (string) $shipment->recipient_name;
        $this->recipientPhone = (string) $shipment->recipient_phone;
        $this->address = (string) $shipment->address;
        $this->fee = (int) $shipment->fee;
        $this->notes = (string) $shipment->notes;
    }

    public function save(LogisticsService $logistics): void
    {
        $validated = $this->validate([
            'shippableType' => ['required', 'in:order,auction_result'],
            'shippableReference' => ['required', 'string', 'max:120'],
            'method' => ['required', 'in:'.implode(',', [
                Shipment::METHOD_NAIROBI_DELIVERY,
                Shipment::METHOD_WESTLANDS_COLLECTION,
                Shipment::METHOD_UPCOUNTRY,
            ])],
            'shipmentStatus' => ['required', 'in:'.implode(',', [
                Shipment::STATUS_PENDING,
                Shipment::STATUS_READY,
                Shipment::STATUS_DISPATCHED,
                Shipment::STATUS_DELIVERED,
                Shipment::STATUS_COLLECTED,
                Shipment::STATUS_CANCELLED,
            ])],
            'recipientName' => ['nullable', 'string', 'max:120'],
            'recipientPhone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:1000'],
            'fee' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $payload = [
            'method' => $validated['method'],
            'status' => $validated['shipmentStatus'],
            'recipient_name' => $validated['recipientName'] ?: null,
            'recipient_phone' => $validated['recipientPhone'] ?: null,
            'address' => $validated['address'] ?: null,
            'fee' => (int) $validated['fee'],
            'notes' => $validated['notes'] ?: null,
        ];

        try {
            if ($this->editingId) {
                $shipment = Shipment::query()->findOrFail($this->editingId);
                $logistics->updateShipment($shipment, $payload);
                Flux::toast(variant: 'success', text: 'Shipment updated.');
            } else {
                $reference = trim($validated['shippableReference']);

                if ($validated['shippableType'] === 'order') {
                    $order = Order::query()
                        ->where(function ($query) use ($reference): void {
                            $query->where('reference', strtoupper($reference));

                            if (ctype_digit($reference)) {
                                $query->orWhere('id', (int) $reference);
                            }
                        })
                        ->first();

                    if ($order === null) {
                        $this->addError('shippableReference', 'No order found for that reference or ID.');

                        return;
                    }

                    $logistics->createShipmentForOrder($order, $payload);
                } else {
                    $result = AuctionResult::query()
                        ->where(function ($query) use ($reference): void {
                            $query->where('slug', $reference);

                            if (ctype_digit($reference)) {
                                $query->orWhere('id', (int) $reference);
                            }
                        })
                        ->first();

                    if ($result === null) {
                        $this->addError('shippableReference', 'No auction result found for that slug or ID.');

                        return;
                    }

                    $logistics->createShipmentForAuctionResult($result, $payload);
                }

                Flux::toast(variant: 'success', text: 'Shipment created.');
            }
        } catch (ValidationException $exception) {
            Flux::toast(variant: 'danger', text: collect($exception->errors())->flatten()->first() ?? 'Unable to save shipment.');

            throw $exception;
        }

        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->shippableType = 'order';
        $this->shippableReference = '';
        $this->method = Shipment::METHOD_NAIROBI_DELIVERY;
        $this->shipmentStatus = Shipment::STATUS_PENDING;
        $this->recipientName = '';
        $this->recipientPhone = '';
        $this->address = '';
        $this->fee = 0;
        $this->notes = '';
        $this->resetValidation();
    }

    public function with(): array
    {
        $query = Shipment::query()->with('shippable')->latest();

        if ($this->status !== '') {
            $query->where('status', $this->status);
        }

        return [
            'shipments' => $query->paginate(20),
            'methods' => [
                Shipment::METHOD_NAIROBI_DELIVERY => 'Nairobi delivery',
                Shipment::METHOD_WESTLANDS_COLLECTION => 'Westlands collection',
                Shipment::METHOD_UPCOUNTRY => 'Upcountry delivery',
            ],
            'statuses' => [
                Shipment::STATUS_PENDING => 'Pending',
                Shipment::STATUS_READY => 'Ready for collection',
                Shipment::STATUS_DISPATCHED => 'Dispatched',
                Shipment::STATUS_DELIVERED => 'Delivered',
                Shipment::STATUS_COLLECTED => 'Collected',
                Shipment::STATUS_CANCELLED => 'Cancelled',
            ],
        ];
    }
}; ?>

@php
    $statusColor = fn (string $status): string => match ($status) {
        Shipment::STATUS_PENDING => 'amber',
        Shipment::STATUS_READY => 'sky',
        Shipment::STATUS_DISPATCHED => 'blue',
        Shipment::STATUS_DELIVERED, Shipment::STATUS_COLLECTED => 'green',
        Shipment::STATUS_CANCELLED => 'red',
        default => 'zinc',
    };
@endphp

<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">Logistics</flux:heading>
            <flux:text class="mt-1">Shipments for shop orders and successful auction buys.</flux:text>
        </div>
        <flux:button type="button" wire:click="openCreate" class="bg-accent! text-accent-foreground!">New shipment</flux:button>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="sm:w-56">
            <flux:field>
                <flux:label>Status</flux:label>
                <flux:select wire:model.live="status">
                    <flux:select.option value="">All statuses</flux:select.option>
                    @foreach ($statuses as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>
        </div>
    </div>

    @if ($editingId !== null)
        <form wire:submit="save" class="space-y-4 rounded-xl border border-zinc-200 bg-white p-4 sm:p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg">{{ $editingId ? 'Update shipment' : 'Create shipment' }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2">
                @unless ($editingId)
                    <flux:field>
                        <flux:label>Linked to</flux:label>
                        <flux:select wire:model="shippableType">
                            <flux:select.option value="order">Shop order</flux:select.option>
                            <flux:select.option value="auction_result">Auction result</flux:select.option>
                        </flux:select>
                        <flux:error name="shippableType" />
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ $shippableType === 'order' ? 'Order reference or ID' : 'Result slug or ID' }}</flux:label>
                        <flux:input wire:model="shippableReference" />
                        <flux:error name="shippableReference" />
                    </flux:field>
                @endunless
                <flux:field>
                    <flux:label>Method</flux:label>
                    <flux:select wire:model="method">
                        @foreach ($methods as $value => $label)
                            <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="method" />
                </flux:field>
                <flux:field>
                    <flux:label>Status</flux:label>
                    <flux:select wire:model="shipmentStatus">
                        @foreach ($statuses as $value => $label)
                            <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="shipmentStatus" />
                </flux:field>
                <flux:field>
                    <flux:label>Recipient name</flux:label>
                    <flux:input wire:model="recipientName" />
                    <flux:error name="recipientName" />
                </flux:field>
                <flux:field>
                    <flux:label>Recipient phone</flux:label>
                    <flux:input wire:model="recipientPhone" />
                    <flux:error name="recipientPhone" />
                </flux:field>
                <flux:field class="sm:col-span-2">
                    <flux:label>Address</flux:label>
                    <flux:textarea wire:model="address" rows="2" />
                    <flux:error name="address" />
                </flux:field>
                <flux:field>
                    <flux:label>Fee (KES)</flux:label>
                    <flux:input type="number" wire:model="fee" min="0" />
                    <flux:error name="fee" />
                </flux:field>
                <flux:field class="sm:col-span-2">
                    <flux:label>Notes</flux:label>
                    <flux:textarea wire:model="notes" rows="2" />
                    <flux:error name="notes" />
                </flux:field>
            </div>
            <div class="flex gap-2">
                <flux:button type="submit" class="bg-accent! text-accent-foreground!">Save shipment</flux:button>
                <flux:button type="button" variant="ghost" wire:click="resetForm">Cancel</flux:button>
            </div>
        </form>
    @endif

    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
            <thead class="bg-zinc-50 text-left dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 font-medium">Shipment</th>
                    <th class="px-4 py-3 font-medium">Linked to</th>
                    <th class="px-4 py-3 font-medium">Method</th>
                    <th class="px-4 py-3 font-medium">Recipient</th>
                    <th class="px-4 py-3 font-medium">Fee</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($shipments as $shipment)
                    <tr wire:key="shipment-{{ $shipment->id }}">
                        <td class="px-4 py-3">
                            <div class="font-medium">#{{ $shipment->id }}</div>
                            <div class="text-xs text-zinc-500">{{ $shipment->created_at?->timezone('Africa/Nairobi')->format('d M Y H:i') }} EAT</div>
                        </td>
                        <td class="px-4 py-3 text-xs">
                            @if ($shipment->shippable instanceof Order)
                                Order {{ $shipment->shippable->reference }}
                            @elseif ($shipment->shippable instanceof AuctionResult)
                                Result {{ $shipment->shippable->name }}
                            @else
                                {{ class_basename((string) $shipment->shippable_type) }} #{{ $shipment->shippable_id }}
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $shipment->methodLabel() }}</td>
                        <td class="px-4 py-3 text-xs">
                            <div>{{ $shipment->recipient_name ?: '—' }}</div>
                            <div class="text-zinc-500">{{ $shipment->recipient_phone }}</div>
                        </td>
                        <td class="px-4 py-3">{{ Money::kes($shipment->fee) }}</td>
                        <td class="px-4 py-3">
                            <flux:badge size="sm" :color="$statusColor($shipment->status)">
                                {{ $shipment->statusLabel() }}
                            </flux:badge>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <flux:button size="sm" variant="ghost" wire:click="edit({{ $shipment->id }})">Edit</flux:button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-zinc-500">No shipments yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $shipments->links() }}</div>
</div>
