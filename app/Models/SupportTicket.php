<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string $subject
 * @property string $message
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'email',
    'phone',
    'subject',
    'message',
    'status',
])]
class SupportTicket extends Model
{
    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => 'open',
    ];
}
