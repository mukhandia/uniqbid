<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'amount',
    'is_unique',
    'sort_order',
])]
class HowItWorksExampleRow extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_unique' => true,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'is_unique' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function isSuccessfulBuy(int $lowestUniqueAmount): bool
    {
        return $this->is_unique && $this->amount === $lowestUniqueAmount;
    }
}
