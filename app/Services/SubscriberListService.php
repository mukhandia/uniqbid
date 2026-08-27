<?php

namespace App\Services;

use App\Models\Bidder;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SubscriberListService
{
    /**
     * @return Collection<int, array{name: string, email: string|null, phone: string|null, source: string, sms: bool|null, whatsapp: bool|null, consented_at: Carbon|null}>
     */
    public function optedIn(): Collection
    {
        $accounts = User::query()
            ->marketingConsent()
            ->get(['name', 'email', 'phone', 'sms_notifications', 'whatsapp_notifications', 'marketing_consented_at'])
            ->map(fn (User $user): array => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'source' => 'Account',
                'sms' => (bool) $user->sms_notifications,
                'whatsapp' => (bool) $user->whatsapp_notifications,
                'consented_at' => $user->marketing_consented_at,
            ]);

        $guests = Bidder::query()
            ->marketingConsent()
            ->guest()
            ->get(['name', 'email', 'phone', 'marketing_consented_at'])
            ->map(fn (Bidder $bidder): array => [
                'name' => $bidder->name,
                'email' => $bidder->email,
                'phone' => $bidder->phone,
                'source' => 'Guest',
                'sms' => null,
                'whatsapp' => null,
                'consented_at' => $bidder->marketing_consented_at,
            ]);

        return $accounts
            ->concat($guests)
            ->sortByDesc(fn (array $row): int => $row['consented_at']?->getTimestamp() ?? 0)
            ->values();
    }
}
