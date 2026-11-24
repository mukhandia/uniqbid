<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class MobileSasaSmsService
{
    public function isReady(): bool
    {
        return filled(config('mobilesasa.token'))
            && filled(config('mobilesasa.sender_id'))
            && filled(config('mobilesasa.base_url'));
    }

    /**
     * Queue one SMS with Mobile Sasa. Accepted means the network has the request, not that the handset got it.
     *
     * @return array{message_id: string, cost: int|null}
     */
    public function send(string $phone, string $message, string $trackingId): array
    {
        if (! $this->isReady()) {
            throw ValidationException::withMessages([
                'phone' => 'SMS is not connected.',
            ]);
        }

        try {
            $response = $this->http()
                ->withToken((string) config('mobilesasa.token'))
                ->post('/v2/send/message', [
                    'senderID' => (string) config('mobilesasa.sender_id'),
                    'phone' => $phone,
                    'message' => $message,
                    'trackingId' => $trackingId,
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('mobilesasa.unreachable', ['message' => $exception->getMessage()]);

            throw ValidationException::withMessages([
                'phone' => 'SMS could not be reached. Try again.',
            ]);
        }

        $body = $response->json() ?? [];
        $accepted = $response->successful()
            && ($body['status'] ?? false) === true
            && (string) ($body['responseCode'] ?? '') === '0200';

        if (! $accepted) {
            $reason = (string) ($body['message'] ?? 'SMS was not accepted.');

            Log::warning('mobilesasa.send.rejected', [
                'phone' => $phone,
                'tracking_id' => $trackingId,
                'response_code' => $body['responseCode'] ?? null,
                'message' => $reason,
            ]);

            throw ValidationException::withMessages([
                'phone' => $reason,
            ]);
        }

        $messageId = (string) ($body['messageId'] ?? '');

        Log::info('mobilesasa.send.accepted', [
            'phone' => $phone,
            'tracking_id' => $trackingId,
            'message_id' => $messageId,
            'cost' => $body['cost'] ?? null,
        ]);

        return [
            'message_id' => $messageId,
            'cost' => isset($body['cost']) ? (int) $body['cost'] : null,
        ];
    }

    private function http(): PendingRequest
    {
        $request = Http::baseUrl((string) config('mobilesasa.base_url'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('mobilesasa.timeout'))
            ->connectTimeout(5);

        if (PHP_OS_FAMILY === 'Windows' && defined('CURLSSLOPT_NATIVE_CA')) {
            $request = $request->withOptions([
                'curl' => [
                    CURLOPT_SSL_OPTIONS => CURLSSLOPT_NATIVE_CA,
                ],
            ]);
        }

        return $request;
    }
}
