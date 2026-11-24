<?php

namespace App\Services;

use App\Models\PaymentAttempt;
use App\Support\DarajaStkResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class DarajaStkService
{
    public function __construct(
        private readonly AuctionBidService $bids,
        private readonly RedeemBidService $sales,
        private readonly OrderService $orders,
    ) {}

    public function isReady(): bool
    {
        return filled(config('mpesa.consumer_key'))
            && filled(config('mpesa.consumer_secret'))
            && filled(config('mpesa.passkey'))
            && filled(config('mpesa.shortcode'))
            && filled(config('mpesa.callback_url'));
    }

    /**
     * Ask Safaricom to show the M-Pesa PIN prompt for a participation fee.
     * A rejected request fails the attempt. A timeout leaves it open so a late callback can still complete it.
     */
    public function requestPayment(PaymentAttempt $attempt): void
    {
        $phone = (string) ($attempt->request_payload['phone'] ?? '');

        if ($phone === '' || $attempt->amount < 1) {
            throw ValidationException::withMessages([
                'payment' => 'This payment cannot be sent to M-Pesa.',
            ]);
        }

        $credentials = $this->passwordFields();

        try {
            $token = $this->accessToken();
        } catch (ValidationException $exception) {
            $this->failAttempt($attempt, 'M-Pesa could not be reached. Try again.');

            throw $exception;
        }

        try {
            $response = $this->http()
                ->withToken($token)
                ->post('/mpesa/stkpush/v1/processrequest', [
                    ...$credentials,
                    'TransactionType' => 'CustomerPayBillOnline',
                    'Amount' => $attempt->amount,
                    'PartyA' => $phone,
                    'PartyB' => $credentials['BusinessShortCode'],
                    'PhoneNumber' => $phone,
                    'CallBackURL' => config('mpesa.callback_url'),
                    'AccountReference' => $this->accountReference($attempt),
                    'TransactionDesc' => $this->transactionDescription($attempt),
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('daraja.stk.unreachable', ['message' => $exception->getMessage()]);

            throw ValidationException::withMessages([
                'payment_pending' => 'M-Pesa did not respond in time. If a prompt arrived on your phone, enter your PIN and keep this page open.',
            ]);
        }

        $body = $response->json() ?? [];

        if (! $response->successful() || (string) ($body['ResponseCode'] ?? '') !== '0') {
            $message = (string) ($body['errorMessage'] ?? $body['ResponseDescription'] ?? 'M-Pesa rejected the payment prompt.');
            $this->failAttempt($attempt, $message, is_array($body) ? $body : null);

            throw ValidationException::withMessages([
                'payment' => $message,
            ]);
        }

        $payload = $attempt->request_payload ?? [];
        $payload['stk_response'] = $body;

        $attempt->update([
            'checkout_request_id' => $body['CheckoutRequestID'] ?? null,
            'merchant_request_id' => $body['MerchantRequestID'] ?? null,
            'request_payload' => $payload,
        ]);
    }

    /**
     * Ask Safaricom for the STK result when the callback has not arrived yet.
     * A query error leaves the attempt open so the waiting page can try again.
     */
    public function queryPayment(PaymentAttempt $attempt): void
    {
        $attempt->refresh();

        if ($attempt->status !== 'initiated' || blank($attempt->checkout_request_id) || ! $this->isReady()) {
            return;
        }

        if (! Cache::add('daraja.stk.query.'.$attempt->id, true, 8)) {
            return;
        }

        try {
            $response = $this->http()
                ->withToken($this->accessToken())
                ->post('/mpesa/stkpushquery/v1/query', [
                    ...$this->passwordFields(),
                    'CheckoutRequestID' => $attempt->checkout_request_id,
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('daraja.stk.query.unreachable', [
                'attempt_id' => $attempt->id,
                'message' => $exception->getMessage(),
            ]);

            return;
        } catch (ValidationException $exception) {
            Log::warning('daraja.stk.query.unreachable', [
                'attempt_id' => $attempt->id,
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        $decoded = $response->json();
        /** @var array<string, mixed> $body */
        $body = is_array($decoded) ? $decoded : [];

        if (! array_key_exists('ResultCode', $body)) {
            Log::info('daraja.stk.query.pending', [
                'attempt_id' => $attempt->id,
                'error_code' => $body['errorCode'] ?? null,
            ]);

            return;
        }

        $resultCode = (int) $body['ResultCode'];
        $resultDesc = (string) ($body['ResultDesc'] ?? 'Payment was not completed.');
        $stored = [
            'result_code' => $resultCode,
            'result_desc' => $resultDesc,
            'source' => 'stk_query',
        ];

        $this->applyResult($attempt, $resultCode, $resultDesc, $stored);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleCallback(array $payload): void
    {
        try {
            $callback = $payload['Body']['stkCallback'] ?? null;

            if (! is_array($callback)) {
                Log::warning('daraja.stk.callback.unrecognised');

                return;
            }

            $checkoutRequestId = (string) ($callback['CheckoutRequestID'] ?? '');
            $attempt = $checkoutRequestId === ''
                ? null
                : PaymentAttempt::query()->where('checkout_request_id', $checkoutRequestId)->first();

            if ($attempt === null) {
                Log::info('daraja.stk.callback.unknown', ['checkout_request_id' => $checkoutRequestId]);

                return;
            }

            if (! array_key_exists('ResultCode', $callback)) {
                Log::info('daraja.stk.callback.pending', [
                    'attempt_id' => $attempt->id,
                    'checkout_request_id' => $checkoutRequestId,
                ]);

                return;
            }

            $resultCode = (int) $callback['ResultCode'];
            $resultDesc = (string) ($callback['ResultDesc'] ?? 'Payment was not completed.');
            $metadata = $this->metadata($callback);
            $stored = [
                'result_code' => $resultCode,
                'result_desc' => $resultDesc,
                'metadata' => $metadata,
            ];

            if (DarajaStkResult::classify($resultCode) === DarajaStkResult::SUCCESS) {
                $receipt = isset($metadata['MpesaReceiptNumber']) ? (string) $metadata['MpesaReceiptNumber'] : null;

                if ($receipt !== null) {
                    $attempt->update(['mpesa_receipt' => $receipt]);
                }
            }

            $this->applyResult($attempt->fresh(), $resultCode, $resultDesc, $stored);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    private function applyResult(PaymentAttempt $attempt, int $resultCode, string $resultDesc, array $stored): void
    {
        $outcome = DarajaStkResult::classify($resultCode);

        if ($outcome === DarajaStkResult::SUCCESS) {
            if ($this->isShopCheckout($attempt)) {
                $this->orders->confirmShopPayment($attempt->fresh(), $stored);

                return;
            }

            if ($this->isSuccessfulBuy($attempt)) {
                $this->sales->confirmSalePayment($attempt->fresh(), $stored);

                return;
            }

            $this->bids->markSucceeded($attempt->fresh(), $stored);

            return;
        }

        if ($outcome === DarajaStkResult::PENDING) {
            Log::info('daraja.stk.pending', [
                'attempt_id' => $attempt->id,
                'result_code' => $resultCode,
                'result_desc' => $resultDesc,
            ]);

            return;
        }

        $this->failAttempt($attempt, $resultDesc, $stored);
    }

    private function isSuccessfulBuy(PaymentAttempt $attempt): bool
    {
        $purpose = (string) ($attempt->request_payload['purpose'] ?? '');

        return in_array($purpose, ['successful_buy', 'sale_redemption_payment'], true);
    }

    private function isShopCheckout(PaymentAttempt $attempt): bool
    {
        return (string) ($attempt->request_payload['purpose'] ?? '') === 'shop_checkout';
    }

    private function transactionDescription(PaymentAttempt $attempt): string
    {
        if ($this->isShopCheckout($attempt)) {
            return 'Shop order';
        }

        return $this->isSuccessfulBuy($attempt) ? 'Winning buy' : 'Participation';
    }

    /**
     * @param  array<string, mixed>|null  $responsePayload
     */
    private function failAttempt(PaymentAttempt $attempt, string $message, ?array $responsePayload = null): void
    {
        if ($this->isShopCheckout($attempt)) {
            $this->orders->markShopPaymentFailed($attempt, $message, $responsePayload);

            return;
        }

        if ($this->isSuccessfulBuy($attempt)) {
            $this->sales->markSalePaymentFailed($attempt, $message, $responsePayload);

            return;
        }

        $this->bids->markFailed($attempt, $message, $responsePayload);
    }

    /**
     * @param  array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    private function metadata(array $callback): array
    {
        $items = $callback['CallbackMetadata']['Item'] ?? [];
        $metadata = [];

        if (! is_array($items)) {
            return $metadata;
        }

        foreach ($items as $item) {
            if (is_array($item) && isset($item['Name'])) {
                $metadata[(string) $item['Name']] = $item['Value'] ?? null;
            }
        }

        return $metadata;
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
                'payment' => 'M-Pesa could not be reached. Try again.',
            ]);
        }

        $token = (string) $response->json('access_token');

        if (! $response->successful() || $token === '') {
            throw ValidationException::withMessages([
                'payment' => 'M-Pesa could not be reached. Try again.',
            ]);
        }

        $ttl = max(60, ((int) $response->json('expires_in', 3600)) - 60);
        Cache::put($cacheKey, $token, $ttl);

        return $token;
    }

    /**
     * @return array{BusinessShortCode: string, Password: string, Timestamp: string}
     */
    private function passwordFields(): array
    {
        $timestamp = now()->timezone('Africa/Nairobi')->format('YmdHis');
        $shortcode = (string) config('mpesa.shortcode');

        return [
            'BusinessShortCode' => $shortcode,
            'Password' => base64_encode($shortcode.config('mpesa.passkey').$timestamp),
            'Timestamp' => $timestamp,
        ];
    }

    private function accountReference(PaymentAttempt $attempt): string
    {
        $reference = preg_replace('/[^A-Za-z0-9]/', '', $attempt->reference) ?? '';

        return substr($reference, 0, 12);
    }

    private function http(): PendingRequest
    {
        $request = Http::baseUrl((string) config('mpesa.base_url'))
            ->acceptJson()
            ->timeout((int) config('mpesa.timeout'))
            ->connectTimeout(5);

        // PHP on Windows does not use the Windows certificate store unless asked.
        // Without this, Safaricom's certificate chain is rejected on this machine.
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
