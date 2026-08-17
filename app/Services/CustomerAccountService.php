<?php

namespace App\Services;

use App\Models\AuctionBid;
use App\Models\CustomerControl;
use App\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CustomerAccountService
{
    public function ensureControl(User $user): CustomerControl
    {
        return $user->customerControl()->firstOrCreate([]);
    }

    public function isSelfExcluded(User $user): bool
    {
        $until = $user->customerControl?->self_excluded_until;

        return $until !== null && $until->isFuture();
    }

    public function assertCanBid(User $user, int $participationFee): void
    {
        if ($user->is_admin) {
            return;
        }

        if ($this->isSelfExcluded($user)) {
            $until = $user->customerControl->self_excluded_until->timezone(config('app.timezone'));

            throw ValidationException::withMessages([
                'phone' => 'Your account is self-excluded from bidding until '.$until->format('j M Y, H:i').'.',
            ]);
        }

        if ($this->wouldExceedSpendLimit($user, $participationFee)) {
            throw ValidationException::withMessages([
                'amount' => 'This bid would exceed your participation fee limit for the selected period.',
            ]);
        }
    }

    public function wouldExceedSpendLimit(User $user, int $additionalFee): bool
    {
        $control = $user->customerControl;

        if ($control === null || $control->participation_fee_limit === null || $control->limit_period === null) {
            return false;
        }

        $bidder = $user->bidder;

        if ($bidder === null) {
            return false;
        }

        $spent = $this->participationFeesPaidSince($bidder->id, $this->periodStart($control->limit_period));

        return ($spent + $additionalFee) > $control->participation_fee_limit;
    }

    /**
     * @return Collection<int, array{bid: AuctionBid, status: string, label: string}>
     */
    public function bidHistory(User $user): Collection
    {
        $bidder = $user->bidder;

        if ($bidder === null) {
            return collect();
        }

        return AuctionBid::query()
            ->with(['auction.result'])
            ->where('bidder_id', $bidder->id)
            ->latest()
            ->get()
            ->map(fn (AuctionBid $bid): array => [
                'bid' => $bid,
                'status' => $this->classifyBid($bid),
                'label' => $this->statusLabel($this->classifyBid($bid)),
            ]);
    }

    public function classifyBid(AuctionBid $bid): string
    {
        if ($bid->status === 'failed') {
            return 'failed';
        }

        if ($bid->status === 'pending_payment') {
            return 'pending_payment';
        }

        $auction = $bid->auction;
        $result = $auction->result;

        if ($auction->settled_at === null) {
            return 'open';
        }

        if ($result !== null && $bid->status === 'accepted' && (
            $result->auction_bid_id === $bid->id
            || ($result->auction_bid_id === null && $bid->amount === $result->sale_price)
        )) {
            return 'successful';
        }

        return 'unsuccessful';
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'open' => 'Open',
            'pending_payment' => 'Awaiting fee payment',
            'successful' => 'Successful buy',
            'unsuccessful' => 'Not successful',
            'failed' => 'Payment failed',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    private function participationFeesPaidSince(int $bidderId, Carbon $since): int
    {
        return (int) PaymentAttempt::query()
            ->where('status', 'succeeded')
            ->whereHas('auctionBid', fn ($query) => $query->where('bidder_id', $bidderId))
            ->where('updated_at', '>=', $since)
            ->sum('amount');
    }

    private function periodStart(string $period): Carbon
    {
        $now = Carbon::now();

        return match ($period) {
            'daily' => $now->copy()->startOfDay(),
            'weekly' => $now->copy()->startOfWeek(),
            'monthly' => $now->copy()->startOfMonth(),
            default => $now->copy()->startOfDay(),
        };
    }
}
