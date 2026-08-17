<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'participation_fee_limit',
    'limit_period',
    'self_excluded_until',
    'verification_status',
    'verification_requested_at',
])]
class CustomerControl extends Model
{
    protected function casts(): array
    {
        return [
            'participation_fee_limit' => 'integer',
            'self_excluded_until' => 'datetime',
            'verification_requested_at' => 'datetime',
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
