<?php

namespace App\Models;

use App\Support\MapEmbedUrl;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $phone
 * @property string $whatsapp
 * @property string $email
 * @property string $address
 * @property string $hours
 * @property string|null $map_url
 * @property string $auction_display_mode
 * @property int $auction_shuffle_interval_seconds
 * @property int $guest_shop_commission_percent
 * @property string $licence_number
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'phone',
    'whatsapp',
    'email',
    'address',
    'hours',
    'map_url',
    'auction_display_mode',
    'auction_shuffle_interval_seconds',
    'guest_shop_commission_percent',
    'licence_number',
])]
class SiteSetting extends Model
{
    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'phone' => '+254 700 000 000',
            'whatsapp' => '254700000000',
            'email' => 'support@uniqbid.co.ke',
            'address' => "Westlands Business Centre, 3rd Floor\nWaiyaki Way, Nairobi",
            'hours' => 'Mon–Sat, 8:00 – 18:00 EAT',
            'map_url' => null,
            'auction_display_mode' => 'auto',
            'auction_shuffle_interval_seconds' => 5,
            'guest_shop_commission_percent' => 10,
            'licence_number' => '2026/0010/008895',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'auction_shuffle_interval_seconds' => 'integer',
            'guest_shop_commission_percent' => 'integer',
        ];
    }

    public static function current(): self
    {
        return once(fn (): self => static::query()->first() ?? static::query()->create(self::defaults()));
    }

    public function auctionDisplayMode(): string
    {
        return in_array($this->auction_display_mode, ['auto', 'manual'], true)
            ? $this->auction_display_mode
            : 'auto';
    }

    public function auctionShuffleIntervalSeconds(): int
    {
        return max(5, (int) ($this->auction_shuffle_interval_seconds ?: 5));
    }

    public function guestShopCommissionPercent(): int
    {
        return max(0, min(90, (int) ($this->guest_shop_commission_percent ?? 10)));
    }

    public function phoneTel(): string
    {
        $number = preg_replace('/[^\d+]/', '', $this->phone) ?? '';

        return $number !== '' ? 'tel:'.$number : '#';
    }

    public function whatsappUrl(): string
    {
        $digits = preg_replace('/\D+/', '', $this->whatsapp) ?: preg_replace('/\D+/', '', $this->phone);
        $digits = (string) $digits;

        if (Str::startsWith($digits, '0') && Str::length($digits) === 10) {
            $digits = '254'.Str::substr($digits, 1);
        }

        return 'https://wa.me/'.$digits;
    }

    public function mapEmbedUrl(): ?string
    {
        return MapEmbedUrl::embed($this->map_url, $this->address);
    }
}
