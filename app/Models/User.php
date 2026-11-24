<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $phone_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property bool $is_admin
 * @property bool $marketing_consent
 * @property Carbon|null $marketing_consented_at
 * @property bool $sms_notifications
 * @property bool $whatsapp_notifications
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'email',
    'phone',
    'password',
    'is_admin',
    'marketing_consent',
    'marketing_consented_at',
    'sms_notifications',
    'whatsapp_notifications',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'marketing_consent' => 'boolean',
            'marketing_consented_at' => 'datetime',
            'sms_notifications' => 'boolean',
            'whatsapp_notifications' => 'boolean',
        ];
    }

    /**
     * @return HasOne<Bidder, $this>
     */
    public function bidder(): HasOne
    {
        return $this->hasOne(Bidder::class);
    }

    /**
     * @return HasOne<CustomerControl, $this>
     */
    public function customerControl(): HasOne
    {
        return $this->hasOne(CustomerControl::class);
    }

    /**
     * @return HasMany<CustomerVerificationDocument, $this>
     */
    public function verificationDocuments(): HasMany
    {
        return $this->hasMany(CustomerVerificationDocument::class)->latest('id');
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->latest();
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeMarketingConsent(Builder $query): Builder
    {
        return $query->where('marketing_consent', true)->where('is_admin', false);
    }

    /**
     * Users with admin panel access via is_admin flag and/or Spatie roles.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeStaff(Builder $query): Builder
    {
        return $query->where(function (Builder $inner): void {
            $inner->where('is_admin', true)->orWhereHas('roles');
        });
    }

    public function canAccessAdmin(): bool
    {
        if ($this->is_admin) {
            return true;
        }

        if ($this->roles()->exists()) {
            return true;
        }

        return $this->getAllPermissions()->contains(
            fn ($permission): bool => $permission->name === 'access.admin',
        );
    }

    public function homePath(): string
    {
        return $this->canAccessAdmin()
            ? route('dashboard', absolute: false)
            : route('account', absolute: false);
    }

    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
