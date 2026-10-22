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

        if ($result->fulfilment_status === AuctionResult::FULFILMENT_REDEEMED) {
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

            if ($result->fulfilment_status === AuctionResult::FULFILMENT_REDEEMED) {
                throw ValidationException::withMessages([
                    'payment' => 'This successful buy was redeemed and does not need a sale payment.',
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
                $result->update([
                    'sale_payment_status' => AuctionResult::PAYMENT_PAID,
                    'sale_payment_paid_at' => now(),
                ]);

                AuditLogger::log('sale_payment', 'successful-buy payment received', 'paid', $result, [
                    'auction_id' => $result->auction_id,
                    'amount' => $result->sale_price,
                    'payment_attempt_id' => $attempt->id,
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

            AuditLogger::log('redeem', 'successful buy redeemed', 'redeemed', $fresh, [
                'auction_id' => $fresh->auction_id,
                'redemption_amount' => $fresh->redemption_amount,
            ]);

            $this->logistics->syncFromAuctionResult($fresh);

            return $fresh;
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
}
