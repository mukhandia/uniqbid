<?php

namespace App\Services;

use App\Models\PaymentAttempt;
use App\Support\DarajaB2cResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class DarajaB2cService
{
    public function __construct(
        private readonly RedeemBidService $redeems,
        private readonly GuestSellerPayoutService $sellerPayouts,
        private readonly ParticipationRefundService $feeRefunds,
    ) {}

    public function isReady(): bool
    {
        return filled(config('mpesa.consumer_key'))
            && filled(config('mpesa.consumer_secret'))
            && filled(config('mpesa.initiator_name'))
            && filled(config('mpesa.security_credential'))
            && filled(config('mpesa.b2c_shortcode'))
            && filled(config('mpesa.b2c_result_url'))
            && filled(config('mpesa.b2c_timeout_url'));
    }

    /**
     * Ask Safaricom to send a redeem payout to the bidder's phone.
     * The acknowledgement only means the request was accepted. The result callback decides the payout.
     */
    public function requestPayout(PaymentAttempt $attempt): void
    {
        $phone = (string) ($attempt->request_payload['phone'] ?? '');

        if ($phone === '' || $attempt->amount < 10 || ! $this->isReady()) {
            throw ValidationException::withMessages([
                'redeem' => 'This payout cannot be sent to M-Pesa.',
            ]);
        }

        $attempt->refresh();

        if ($attempt->status !== 'initiated' || filled($attempt->checkout_request_id)) {
            return;
        }

        $token = $this->accessToken();
        [$remarks, $occasion] = $this->b2cCopy((string) ($attempt->request_payload['purpose'] ?? ''));

        try {
            $response = $this->http()
                ->withToken($token)
                ->post('/mpesa/b2c/v3/paymentrequest', [
                    'OriginatorConversationID' => $attempt->reference,
                    'InitiatorName' => (string) config('mpesa.initiator_name'),
                    'SecurityCredential' => (string) config('mpesa.security_credential'),
                    'CommandID' => 'BusinessPayment',
                    'Amount' => $attempt->amount,
                    'PartyA' => (string) config('mpesa.b2c_shortcode'),
                    'PartyB' => $phone,
                    'Remarks' => $remarks,
                    'QueueTimeOutURL' => (string) config('mpesa.b2c_timeout_url'),
                    'ResultURL' => (string) config('mpesa.b2c_result_url'),
                    'Occassion' => $occasion,
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('daraja.b2c.unreachable', ['message' => $exception->getMessage()]);

            throw ValidationException::withMessages([
                'payment_pending' => 'M-Pesa did not respond in time. Keep this page open.',
            ]);
        }

        $body = $response->json() ?? [];
        $payload = $attempt->request_payload ?? [];
        $payload['b2c_response'] = is_array($body) ? $body : [];

        if (! $response->successful() || (string) ($body['ResponseCode'] ?? '') !== '0') {
            $message = (string) ($body['errorMessage'] ?? $body['ResponseDescription'] ?? 'M-Pesa rejected the payout.');
            $duplicate = str_contains(strtolower($message), 'duplicate')
                || str_contains((string) ($body['errorCode'] ?? ''), '500.002.1001');

            $attempt->update(['request_payload' => $payload]);

            if ($duplicate) {
                Log::info('daraja.b2c.duplicate', ['attempt_id' => $attempt->id, 'message' => $message]);

                return;
            }

            $this->failAttempt($attempt, $message, is_array($body) ? $body : null);

            throw ValidationException::withMessages([
                'redeem' => $message,
            ]);
        }

        $attempt->update([
            'checkout_request_id' => $body['ConversationID'] ?? null,
            'merchant_request_id' => $body['OriginatorConversationID'] ?? $attempt->reference,
            'request_payload' => $payload,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleResult(array $payload): void
    {
        try {
            $result = $payload['Result'] ?? null;

            if (! is_array($result)) {
                Log::info('daraja.b2c.result.ignored');

                return;
            }

            $attempt = $this->findAttempt(
                (string) ($result['OriginatorConversationID'] ?? ''),
                (string) ($result['ConversationID'] ?? ''),
            );

            if ($attempt === null) {
                Log::info('daraja.b2c.result.unknown', [
                    'originator' => $result['OriginatorConversationID'] ?? null,
                ]);

                return;
            }

            if (! array_key_exists('ResultCode', $result) || ! is_numeric($result['ResultCode'])) {
                Log::info('daraja.b2c.result.pending', ['attempt_id' => $attempt->id]);

                return;
            }

            $resultCode = (int) $result['ResultCode'];
            $resultDesc = (string) ($result['ResultDesc'] ?? 'The payout was not completed.');
            $parameters = $this->parameters($result);
            $stored = [
                'result_code' => $resultCode,
                'result_desc' => $resultDesc,
                'parameters' => $parameters,
            ];
            $outcome = DarajaB2cResult::classify($resultCode);

            if ($outcome === DarajaB2cResult::SUCCESS) {
                $receipt = $parameters['TransactionReceipt'] ?? $result['TransactionID'] ?? null;

                if (is_scalar($receipt) && (string) $receipt !== '') {
                    $attempt->update(['mpesa_receipt' => (string) $receipt]);
                }

                $this->confirmAttempt($attempt->fresh(), $stored);

                return;
            }

            if ($outcome === DarajaB2cResult::PENDING) {
                Log::info('daraja.b2c.pending', [
                    'attempt_id' => $attempt->id,
                    'result_code' => $resultCode,
                    'result_desc' => $resultDesc,
                ]);

                return;
            }

            $this->failAttempt($attempt, $resultDesc, $stored);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * A timeout means Safaricom has not finished. The payout stays open for a later result.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleTimeout(array $payload): void
    {
        Log::info('daraja.b2c.timeout', ['payload' => $payload]);
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    private function confirmAttempt(PaymentAttempt $attempt, array $stored): void
    {
        $purpose = (string) ($attempt->request_payload['purpose'] ?? '');

        if ($purpose === 'seller_payout') {
            $this->sellerPayouts->confirmPayout($attempt, $stored);

            return;
        }

        if ($purpose === 'participation_refund') {
            $this->feeRefunds->confirmRefund($attempt, $stored);

            return;
        }

        $this->redeems->confirmPayout($attempt, $stored);
    }

    /**
     * @param  array<string, mixed>|null  $responsePayload
     */
    private function failAttempt(PaymentAttempt $attempt, string $message, ?array $responsePayload = null): void
    {
        $purpose = (string) ($attempt->request_payload['purpose'] ?? '');

        if ($purpose === 'seller_payout') {
            $this->sellerPayouts->markPayoutFailed($attempt, $message, $responsePayload);

            return;
        }

        if ($purpose === 'participation_refund') {
            $this->feeRefunds->markRefundFailed($attempt, $message, $responsePayload);

            return;
        }

        $this->redeems->markPayoutFailed($attempt, $message, $responsePayload);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function b2cCopy(string $purpose): array
    {
        return match ($purpose) {
            'seller_payout' => ['Seller payout', 'Seller'],
            'participation_refund' => ['Fee refund', 'Refund'],
            default => ['Redeem payout', 'Redeem'],
        };
    }

    private function findAttempt(string $originator, string $conversation): ?PaymentAttempt
    {
        if ($originator === '' && $conversation === '') {
            return null;
        }

        return PaymentAttempt::query()
            ->where('provider', 'mpesa_daraja_b2c')
            ->where(function (Builder $query) use ($originator, $conversation): void {
                if ($originator !== '') {
                    $query->orWhere('reference', $originator);
                }

                if ($conversation !== '') {
                    $query->orWhere('checkout_request_id', $conversation);
                }
            })
            ->first();
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function parameters(array $result): array
    {
        $items = $result['ResultParameters']['ResultParameter'] ?? [];
        $parameters = [];

        if (! is_array($items)) {
            return $parameters;
        }

        foreach ($items as $item) {
            if (is_array($item) && isset($item['Key'])) {
                $parameters[(string) $item['Key']] = $item['Value'] ?? null;
            }
        }

        return $parameters;
    }

    private function accessToken(): string
    {
        $cacheKey = 'daraja.token.'.sha1((string) config('mpesa.consumer_key'));
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = $this->http()
                ->withBasicAuth((string) config('mpesa.consumer_key'), (string) config('mpesa.consumer_secret'))
                ->get('/oauth/v1/generate', ['grant_type' => 'client_credentials']);
        } catch (ConnectionException $exception) {
            Log::warning('daraja.oauth.unreachable', ['message' => $exception->getMessage()]);

            throw ValidationException::withMessages([
                'redeem' => 'M-Pesa could not be reached. Try again.',
            ]);
        }

        $token = (string) $response->json('access_token');

        if (! $response->successful() || $token === '') {
            throw ValidationException::withMessages([
                'redeem' => 'M-Pesa could not be reached. Try again.',
            ]);
        }

        $ttl = max(60, ((int) $response->json('expires_in', 3600)) - 60);
        Cache::put($cacheKey, $token, $ttl);

        return $token;
    }

    private function http(): PendingRequest
    {
        $request = Http::baseUrl((string) config('mpesa.base_url'))
            ->acceptJson()
            ->timeout((int) config('mpesa.timeout'))
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
