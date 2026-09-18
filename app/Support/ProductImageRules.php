<?php

namespace App\Support;

class ProductImageRules
{
    /**
     * @return list<string>
     */
    public static function required(): array
    {
        return [
            'required',
            'image',
            'mimes:jpg,jpeg,png,webp',
            'mimetypes:image/jpeg,image/png,image/webp',
            'max:4096',
            'dimensions:min_width=800,min_height=800,ratio=1/1',
        ];
    }

    /**
     * @return list<string>
     */
    public static function optional(): array
    {
        return [
            'nullable',
            'image',
            'mimes:jpg,jpeg,png,webp',
            'mimetypes:image/jpeg,image/png,image/webp',
            'max:4096',
            'dimensions:min_width=800,min_height=800,ratio=1/1',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(string $attribute = 'imageUpload'): array
    {
        return [
            "{$attribute}.required" => 'Add a clear product photo.',
            "{$attribute}.image" => 'Upload a JPG, PNG, or WebP image.',
            "{$attribute}.mimes" => 'Use JPG, PNG, or WebP only.',
            "{$attribute}.mimetypes" => 'Use JPG, PNG, or WebP only.',
            "{$attribute}.max" => 'Keep the image under 4 MB.',
            "{$attribute}.dimensions" => 'Use a square photo at least 800×800px (1:1). 1024×1024px is recommended.',
        ];
    }

    public static function guidance(): string
    {
        return 'Square 1:1 photos display best. Use JPG, PNG, or WebP, 1024×1024px recommended (minimum 800×800px), max 4 MB. Shop tiles and product pages show a square crop; wide or tall photos will be rejected.';
    }

    public static function acceptAttribute(): string
    {
        return '.jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp';
    }
}
