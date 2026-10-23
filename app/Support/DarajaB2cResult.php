<?php

namespace App\Support;

/**
 * Safaricom does not publish one complete B2C result-code list.
 * Only codes with a settled meaning close a payout. Every other code stays open
 * until a later result says the money was sent or the payout ended.
 */
final class DarajaB2cResult
{
    public const SUCCESS = 'success';

    public const FAILED = 'failed';

    public const PENDING = 'pending';

    /**
     * This payout is finished and no money was sent.
     *
     * @var array<int, string>
     */
    private const FAILURES = [
        1 => 'The M-Pesa utility account does not have enough funds.',
        2 => 'The payout is below the M-Pesa minimum.',
        3 => 'The payout is above the M-Pesa maximum.',
        4 => 'The payout would exceed the daily limit.',
        8 => 'The payout would exceed the recipient balance limit.',
        21 => 'This M-Pesa user is not allowed to send payouts.',
        2001 => 'The M-Pesa initiator details were rejected.',
        2040 => 'That phone is not a registered M-Pesa customer.',
        8006 => 'The M-Pesa security credential is locked.',
    ];

    public static function classify(int $code): string
    {
        if ($code === 0) {
            return self::SUCCESS;
        }

        if (array_key_exists($code, self::FAILURES)) {
            return self::FAILED;
        }

        return self::PENDING;
    }
}
