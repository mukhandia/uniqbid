<?php

namespace App\Support;

class Money
{
    /**
     * Format an amount in Kenya Shillings.
     */
    public static function kes(int|float $amount): string
    {
        return 'KES '.number_format((int) $amount, 0, '.', ',');
    }
}
