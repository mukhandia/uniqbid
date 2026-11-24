<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'legal_section_id',
    'reference',
    'title',
    'body',
    'sort_order',
])]
class LegalClause extends Model
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
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<LegalSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(LegalSection::class, 'legal_section_id');
    }
}
