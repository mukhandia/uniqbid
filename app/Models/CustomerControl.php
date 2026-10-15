<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $participation_fee_limit
 * @property string|null $limit_period
 * @property Carbon|null $self_excluded_until
 * @property string $verification_status
 * @property Carbon|null $verification_requested_at
 * @property Carbon|null $verification_submitted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'participation_fee_limit',
    'limit_period',
    'self_excluded_until',
    'verification_status',
    'verification_requested_at',
    'verification_submitted_at',
])]
class CustomerControl extends Model
{
    protected function casts(): array
    {
        return [
            'participation_fee_limit' => 'integer',
            'self_excluded_until' => 'datetime',
            'verification_requested_at' => 'datetime',
            'verification_submitted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
