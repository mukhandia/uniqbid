<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

final class AuditLogger
{
    /**
     * Record a domain audit event. Failures are swallowed so business flows are never blocked.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function log(
        string $logName,
        string $description,
        string $event,
        ?Model $subject = null,
        array $properties = [],
    ): void {
        if (! function_exists('activity')) {
            return;
        }

        try {
            $logger = activity($logName)
                ->event($event)
                ->withProperties($properties);

            if ($subject !== null) {
                $logger->performedOn($subject);
            }

            $logger->log($description);
        } catch (\Throwable) {
            // Activity logging is optional; do not break the primary action.
        }
    }
}
