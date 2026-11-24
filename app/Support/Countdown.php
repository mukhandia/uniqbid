<?php

namespace App\Support;

use Carbon\CarbonInterface;

class Countdown
{
    /**
     * Format remaining time as total hours, minutes, and seconds.
     */
    public static function label(?CarbonInterface $endsAt, ?CarbonInterface $now = null): string
    {
        if ($endsAt === null) {
            return 'Closed';
        }

        $now ??= now();
        $diffSeconds = max(0, $endsAt->getTimestamp() - $now->getTimestamp());

        if ($diffSeconds === 0) {
            return 'Closed';
        }

        $hours = intdiv($diffSeconds, 3600);
        $minutes = intdiv($diffSeconds % 3600, 60);
        $seconds = $diffSeconds % 60;

        return sprintf('%dh : %02dm : %02ds', $hours, $minutes, $seconds);
    }
}
