<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\Bidder;
use App\Models\PaymentAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuctionBidService
{
    public function __construct(
        private readonly NotificationPlaceholderService $notifications,
    ) {}

    /**
     * @param  array{name: string, email: string, phone: string, whatsapp_number: string, marketing_consent: bool}  $details
     */
    public function initiate(Auction $auction, int $amount, array $details): PaymentAttempt
    {
        return DB::transaction(function () use ($auction, $amount, $details): PaymentAttempt {
            $auction = Auction::query()->lockForUpdate()->findOrFail($auction->id);

            if ($auction->status !== 'live' || $auction->ends_at->isPast()) {
                throw ValidationException::withMessages([
                    'amount' => 'This sale is no longer accepting bids.',
                ]);
            }

            $phone = $this->normalizeKenyanPhone($details['phone']);
            $whatsapp = $this->normalizeKenyanPhone($details['whatsapp_number'] ?: $details['phone']);
            $consent = $details['marketing_consent'];

            $bidder = Bidder::query()->firstOrNew(['phone' => $phone]);
            $bidder->fill([
                'name' => $details['name'],
                'email' => $details['email'],
                'whatsapp_number' => $whatsapp,
                'marketing_consent' => $consent,
                'marketing_consented_at' => $consent ? now() : null,
            ])->save();

            $bid = AuctionBid::query()->create([
                'auction_id' => $auction->id,
                'bidder_id' => $bidder->id,
                'reference' => $this->reference('BID'),
                'amount' => $amount,
            ]);

            return $bid->paymentAttempts()->create([
                'provider' => 'intasend_placeholder',
                'reference' => $this->reference('PAY'),
                'amount' => $auction->bid_fee,
                'request_payload' => [
                    'phone' => $phone,
                    'amount' => $auction->bid_fee,
                    'bid_reference' => $bid->reference,
                ],
            ]);
        });
    }

    public function markSucceeded(PaymentAttempt $attempt): AuctionBid
    {
        $bid = DB::transaction(function () use ($attempt): AuctionBid {
            $attempt = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $bid = AuctionBid::query()->lockForUpdate()->findOrFail($attempt->auction_bid_id);

            if ($attempt->status === 'succeeded') {
                return $bid->load(['auction', 'bidder']);
            }

            if ($attempt->status !== 'initiated') {
                throw ValidationException::withMessages([
                    'payment' => 'This payment attempt can no longer be completed.',
                ]);
            }

            $attempt->update([
                'status' => 'succeeded',
                'response_payload' => ['status' => 'placeholder_paid'],
            ]);
            $bid->update(['status' => 'accepted', 'paid_at' => now()]);
            $bid->auction()->increment('bids_count');

            return $bid->fresh(['auction', 'bidder']);
        });

        $this->notifications->paymentSucceeded($bid);

        return $bid;
    }

    public function markFailed(PaymentAttempt $attempt): void
    {
        $bid = DB::transaction(function () use ($attempt): AuctionBid {
            $attempt = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $bid = AuctionBid::query()->lockForUpdate()->findOrFail($attempt->auction_bid_id);

            if ($attempt->status === 'failed') {
                return $bid->load(['auction', 'bidder']);
            }

            $attempt->update([
                'status' => 'failed',
                'failure_reason' => 'Payment was not completed.',
                'response_payload' => ['status' => 'placeholder_failed'],
            ]);

            $bid->update(['status' => 'failed']);

            return $bid->fresh(['auction', 'bidder']);
        });

        $this->notifications->paymentFailed($bid);
    }

    public function normalizeKenyanPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (str_starts_with($digits, '0')) {
            $digits = '254'.substr($digits, 1);
        }

        if (! preg_match('/^2547\d{8}$/', $digits)) {
            throw ValidationException::withMessages([
                'phone' => 'Enter a valid Kenyan mobile number.',
            ]);
        }

        return $digits;
    }

    private function reference(string $prefix): string
    {
        return $prefix.'-'.str()->upper(str()->random(10));
    }
}
