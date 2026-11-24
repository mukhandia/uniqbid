<?php

namespace App\Support;

class CustomerSearch
{
    /**
     * LIKE patterns for a typed phone number, so "0712 345", "+254712" and "712345" all match numbers stored as 2547XXXXXXXX.
     *
     * @return list<string>
     */
    public static function phonePatterns(string $term): array
    {
        $digits = preg_replace('/\D/', '', $term) ?? '';

        if (strlen($digits) < 3) {
            return [];
        }

        $patterns = ['%'.$digits.'%'];

        if (str_starts_with($digits, '0')) {
            $patterns[] = '%254'.substr($digits, 1).'%';
        }

        return $patterns;
    }
}
