<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\Bidder;
use App\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuctionBidService
{
    public const KENYAN_MOBILE_INPUT_REGEX = '/^(\+?254|0)[17]\d{8}$/';

    public function __construct(
        private readonly NotificationPlaceholderService $notifications,
    ) {}

    /**
     * @param  array{name: string, email?: string|null, phone: string, whatsapp_number?: string|null, marketing_consent?: bool}  $details
     */
    public function initiate(Auction $auction, int $amount, array $details): PaymentAttempt
    {
        return DB::transaction(function () use ($auction, $amount, $details): PaymentAttempt {
            $auction = Auction::query()->lockForUpdate()->findOrFail($auction->id);

            if ($auction->status !== 'live' || $auction->ends_at->isPast()) {
                throw ValidationException::withMessages([
                    'amount' => 'This sale is no longer accepting entries.',
                ]);
            }

            $phone = $this->normalizeKenyanPhone($details['phone']);
            $whatsapp = filled($details['whatsapp_number'] ?? null)
                ? $this->normalizeKenyanPhone((string) $details['whatsapp_number'])
                : null;
            $consent = (bool) ($details['marketing_consent'] ?? true);
            $email = filled($details['email'] ?? null) ? (string) $details['email'] : null;
            $user = User::query()->where('phone', $phone)->first();

            if ($user !== null && Auth::id() !== $user->id) {
                throw ValidationException::withMessages([
                    'phone' => 'This mobile number has an account. Sign in with the one-time code to participate.',
                ]);
            }

            if ($user !== null) {
                app(CustomerAccountService::class)->assertCanBid($user, $auction->bid_fee);
            }

            $bidder = Bidder::query()->firstOrNew(['phone' => $phone]);
            $bidder->fill([
                'user_id' => $user?->id,
                'name' => $details['name'],
                'email' => $email ?? $bidder->email,
                'whatsapp_number' => $whatsapp ?? $bidder->whatsapp_number,
                'marketing_consent' => $consent,
                'marketing_consented_at' => $consent ? ($bidder->marketing_consented_at ?? now()) : null,
            ])->save();

            if ($user !== null && $consent) {
                $user->forceFill([
                    'marketing_consent' => true,
                    'marketing_consented_at' => $user->marketing_consented_at ?? now(),
                    'sms_notifications' => true,
                    'whatsapp_notifications' => true,
                ])->save();
            }

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

    /**
     * @param  array<string, mixed>|null  $responsePayload
     */
    public function markSucceeded(PaymentAttempt $attempt, ?array $responsePayload = null): AuctionBid
    {
        $alreadySettled = false;

        $bid = DB::transaction(function () use ($attempt, $responsePayload, &$alreadySettled): AuctionBid {
            $attempt = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $bid = AuctionBid::query()->lockForUpdate()->findOrFail($attempt->auction_bid_id);

            if ($attempt->status === 'succeeded') {
                $alreadySettled = true;

                return $bid->load(['auction', 'bidder']);
            }

            if (! in_array($attempt->status, ['initiated', 'failed'], true)) {
                throw ValidationException::withMessages([
                    'payment' => 'This payment attempt can no longer be completed.',
                ]);
            }

            $attempt->update([
                'status' => 'succeeded',
                'failure_reason' => null,
                'response_payload' => $responsePayload ?? ['status' => 'stk_push_simulated_success'],
            ]);

            if ($bid->status !== 'accepted') {
                $bid->update(['status' => 'accepted', 'paid_at' => now()]);
                $bid->auction()->increment('bids_count');
            }

            $this->ensureCustomerAccount($bid->bidder()->lockForUpdate()->firstOrFail());

            return $bid->fresh(['auction', 'bidder.user']);
        });

        if (! $alreadySettled) {
            $this->notifications->paymentSucceeded($bid);
        }

        return $bid;
    }

    /**
     * @param  array<string, mixed>|null  $responsePayload
     */
    public function markFailed(PaymentAttempt $attempt, ?string $reason = null, ?array $responsePayload = null): void
    {
        $alreadySettled = false;

        $bid = DB::transaction(function () use ($attempt, $reason, $responsePayload, &$alreadySettled): AuctionBid {
            $attempt = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $bid = AuctionBid::query()->lockForUpdate()->findOrFail($attempt->auction_bid_id);

            if (in_array($attempt->status, ['failed', 'succeeded'], true)) {
                $alreadySettled = true;

                return $bid->load(['auction', 'bidder']);
            }

            $attempt->update([
                'status' => 'failed',
                'failure_reason' => str($reason ?: 'Payment was not completed.')->limit(250, ''),
                'response_payload' => $responsePayload ?? ['status' => 'stk_push_simulated_failure'],
            ]);

            $bid->update(['status' => 'failed']);

            return $bid->fresh(['auction', 'bidder']);
        });

        if (! $alreadySettled) {
            $this->notifications->paymentFailed($bid);
        }
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

    /**
     * A bidder's first paid participation fee proves the mobile number, so the account is created then.
     */
    public function ensureCustomerAccount(Bidder $bidder): ?User
    {
        if ($bidder->user_id !== null && ($linked = User::query()->find($bidder->user_id)) !== null) {
            return $linked;
        }

        $user = User::query()->where('phone', $bidder->phone)->first();

        if ($user?->canAccessAdmin()) {
            return null;
        }

        if ($user === null) {
            $email = filled($bidder->email) && ! User::query()->where('email', $bidder->email)->exists()
                ? $bidder->email
                : null;

            $user = User::query()->create([
                'name' => $bidder->name,
                'email' => $email,
                'phone' => $bidder->phone,
                'password' => Hash::make(Str::random(40)),
                'marketing_consent' => $bidder->marketing_consent,
                'marketing_consented_at' => $bidder->marketing_consent ? ($bidder->marketing_consented_at ?? now()) : null,
                'sms_notifications' => $bidder->marketing_consent,
                'whatsapp_notifications' => $bidder->marketing_consent,
            ]);
            $user->forceFill(['phone_verified_at' => now()])->save();
        }

        $bidder->forceFill(['user_id' => $user->id])->save();

        return $user;
    }

    private function reference(string $prefix): string
    {
        return $prefix.'-'.str()->upper(str()->random(10));
    }
}
