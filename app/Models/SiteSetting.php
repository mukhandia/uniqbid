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
])]
class SiteSetting extends Model
{
    /**
     * @return array<string, string|null>
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
        ];
    }

    public static function current(): self
    {
        return once(fn (): self => static::query()->first() ?? static::query()->create(self::defaults()));
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
