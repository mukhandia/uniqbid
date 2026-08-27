<?php

namespace App\Services;

use App\Models\Bidder;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * @phpstan-type SubscriberRow array{name: string, email: string|null, phone: string|null, source: string, sms: bool|null, whatsapp: bool|null, consented_at: Carbon|null}
 */
class SubscriberListService
{
    /**
     * @return list<SubscriberRow>
     */
    public function optedIn(): array
    {
        $accounts = User::query()
            ->marketingConsent()
            ->get(['name', 'email', 'phone', 'sms_notifications', 'whatsapp_notifications', 'marketing_consented_at'])
            ->map($this->fromAccount(...))
            ->all();

        $guests = Bidder::query()
            ->marketingConsent()
            ->guest()
            ->get(['name', 'email', 'phone', 'marketing_consented_at'])
            ->map($this->fromGuest(...))
            ->all();

        $rows = [...$accounts, ...$guests];

        usort($rows, function (array $left, array $right): int {
            $rightAt = $right['consented_at'] ?? null;
            $leftAt = $left['consented_at'] ?? null;

            $rightTs = $rightAt instanceof Carbon ? $rightAt->getTimestamp() : 0;
            $leftTs = $leftAt instanceof Carbon ? $leftAt->getTimestamp() : 0;

            return $rightTs <=> $leftTs;
        });

        return $rows;
    }

    /**
     * @return SubscriberRow
     */
    private function fromAccount(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'source' => 'Account',
            'sms' => $user->sms_notifications,
            'whatsapp' => $user->whatsapp_notifications,
            'consented_at' => $user->marketing_consented_at,
        ];
    }

    /**
     * @return SubscriberRow
     */
    private function fromGuest(Bidder $bidder): array
    {
        return [
            'name' => $bidder->name,
            'email' => $bidder->email,
            'phone' => $bidder->phone,
            'source' => 'Guest',
            'sms' => null,
            'whatsapp' => null,
            'consented_at' => $bidder->marketing_consented_at,
        ];
    }
}
