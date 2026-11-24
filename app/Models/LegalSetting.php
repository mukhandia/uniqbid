<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'version',
    'effective_date',
])]
class LegalSetting extends Model
{
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'version' => '2.1',
            'effective_date' => '1 July 2026',
        ]);
    }
}
