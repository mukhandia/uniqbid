<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'step_number',
    'title_en',
    'title_sw',
    'description_en',
    'description_sw',
    'sort_order',
])]
class HowItWorksStep extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'step_number' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function titleFor(string $locale): string
    {
        return $locale === 'sw' && filled($this->title_sw)
            ? $this->title_sw
            : $this->title_en;
    }

    public function descriptionFor(string $locale): string
    {
        return $locale === 'sw' && filled($this->description_sw)
            ? $this->description_sw
            : $this->description_en;
    }
}
