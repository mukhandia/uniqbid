<?php

namespace App\Services;

use App\Models\AuctionBid;
use App\Models\PaymentAttempt;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ParticipationRefundService
{
    public function __construct(private readonly AuctionBidService $bids) {}

    public function beginRefund(AuctionBid $bid): PaymentAttempt
    {
        return DB::transaction(function () use ($bid): PaymentAttempt {
            $bid = AuctionBid::query()->lockForUpdate()->with(['auction', 'bidder'])->findOrFail($bid->id);

            if ($bid->auction->status !== 'voided') {
                throw ValidationException::withMessages([
                    'refund' => 'Refunds are only available after the sale is voided.',
                ]);
            }

            $fee = $this->paidFee($bid);

            if ($bid->status !== 'accepted' || $fee === null) {
                throw ValidationException::withMessages([
                    'refund' => 'This participation fee was not paid.',
                ]);
            }

            if ((int) $fee->amount < 10) {
                throw ValidationException::withMessages([
                    'refund' => 'M-Pesa cannot send a refund below KES 10.',
                ]);
            }

            if ($this->refunds($bid)->where('status', 'succeeded')->exists()) {
                throw ValidationException::withMessages([
                    'refund' => 'This participation fee has already been refunded.',
                ]);
            }

            $existing = $this->refunds($bid)->where('status', 'initiated')->latest('id')->first();

            if ($existing !== null) {
                return $existing;
            }

            $phone = $bid->bidder?->phone;

            if (blank($phone)) {
                throw ValidationException::withMessages([
                    'refund' => 'This bidder has no M-Pesa number.',
                ]);
            }

            return PaymentAttempt::query()->create([
                'auction_bid_id' => $bid->id,
                'provider' => 'mpesa_daraja_b2c',
                'reference' => $this->generateReference(),
                'amount' => (int) $fee->amount,
                'status' => 'initiated',
                'request_payload' => [
                    'purpose' => 'participation_refund',
                    'phone' => $this->bids->normalizeKenyanPhone((string) $phone),
                    'auction_bid_id' => $bid->id,
                ],
            ]);
        });
    }

    /**
     * @param  array<string, mixed>|null  $responsePayload
     */
    public function confirmRefund(PaymentAttempt $attempt, ?array $responsePayload = null): AuctionBid
    {
        return DB::transaction(function () use ($attempt, $responsePayload): AuctionBid {
            $locked = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $bid = AuctionBid::query()->lockForUpdate()->findOrFail($locked->auction_bid_id);

            if ($locked->status === 'succeeded') {
                return $bid;
            }

            if (! in_array($locked->status, ['initiated', 'failed'], true)) {
                return $bid;
            }

            $locked->update([
                'status' => 'succeeded',
                'failure_reason' => null,
                'response_payload' => $responsePayload ?? $locked->response_payload,
            ]);

            AuditLogger::log('auctions', 'participation refund sent', 'participation_refund_paid', $bid->fresh(), [
                'auction_id' => $bid->auction_id,
                'amount' => $locked->amount,
                'payment_attempt_id' => $locked->id,
            ]);

            return $bid->fresh();
        });
    }

    /**
     * @param  array<string, mixed>|null  $responsePayload
     */
    public function markRefundFailed(PaymentAttempt $attempt, string $message, ?array $responsePayload = null): void
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

            $bid = $locked->auction_bid_id !== null
                ? AuctionBid::query()->find($locked->auction_bid_id)
                : null;

            AuditLogger::log('auctions', 'participation refund not sent', 'participation_refund_failed', $bid, [
                'auction_id' => $bid?->auction_id,
                'payment_attempt_id' => $locked->id,
                'reason' => $locked->failure_reason,
            ]);
        });
    }

    private function paidFee(AuctionBid $bid): ?PaymentAttempt
    {
        return PaymentAttempt::query()
            ->where('auction_bid_id', $bid->id)
            ->where('status', 'succeeded')
            ->where('provider', '!=', 'mpesa_daraja_b2c')
            ->latest('id')
            ->first();
    }

    /**
     * @return Builder<PaymentAttempt>
     */
    private function refunds(AuctionBid $bid): Builder
    {
        return PaymentAttempt::query()
            ->where('auction_bid_id', $bid->id)
            ->where('provider', 'mpesa_daraja_b2c')
            ->where('request_payload->purpose', 'participation_refund');
    }

    private function generateReference(): string
    {
        do {
            $reference = 'RFD'.str()->upper(str()->random(10));
        } while (PaymentAttempt::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
