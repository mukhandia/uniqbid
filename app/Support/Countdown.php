<?php

namespace App\Support;

use Carbon\CarbonInterface;

class Countdown
{
    /**
     * Format remaining time as days, hours, and minutes (no seconds).
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

        $days = intdiv($diffSeconds, 86400);
        $hours = intdiv($diffSeconds % 86400, 3600);
        $minutes = intdiv($diffSeconds % 3600, 60);

        return sprintf('%dd : %02dh : %02dm', $days, $hours, $minutes);
    }
}
