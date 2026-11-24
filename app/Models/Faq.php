<?php

namespace App\Models;

use Database\Factories\FaqFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'category',
    'question_en',
    'answer_en',
    'question_sw',
    'answer_sw',
    'sort_order',
    'is_published',
])]
class Faq extends Model
{
    /** @use HasFactory<FaqFactory> */
    use HasFactory;

    public const CATEGORY_GENERAL = 'general';

    public const CATEGORY_BIDDING = 'bidding';

    public const CATEGORY_SHOP = 'shop';

    public const CATEGORY_ACCOUNT = 'account';

    /**
     * @return array<string, string>
     */
    public static function categories(): array
    {
        return [
            self::CATEGORY_GENERAL => 'General',
            self::CATEGORY_BIDDING => 'Bidding',
            self::CATEGORY_SHOP => 'Shop',
            self::CATEGORY_ACCOUNT => 'Account',
        ];
    }

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'category' => self::CATEGORY_GENERAL,
        'sort_order' => 0,
        'is_published' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public function categoryLabel(): string
    {
        return self::categories()[$this->category] ?? ucfirst($this->category);
    }

    public function questionFor(string $locale): string
    {
        return $locale === 'sw' && filled($this->question_sw)
            ? $this->question_sw
            : $this->question_en;
    }

    public function answerFor(string $locale): string
    {
        return $locale === 'sw' && filled($this->answer_sw)
            ? $this->answer_sw
            : $this->answer_en;
    }

    /**
     * @param  Builder<Faq>  $query
     * @return Builder<Faq>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * @param  Builder<Faq>  $query
     * @return Builder<Faq>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
