<?php

namespace App\Services;

use App\Models\MobileLoginCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class MobileOtpService
{
    public function __construct(private readonly MobileSasaSmsService $sms) {}

    /**
     * Create a six-digit code. When Mobile Sasa is connected, the code is sent by SMS and an empty
     * string is returned so the page does not show it. Otherwise the code is returned for the
     * on-screen placeholder used in tests and offline local work.
     */
    public function issue(string $phone): string
    {
        $code = (string) random_int(100000, 999999);

        MobileLoginCode::query()
            ->where('phone', $phone)
            ->whereNull('consumed_at')
            ->delete();

        MobileLoginCode::query()->create([
            'phone' => $phone,
            'code' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
        ]);

        if ($this->sms->isReady()) {
            try {
                $this->sms->send(
                    phone: $phone,
                    message: "UniqBid: Your code is {$code}. It expires in 10 minutes.",
                    trackingId: 'OTP-'.str()->upper(str()->random(12)),
                );
            } catch (ValidationException $exception) {
                MobileLoginCode::query()
                    ->where('phone', $phone)
                    ->whereNull('consumed_at')
                    ->delete();

                throw $exception;
            }

            return '';
        }

        Log::info('mobile_otp_placeholder', [
            'to' => $phone,
            'code' => $code,
            'expires_at' => now()->addMinutes(10)->toIso8601String(),
        ]);

        return $code;
    }

    public function verify(string $phone, string $code): void
    {
        $loginCode = MobileLoginCode::query()
            ->where('phone', $phone)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if ($loginCode === null || ! Hash::check($code, $loginCode->code)) {
            throw ValidationException::withMessages([
                'code' => 'That code is invalid or has expired. Request a new code and try again.',
            ]);
        }

        $loginCode->update(['consumed_at' => now()]);
    }
}
