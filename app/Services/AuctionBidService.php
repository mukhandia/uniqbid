<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\Bidder;
use App\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuctionBidService
{
    public const KENYAN_MOBILE_INPUT_REGEX = '/^(\+?254|0)[17]\d{8}$/';

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
            $user = User::query()->where('phone', $phone)->first();

            if ($user !== null && Auth::id() !== $user->id) {
                throw ValidationException::withMessages([
                    'phone' => 'This mobile number has an account. Sign in with the one-time code to place a bid.',
                ]);
            }

            if ($user !== null) {
                app(CustomerAccountService::class)->assertCanBid($user, $auction->bid_fee);
            }

            $bidder = Bidder::query()->firstOrNew(['phone' => $phone]);
            $bidder->fill([
                'user_id' => $user?->id,
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
                'provider' => 'mpesa_daraja_stk_push',
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
                'response_payload' => ['status' => 'stk_push_simulated_success'],
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
                'response_payload' => ['status' => 'stk_push_simulated_failure'],
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

        if (strlen($digits) === 13 && str_starts_with($digits, '2540')) {
            $digits = '254'.substr($digits, 4);
        }

        if (! preg_match('/^254[17]\d{8}$/', $digits)) {
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
