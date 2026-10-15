<?php

use App\Models\Bidder;
use App\Models\User;
use App\Services\AuctionBidService;
use App\Services\MobileOtpService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new
#[Layout('layouts.auth')]
#[Title('Sign in with mobile: UniqBid')]
class extends Component {
    #[Url(as: 'redirect')]
    public string $redirectTo = '';

    public string $phone = '';

    public string $code = '';

    public string $name = '';

    public string $email = '';

    public string $step = 'phone';

    public bool $requiresProfile = false;

    public string $normalizedPhone = '';

    public string $placeholderCode = '';

    public bool $phoneVerified = false;

    public function requestCode(): void
    {
        $this->validate([
            'phone' => ['required', 'string'],
        ], [
            'phone.required' => 'Enter a valid Kenyan mobile number.',
        ]);

        $this->normalizedPhone = app(AuctionBidService::class)->normalizeKenyanPhone($this->phone);
        $this->requiresProfile = ! User::query()->where('phone', $this->normalizedPhone)->exists();
        $this->placeholderCode = app(MobileOtpService::class)->issue($this->normalizedPhone);
        $this->phoneVerified = false;
        $this->code = '';
        $this->step = 'code';
        $this->resetErrorBag();
    }

    public function verifyCode(): void
    {
        $this->validate([
            'code' => ['required', 'digits:6'],
        ]);

        app(MobileOtpService::class)->verify($this->normalizedPhone, $this->code);
        $this->phoneVerified = true;
        $this->resetErrorBag();

        if ($this->requiresProfile) {
            $this->step = 'profile';

            return;
        }

        $this->completeLogin();
    }

    public function completeRegistration(): void
    {
        abort_unless($this->phoneVerified, 403);

        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique(User::class)],
        ]);

        $this->completeLogin();
    }

    private function completeLogin(): void
    {
        if (! $this->phoneVerified) {
            app(MobileOtpService::class)->verify($this->normalizedPhone, $this->code);
            $this->phoneVerified = true;
        }

        $user = User::query()->firstOrCreate(
            ['phone' => $this->normalizedPhone],
            [
                'name' => $this->name,
                'email' => filled($this->email) ? $this->email : null,
                'password' => Hash::make(str()->random(40)),
                'phone_verified_at' => now(),
            ],
        );

        $user->forceFill(['phone_verified_at' => now()])->save();

        $bidder = Bidder::query()->firstOrNew(['phone' => $this->normalizedPhone]);
        $bidder->fill([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email ?? $bidder->email,
        ]);

        if (! $bidder->exists) {
            $bidder->fill([
                'marketing_consent' => (bool) $user->marketing_consent,
                'marketing_consented_at' => $user->marketing_consented_at,
            ]);
        }

        $bidder->save();

        Auth::login($user);
        session()->regenerate();

        $this->redirectIntended(default: $this->redirectDestination(), navigate: true);
    }

    private function redirectDestination(): string
    {
        if (
            str_starts_with($this->redirectTo, '/')
            && ! str_starts_with($this->redirectTo, '//')
            && ! str_contains($this->redirectTo, '\\')
        ) {
            return $this->redirectTo;
        }

        return route('account', absolute: false);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header :title="__('Sign in with your mobile')" :description="__('Use a one-time code. No password is needed.')" />

    @if ($step === 'phone')
        <form wire:submit="requestCode" class="flex flex-col gap-6">
            <flux:input wire:model="phone" :label="__('Kenyan mobile number')" type="tel" placeholder="0712 345 678 or 0112 345 678" autofocus autocomplete="tel" />
            <flux:button type="submit" variant="primary" class="w-full">
                Send code
            </flux:button>
        </form>
    @elseif ($step === 'code')
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950">
            <div class="font-semibold">SMS placeholder</div>
            <p class="mt-1">SMS delivery will be connected later. For this staging flow, enter <span class="font-mono font-bold">{{ $placeholderCode }}</span>.</p>
        </div>

        <form wire:submit="verifyCode" class="flex flex-col gap-6">
            <flux:input wire:model="code" :label="__('Six-digit code')" inputmode="numeric" maxlength="6" autocomplete="one-time-code" autofocus />
            <flux:error name="code" />
            <flux:button type="submit" variant="primary" class="w-full">
                Continue
            </flux:button>
            <flux:button type="button" wire:click="requestCode" variant="ghost" class="w-full">
                Send a new code
            </flux:button>
        </form>
    @else
        <form wire:submit="completeRegistration" class="flex flex-col gap-5">
            <div class="rounded-lg bg-surface p-3 text-sm text-muted-foreground">
                Your mobile number is verified. Complete your profile once to continue.
            </div>
            <flux:input wire:model="name" :label="__('Full name')" autocomplete="name" autofocus />
            <flux:input wire:model="email" :label="__('Email address (optional)')" type="email" autocomplete="email" />
            <flux:button type="submit" variant="primary" class="w-full">
                Create account
            </flux:button>
        </form>
    @endif
</div>
