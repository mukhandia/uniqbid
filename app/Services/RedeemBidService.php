<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\AuctionResult;
use App\Models\PaymentAttempt;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RedeemBidService
{
    public function __construct(
        private readonly LogisticsService $logistics,
        private readonly AuctionBidService $bids,
    ) {}

    /**
     * @return array{bid: AuctionBid, result: AuctionResult, auction: Auction}
     */
    public function validateReference(string $reference): array
    {
        $reference = strtoupper(trim($reference));

        $bid = AuctionBid::query()
            ->with(['auction', 'bidder'])
            ->where('reference', $reference)
            ->where('status', 'accepted')
            ->first();

        if ($bid === null) {
            throw ValidationException::withMessages([
                'reference' => 'No successful bid was found for that reference.',
            ]);
        }

        $result = AuctionResult::query()
            ->where('auction_id', $bid->auction_id)
            ->where('auction_bid_id', $bid->id)
            ->first();

        if ($result === null) {
            throw ValidationException::withMessages([
                'reference' => 'This bid is not the successful buy for its sale.',
            ]);
        }

        $unpaidRedeem = $result->fulfilment_status === AuctionResult::FULFILMENT_REDEEMED
            && $result->redemption_payout_status === AuctionResult::REDEMPTION_PAYOUT_PENDING;

        if ($result->isSalePaid() && ! $unpaidRedeem) {
            throw ValidationException::withMessages([
                'reference' => 'This successful buy has been paid and cannot be redeemed.',
            ]);
        }

        if ($result->fulfilment_status === AuctionResult::FULFILMENT_REDEEMED
            && $result->redemption_payout_status !== AuctionResult::REDEMPTION_PAYOUT_PENDING) {
            throw ValidationException::withMessages([
                'reference' => 'This successful buy has already been redeemed.',
            ]);
        }

        if (in_array($result->fulfilment_status, [
            AuctionResult::FULFILMENT_COLLECTED,
            AuctionResult::FULFILMENT_DELIVERED,
            AuctionResult::FULFILMENT_DISPATCHED,
        ], true)) {
            throw ValidationException::withMessages([
                'reference' => 'This successful buy is already in collection or delivery and cannot be redeemed.',
            ]);
        }

        return [
            'bid' => $bid,
            'result' => $result,
            'auction' => $bid->auction,
        ];
    }

    public function initiateSalePayment(AuctionResult $result, string $phone): PaymentAttempt
    {
        $phone = $this->bids->normalizeKenyanPhone($phone);

        return DB::transaction(function () use ($result, $phone): PaymentAttempt {
            $result = AuctionResult::query()->lockForUpdate()->with('auctionBid')->findOrFail($result->id);

            if ($result->isSalePaid()) {
                throw ValidationException::withMessages([
                    'payment' => 'The sale amount has already been paid.',
                ]);
            }

            if ($result->fulfilment_status === AuctionResult::FULFILMENT_REDEEMED
                && $result->redemption_payout_status === AuctionResult::REDEMPTION_PAYOUT_PAID) {
                throw ValidationException::withMessages([
                    'payment' => 'This successful buy was redeemed and the cash has already been sent.',
                ]);
            }

            if ($result->fulfilment_status === AuctionResult::FULFILMENT_REDEEMED
                && $this->openPayoutAttempt($result) !== null) {
                throw ValidationException::withMessages([
                    'payment' => 'The cash payout is still with M-Pesa. Wait for that result before paying for the item.',
                ]);
            }

            if ($result->auction_bid_id === null || $result->sale_price < 1) {
                throw ValidationException::withMessages([
                    'payment' => 'This payment cannot be sent to M-Pesa.',
                ]);
            }

            $existing = PaymentAttempt::query()
                ->where('auction_bid_id', $result->auction_bid_id)
                ->where('status', 'initiated')
                ->where(function ($query): void {
                    $query->where('request_payload->purpose', 'successful_buy')
                        ->orWhere('request_payload->purpose', 'sale_redemption_payment');
                })
                ->latest('id')
                ->first();

            if ($existing !== null) {
                $payload = $existing->request_payload ?? [];
                $payload['phone'] = $phone;
                $existing->update(['request_payload' => $payload]);

                return $existing->fresh();
            }

            return PaymentAttempt::query()->create([
                'auction_bid_id' => $result->auction_bid_id,
                'provider' => 'mpesa_daraja_stk_push',
                'reference' => 'SBY-'.str()->upper(str()->random(10)),
                'amount' => $result->sale_price,
                'status' => 'initiated',
                'request_payload' => [
                    'purpose' => 'successful_buy',
                    'auction_result_id' => $result->id,
                    'amount' => $result->sale_price,
                    'phone' => $phone,
                ],
            ]);
        });
    }

    /**
     * @param  array<string, mixed>|null  $responsePayload
     */
    public function confirmSalePayment(PaymentAttempt $attempt, ?array $responsePayload = null): AuctionResult
    {
        return DB::transaction(function () use ($attempt, $responsePayload): AuctionResult {
            $attempt = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $payload = $attempt->request_payload ?? [];
            $result = AuctionResult::query()->lockForUpdate()->findOrFail((int) ($payload['auction_result_id'] ?? 0));

            if ($attempt->status === 'succeeded') {
                return $result;
            }

            if (! in_array($attempt->status, ['initiated', 'failed'], true)) {
                throw ValidationException::withMessages([
                    'payment' => 'This payment attempt can no longer be completed.',
                ]);
            }

            $attempt->update([
                'status' => 'succeeded',
                'failure_reason' => null,
                'response_payload' => $responsePayload ?? [
                    'status' => 'stk_push_simulated_success',
                    'purpose' => 'successful_buy',
                ],
            ]);

            if (! $result->isSalePaid()) {
                $takingTheItem = $result->fulfilment_status === AuctionResult::FULFILMENT_REDEEMED
                    && $result->redemption_payout_status !== AuctionResult::REDEMPTION_PAYOUT_PAID;

                $result->update([
                    'sale_payment_status' => AuctionResult::PAYMENT_PAID,
                    'sale_payment_paid_at' => now(),
                    ...($takingTheItem ? [
                        'outcome_choice' => null,
                        'fulfilment_status' => AuctionResult::FULFILMENT_PENDING,
                        'redemption_amount' => null,
                        'redemption_payout_status' => null,
                        'redeemed_at' => null,
                        'redemption_requested_at' => null,
                        'redemption_review_read_at' => null,
                    ] : []),
                ]);

                if ($takingTheItem) {
                    $this->logistics->reopenAfterUnpaidRedemption($result);
                }

                AuditLogger::log('sale_payment', 'successful-buy payment received', 'paid', $result, [
                    'auction_id' => $result->auction_id,
                    'amount' => $result->sale_price,
                    'payment_attempt_id' => $attempt->id,
                    'replaced_unpaid_redeem' => $takingTheItem,
                ]);
            }

            return $result->fresh();
        });
    }

    /**
     * @param  array<string, mixed>|null  $responsePayload
     */
    public function markSalePaymentFailed(PaymentAttempt $attempt, ?string $reason = null, ?array $responsePayload = null): void
    {
        DB::transaction(function () use ($attempt, $reason, $responsePayload): void {
            $attempt = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if (in_array($attempt->status, ['failed', 'succeeded'], true)) {
                return;
            }

            $attempt->update([
                'status' => 'failed',
                'failure_reason' => str($reason ?: 'Payment was not completed.')->limit(250, ''),
                'response_payload' => $responsePayload ?? [
                    'status' => 'stk_push_simulated_failure',
                    'purpose' => 'successful_buy',
                ],
            ]);
        });
    }

    public function redeem(AuctionResult $result): AuctionResult
    {
        return DB::transaction(function () use ($result): AuctionResult {
            $result = AuctionResult::query()->lockForUpdate()->with('auction')->findOrFail($result->id);

            if ($result->fulfilment_status === AuctionResult::FULFILMENT_REDEEMED) {
                return $result;
            }

            if ($result->isSalePaid()) {
                throw ValidationException::withMessages([
                    'redeem' => 'This successful buy has been paid and cannot be redeemed.',
                ]);
            }

            if (in_array($result->fulfilment_status, [
                AuctionResult::FULFILMENT_COLLECTED,
                AuctionResult::FULFILMENT_DELIVERED,
                AuctionResult::FULFILMENT_DISPATCHED,
            ], true)) {
                throw ValidationException::withMessages([
                    'redeem' => 'This item is already in fulfilment and cannot be redeemed.',
                ]);
            }

            $retailValue = (int) ($result->auction->retail_value ?? 0);
            $salePrice = (int) $result->sale_price;
            $payout = max(0, $retailValue - $salePrice);

            $result->update([
                'outcome_choice' => 'redeem',
                'fulfilment_status' => AuctionResult::FULFILMENT_REDEEMED,
                'redemption_amount' => $payout,
                'redemption_payout_status' => AuctionResult::REDEMPTION_PAYOUT_PENDING,
                'redeemed_at' => now(),
            ]);

            $fresh = $result->fresh(['auction', 'auctionBid.bidder']);

            AuditLogger::log('redeem', 'redemption recorded, cash payout not sent', 'redeem_recorded', $fresh, [
                'auction_id' => $fresh->auction_id,
                'redemption_amount' => $fresh->redemption_amount,
            ]);

            $this->logistics->syncFromAuctionResult($fresh);

            return $fresh;
        });
    }

    public function submitForReview(AuctionResult $result): AuctionResult
    {
        return DB::transaction(function () use ($result): AuctionResult {
            $result = AuctionResult::query()->lockForUpdate()->findOrFail($result->id);

            if ($result->fulfilment_status !== AuctionResult::FULFILMENT_REDEEMED) {
                throw ValidationException::withMessages([
                    'redeem' => 'Only a redeemed successful buy can be sent for review.',
                ]);
            }

            if ($result->redemption_payout_status === AuctionResult::REDEMPTION_PAYOUT_PAID) {
                throw ValidationException::withMessages([
                    'redeem' => 'This payout has already been sent.',
                ]);
            }

            if ((int) $result->redemption_amount < 10) {
                throw ValidationException::withMessages([
                    'redeem' => 'M-Pesa cannot send a payout below KES 10.',
                ]);
            }

            if ($result->redemption_requested_at !== null) {
                return $result;
            }

            $result->update([
                'redemption_requested_at' => now(),
                'redemption_review_read_at' => null,
            ]);

            $fresh = $result->fresh();

            AuditLogger::log('redeem', 'redeem request sent for review', 'redeem_requested', $fresh, [
                'auction_id' => $fresh->auction_id,
                'redemption_amount' => $fresh->redemption_amount,
            ]);

            return $fresh;
        });
    }

    public function beginPayout(AuctionResult $result): PaymentAttempt
    {
        return DB::transaction(function () use ($result): PaymentAttempt {
            $result = AuctionResult::query()
                ->lockForUpdate()
                ->with('auctionBid.bidder')
                ->findOrFail($result->id);

            if ($this->openSalePaymentAttempt($result) !== null) {
                throw ValidationException::withMessages([
                    'redeem' => 'A payment for this item is already in progress.',
                ]);
            }

            if ($result->fulfilment_status !== AuctionResult::FULFILMENT_REDEEMED) {
                throw ValidationException::withMessages([
                    'redeem' => 'Only a redeemed successful buy can be paid out.',
                ]);
            }

            if ($result->redemption_requested_at === null) {
                throw ValidationException::withMessages([
                    'redeem' => 'The winner has not sent this redeem for review.',
                ]);
            }

            if ($result->redemption_payout_status === AuctionResult::REDEMPTION_PAYOUT_PAID) {
                throw ValidationException::withMessages([
                    'redeem' => 'This payout has already been sent.',
                ]);
            }

            $amount = (int) $result->redemption_amount;

            if ($amount < 10) {
                throw ValidationException::withMessages([
                    'redeem' => 'M-Pesa cannot send a payout below KES 10.',
                ]);
            }

            $existing = PaymentAttempt::query()
                ->where('auction_bid_id', $result->auction_bid_id)
                ->where('provider', 'mpesa_daraja_b2c')
                ->where('status', 'initiated')
                ->latest('id')
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $phone = $this->bids->normalizeKenyanPhone((string) $result->auctionBid->bidder->phone);

            return PaymentAttempt::query()->create([
                'auction_bid_id' => $result->auction_bid_id,
                'provider' => 'mpesa_daraja_b2c',
                'reference' => $this->generatePayoutReference(),
                'amount' => $amount,
                'status' => 'initiated',
                'request_payload' => [
                    'purpose' => 'redeem_payout',
                    'phone' => $phone,
                    'auction_result_id' => $result->id,
                ],
            ]);
        });
    }

    /**
     * @param  array<string, mixed>|null  $responsePayload
     */
    public function confirmPayout(PaymentAttempt $attempt, ?array $responsePayload = null): AuctionResult
    {
        return DB::transaction(function () use ($attempt, $responsePayload): AuctionResult {
            $locked = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $result = AuctionResult::query()->lockForUpdate()->findOrFail(
                (int) ($locked->request_payload['auction_result_id'] ?? 0),
            );

            if ($locked->status === 'succeeded' && $result->redemption_payout_status === AuctionResult::REDEMPTION_PAYOUT_PAID) {
                return $result;
            }

            if (! in_array($locked->status, ['initiated', 'failed'], true)) {
                return $result;
            }

            $locked->update([
                'status' => 'succeeded',
                'failure_reason' => null,
                'response_payload' => $responsePayload ?? $locked->response_payload,
            ]);

            if ($result->fulfilment_status !== AuctionResult::FULFILMENT_REDEEMED) {
                $result->update(['redemption_requested_at' => null]);

                AuditLogger::log('redeem', 'redemption payout sent after the winner chose the item', 'payout_after_sale', $result, [
                    'auction_id' => $result->auction_id,
                    'payment_attempt_id' => $locked->id,
                ]);

                return $result->fresh();
            }

            if ($result->redemption_payout_status !== AuctionResult::REDEMPTION_PAYOUT_PAID) {
                $result->update([
                    'redemption_payout_status' => AuctionResult::REDEMPTION_PAYOUT_PAID,
                    'redemption_requested_at' => null,
                ]);

                AuditLogger::log('redeem', 'redemption payout sent', 'payout_paid', $result->fresh(), [
                    'auction_id' => $result->auction_id,
                    'redemption_amount' => $result->redemption_amount,
                    'payment_attempt_id' => $locked->id,
                ]);
            }

            return $result->fresh();
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

            $resultId = (int) ($locked->request_payload['auction_result_id'] ?? 0);
            $result = $resultId > 0 ? AuctionResult::query()->find($resultId) : null;

            AuditLogger::log('redeem', 'redemption payout not sent', 'payout_failed', $result, [
                'auction_id' => $result?->auction_id,
                'payment_attempt_id' => $locked->id,
                'reason' => $locked->failure_reason,
            ]);
        });
    }

    public function markPayoutPaid(AuctionResult $result): AuctionResult
    {
        return DB::transaction(function () use ($result): AuctionResult {
            $result = AuctionResult::query()->lockForUpdate()->findOrFail($result->id);

            if ($result->fulfilment_status !== AuctionResult::FULFILMENT_REDEEMED) {
                throw ValidationException::withMessages([
                    'redeem' => 'Only redeemed successful buys can be marked as paid out.',
                ]);
            }

            $result->update([
                'redemption_payout_status' => AuctionResult::REDEMPTION_PAYOUT_PAID,
            ]);

            $fresh = $result->fresh();

            AuditLogger::log('redeem', 'redemption payout marked paid', 'payout_paid', $fresh, [
                'auction_id' => $fresh->auction_id,
                'redemption_amount' => $fresh->redemption_amount,
            ]);

            return $fresh;
        });
    }

    private function openPayoutAttempt(AuctionResult $result): ?PaymentAttempt
    {
        if ($result->auction_bid_id === null) {
            return null;
        }

        return PaymentAttempt::query()
            ->where('auction_bid_id', $result->auction_bid_id)
            ->where('provider', 'mpesa_daraja_b2c')
            ->where('status', 'initiated')
            ->latest('id')
            ->first();
    }

    private function openSalePaymentAttempt(AuctionResult $result): ?PaymentAttempt
    {
        if ($result->auction_bid_id === null) {
            return null;
        }

        return PaymentAttempt::query()
            ->where('auction_bid_id', $result->auction_bid_id)
            ->where('status', 'initiated')
            ->where(function ($query): void {
                $query->where('request_payload->purpose', 'successful_buy')
                    ->orWhere('request_payload->purpose', 'sale_redemption_payment');
            })
            ->latest('id')
            ->first();
    }

    private function generatePayoutReference(): string
    {
        do {
            $reference = 'RDM'.str()->upper(str()->random(10));
        } while (PaymentAttempt::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
