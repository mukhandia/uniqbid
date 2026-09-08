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

    public function initiateSalePayment(AuctionResult $result): PaymentAttempt
    {
        return DB::transaction(function () use ($result): PaymentAttempt {
            $result = AuctionResult::query()->lockForUpdate()->with('auctionBid')->findOrFail($result->id);

            if ($result->isSalePaid()) {
                throw ValidationException::withMessages([
                    'payment' => 'The sale amount has already been paid.',
                ]);
            }

            if ($result->auction_bid_id === null) {
                throw ValidationException::withMessages([
                    'payment' => 'This result has no linked bid.',
                ]);
            }

            return PaymentAttempt::query()->create([
                'auction_bid_id' => $result->auction_bid_id,
                'provider' => 'mpesa_daraja_stk_push',
                'reference' => 'RDM-'.str()->upper(str()->random(10)),
                'amount' => $result->sale_price,
                'status' => 'initiated',
                'request_payload' => [
                    'purpose' => 'sale_redemption_payment',
                    'auction_result_id' => $result->id,
                    'amount' => $result->sale_price,
                ],
            ]);
        });
    }

    public function confirmSalePayment(PaymentAttempt $attempt): AuctionResult
    {
        return DB::transaction(function () use ($attempt): AuctionResult {
            $attempt = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ($attempt->status === 'succeeded') {
                $payload = $attempt->request_payload ?? [];
                $resultId = (int) ($payload['auction_result_id'] ?? 0);

                return AuctionResult::query()->findOrFail($resultId);
            }

            if ($attempt->status !== 'initiated') {
                throw ValidationException::withMessages([
                    'payment' => 'This payment attempt can no longer be completed.',
                ]);
            }

            $payload = $attempt->request_payload ?? [];
            $resultId = (int) ($payload['auction_result_id'] ?? 0);
            $result = AuctionResult::query()->lockForUpdate()->findOrFail($resultId);

            $attempt->update([
                'status' => 'succeeded',
                'response_payload' => ['status' => 'stk_push_simulated_success', 'purpose' => 'sale_redemption_payment'],
            ]);

            $result->update([
                'sale_payment_status' => AuctionResult::PAYMENT_PAID,
                'sale_payment_paid_at' => now(),
            ]);

            return $result->fresh();
        });
    }

    public function redeem(AuctionResult $result): AuctionResult
    {
        return DB::transaction(function () use ($result): AuctionResult {
            $result = AuctionResult::query()->lockForUpdate()->with('auction')->findOrFail($result->id);

            if (! $result->isSalePaid()) {
                throw ValidationException::withMessages([
                    'redeem' => 'Pay the successful bid amount before redeeming.',
                ]);
            }

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

            $result->update([
                'outcome_choice' => 'redeem',
                'fulfilment_status' => AuctionResult::FULFILMENT_REDEEMED,
                'redemption_amount' => $retailValue,
                'redemption_payout_status' => AuctionResult::REDEMPTION_PAYOUT_PENDING,
                'redeemed_at' => now(),
            ]);

            $fresh = $result->fresh('auction');

            AuditLogger::log('redeem', 'successful buy redeemed', 'redeemed', $fresh, [
                'auction_id' => $fresh->auction_id,
                'redemption_amount' => $fresh->redemption_amount,
            ]);

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
