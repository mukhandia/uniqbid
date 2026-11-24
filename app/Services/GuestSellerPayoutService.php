<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\PaymentAttempt;
use App\Support\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GuestSellerPayoutService
{
    public function __construct(private readonly AuctionBidService $bids) {}

    public function beginPayout(OrderItem $item): PaymentAttempt
    {
        return DB::transaction(function () use ($item): PaymentAttempt {
            $item = OrderItem::query()->lockForUpdate()->with('seller')->findOrFail($item->id);

            if ($item->seller_payout_status !== OrderItem::PAYOUT_PENDING) {
                throw ValidationException::withMessages([
                    'seller_payout' => 'This seller payout is not waiting to be sent.',
                ]);
            }

            $amount = (int) $item->seller_payout_amount;

            if ($amount < 10) {
                throw ValidationException::withMessages([
                    'seller_payout' => 'M-Pesa cannot send a payout below KES 10.',
                ]);
            }

            $existing = $this->openAttempt($item);

            if ($existing !== null) {
                return $existing;
            }

            $seller = $item->seller;

            if ($seller === null || blank($seller->phone)) {
                throw ValidationException::withMessages([
                    'seller_payout' => 'This seller has no M-Pesa number.',
                ]);
            }

            return PaymentAttempt::query()->create([
                'order_id' => $item->order_id,
                'provider' => 'mpesa_daraja_b2c',
                'reference' => $this->generateReference(),
                'amount' => $amount,
                'status' => 'initiated',
                'request_payload' => [
                    'purpose' => 'seller_payout',
                    'phone' => $this->bids->normalizeKenyanPhone((string) $seller->phone),
                    'order_item_id' => $item->id,
                ],
            ]);
        });
    }

    /**
     * @param  array<string, mixed>|null  $responsePayload
     */
    public function confirmPayout(PaymentAttempt $attempt, ?array $responsePayload = null): OrderItem
    {
        return DB::transaction(function () use ($attempt, $responsePayload): OrderItem {
            $locked = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $item = OrderItem::query()->lockForUpdate()->findOrFail(
                (int) ($locked->request_payload['order_item_id'] ?? 0),
            );

            if ($locked->status === 'succeeded' && $item->seller_payout_status === OrderItem::PAYOUT_PAID) {
                return $item;
            }

            if (! in_array($locked->status, ['initiated', 'failed'], true)) {
                return $item;
            }

            $locked->update([
                'status' => 'succeeded',
                'failure_reason' => null,
                'response_payload' => $responsePayload ?? $locked->response_payload,
            ]);

            if ($item->seller_payout_status !== OrderItem::PAYOUT_PAID) {
                $item->update([
                    'seller_payout_status' => OrderItem::PAYOUT_PAID,
                    'seller_payout_paid_at' => now(),
                ]);

                AuditLogger::log('orders', 'seller payout sent', 'seller_payout_paid', $item->fresh(), [
                    'order_id' => $item->order_id,
                    'seller_payout_amount' => $item->seller_payout_amount,
                    'payment_attempt_id' => $locked->id,
                ]);
            }

            return $item->fresh();
        });
    }

    /**
     * @param  array<string, mixed>|null  $responsePayload
     */
    public function markPayoutFailed(PaymentAttempt $attempt, string $message, ?array $responsePayload = null): void
    {
        DB::transaction(function () use ($attempt, $message, $responsePayload): void {
            $locked = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if (in_array($locked->status, ['succeeded', 'failed'], true)) {
                return;
            }

            $locked->update([
                'status' => 'failed',
                'failure_reason' => str($message)->limit(250, '')->toString(),
                'response_payload' => $responsePayload,
            ]);

            $itemId = (int) ($locked->request_payload['order_item_id'] ?? 0);
            $item = $itemId > 0 ? OrderItem::query()->find($itemId) : null;

            AuditLogger::log('orders', 'seller payout not sent', 'seller_payout_failed', $item, [
                'order_id' => $item?->order_id,
                'payment_attempt_id' => $locked->id,
                'reason' => $locked->failure_reason,
            ]);
        });
    }

    /**
     * @param  Collection<int, OrderItem>  $items
     * @return Collection<int, PaymentAttempt>
     */
    public function latestAttempts(Collection $items): Collection
    {
        $ids = $items->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        if ($ids === []) {
            return collect();
        }

        return PaymentAttempt::query()
            ->where('provider', 'mpesa_daraja_b2c')
            ->where('request_payload->purpose', 'seller_payout')
            ->whereIn('request_payload->order_item_id', $ids)
            ->latest('id')
            ->get()
            ->unique(fn (PaymentAttempt $attempt): int => (int) ($attempt->request_payload['order_item_id'] ?? 0))
            ->keyBy(fn (PaymentAttempt $attempt): int => (int) ($attempt->request_payload['order_item_id'] ?? 0));
    }

    private function openAttempt(OrderItem $item): ?PaymentAttempt
    {
        return PaymentAttempt::query()
            ->where('provider', 'mpesa_daraja_b2c')
            ->where('request_payload->purpose', 'seller_payout')
            ->where('request_payload->order_item_id', $item->id)
            ->where('status', 'initiated')
            ->latest('id')
            ->first();
    }

    private function generateReference(): string
    {
        do {
            $reference = 'SEL'.str()->upper(str()->random(10));
        } while (PaymentAttempt::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
