<?php

namespace App\Support;

class PlaceholderImage
{
    /**
     * Build a consistent SVG data-URL placeholder matching the Lovable POC.
     */
    public static function make(string $label, string $background = '#1a3a6b', string $foreground = '#ffffff'): string
    {
        $safeLabel = htmlspecialchars($label, ENT_QUOTES | ENT_XML1, 'UTF-8');

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 600"><defs><linearGradient id="g" x1="0" x2="1" y1="0" y2="1"><stop offset="0" stop-color="{$background}"/><stop offset="1" stop-color="#0a1930"/></linearGradient></defs><rect width="600" height="600" fill="url(#g)"/><text x="50%" y="50%" text-anchor="middle" dominant-baseline="middle" font-family="Inter,Arial" font-size="36" font-weight="700" fill="{$foreground}">{$safeLabel}</text></svg>
SVG;

        return 'data:image/svg+xml;utf8,'.rawurlencode($svg);
    }
}
