<?php

namespace App\Services;

use App\Models\AuctionBid;
use App\Models\CustomerControl;
use App\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Carbon;
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

        $until = $user->customerControl?->self_excluded_until;

        if ($until !== null && $until->isFuture()) {
            throw ValidationException::withMessages([
                'phone' => 'Your account is self-excluded from bidding until '.$until->timezone(config('app.timezone'))->format('j M Y, H:i').'.',
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
     * @return LengthAwarePaginator<int, array{bid: AuctionBid, status: string, label: string}>
     */
    public function bidHistory(User $user, int $perPage = 10, string $pageName = 'historyPage', ?string $search = null): LengthAwarePaginator
    {
        $bidder = $user->bidder;

        if ($bidder === null) {
            return new Paginator([], 0, $perPage, Paginator::resolveCurrentPage($pageName), [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ]);
        }

        $term = trim((string) $search);

        return AuctionBid::query()
            ->with(['auction.result', 'bidder', 'paymentAttempts'])
            ->where('bidder_id', $bidder->id)
            ->when($term !== '', function ($query) use ($term): void {
                $query->where(function ($builder) use ($term): void {
                    $builder
                        ->where('reference', 'like', '%'.$term.'%')
                        ->orWhereHas('auction', fn ($auction) => $auction->where('name', 'like', '%'.$term.'%'));
                });
            })
            ->latest()
            ->paginate($perPage, pageName: $pageName)
            ->through(function (AuctionBid $bid): array {
                $status = $this->classifyBid($bid);

                return [
                    'bid' => $bid,
                    'status' => $status,
                    'label' => $this->guestHistoryLabel($status),
                ];
            });
    }

    /**
     * Guest account history hides win/loss; UniqBid notifies winners separately.
     */
    public function guestHistoryLabel(string $status): string
    {
        return match ($status) {
            'successful', 'unsuccessful' => 'Bid Successfully Placed',
            default => $this->statusLabel($status),
        };
    }

    public function refundState(AuctionBid $bid): ?string
    {
        if ($bid->auction->status !== 'voided') {
            return null;
        }

        $attempt = $bid->paymentAttempts
            ->filter(fn (PaymentAttempt $payment): bool => ($payment->request_payload['purpose'] ?? '') === 'participation_refund')
            ->sortByDesc('id')
            ->first();

        if ($attempt === null) {
            return null;
        }

        return match ($attempt->status) {
            'succeeded' => 'successful',
            'failed' => 'failed',
            default => 'pending',
        };
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

        if ($auction->status === 'voided' && $bid->status === 'accepted') {
            return match ($this->refundState($bid)) {
                'successful' => 'refund_successful',
                'failed' => 'refund_failed',
                default => 'awaiting_refund',
            };
        }

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
            'awaiting_refund' => 'Awaiting Refund',
            'refund_successful' => 'Refund successful',
            'refund_failed' => 'Refund failed',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    private function participationFeesPaidSince(int $bidderId, Carbon $since): int
    {
        return (int) PaymentAttempt::query()
            ->where('status', 'succeeded')
            ->where('provider', '!=', 'mpesa_daraja_b2c')
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
