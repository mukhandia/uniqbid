<?php

namespace App\Support;

/**
 * Safaricom does not publish one complete STK result-code list.
 * Only codes with a settled meaning close a payment. Every other code stays open
 * until a later callback or status check says the money moved or the prompt ended.
 *
 * Codes that stay open include 4999 (still processing), 1001 (another M-Pesa session),
 * 17 (internal failure) and 26 (system busy), plus any code not listed as a failure.
 */
final class DarajaStkResult
{
    public const SUCCESS = 'success';

    public const FAILED = 'failed';

    public const PENDING = 'pending';

    /**
     * This prompt is finished and no money was taken.
     *
     * @var array<int, string>
     */
    private const FAILURES = [
        1 => 'The customer did not have enough M-Pesa balance.',
        1019 => 'The prompt expired before it was completed.',
        1025 => 'Safaricom could not send the prompt.',
        1032 => 'The customer cancelled the prompt.',
        1037 => 'The phone did not respond to the prompt.',
        2001 => 'The M-Pesa PIN was rejected.',
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
