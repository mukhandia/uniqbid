<?php

use App\Models\Bidder;
use App\Models\MobileLoginCode;
use App\Models\User;
use Livewire\Livewire;

test('new customers can register through the mobile OTP flow', function () {
    $component = Livewire::test('pages::auth.mobile-login')
        ->set('phone', '0712 345 678')
        ->call('requestCode')
        ->assertSet('step', 'code');

    $code = $component->get('placeholderCode');

    $component
        ->set('code', $code)
        ->call('verifyCode')
        ->assertSet('step', 'profile')
        ->set('name', 'Jane Bidder')
        ->set('email', 'jane@example.com')
        ->call('completeRegistration')
        ->assertRedirect(route('account', absolute: false));

    $user = User::query()->where('phone', '254712345678')->firstOrFail();

    expect($user->name)->toBe('Jane Bidder')
        ->and($user->email)->toBe('jane@example.com')
        ->and($user->phone_verified_at)->not->toBeNull()
        ->and(Bidder::query()->where('user_id', $user->id)->exists())->toBeTrue()
        ->and(MobileLoginCode::query()->where('phone', $user->phone)->whereNotNull('consumed_at')->exists())->toBeTrue();

    $this->assertAuthenticatedAs($user);
});

test('new customers can register with a Safaricom 01 mobile number', function () {
    $component = Livewire::test('pages::auth.mobile-login')
        ->set('phone', '0112 345 678')
        ->call('requestCode')
        ->assertHasNoErrors()
        ->assertSet('step', 'code')
        ->assertSet('normalizedPhone', '254112345678');

    $component
        ->set('code', $component->get('placeholderCode'))
        ->call('verifyCode')
        ->assertSet('step', 'profile')
        ->set('name', 'Safaricom One')
        ->set('email', 'one@example.com')
        ->call('completeRegistration')
        ->assertRedirect(route('account', absolute: false));

    $user = User::query()->where('phone', '254112345678')->firstOrFail();

    expect($user->name)->toBe('Safaricom One');
    $this->assertAuthenticatedAs($user);
});

test('returning customers can sign in using their mobile number', function () {
    $user = User::factory()->create([
        'phone' => '254712345678',
    ]);

    $component = Livewire::test('pages::auth.mobile-login')
        ->set('phone', '+254 712 345 678')
        ->call('requestCode')
        ->assertSet('requiresProfile', false);

    $component
        ->set('code', $component->get('placeholderCode'))
        ->call('verifyCode')
        ->assertRedirect(route('account', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('mobile login returns a customer to a permitted sale URL', function () {
    $user = User::factory()->create([
        'phone' => '254712345678',
    ]);

    $component = Livewire::test('pages::auth.mobile-login')
        ->set('redirectTo', '/auctions/example-sale')
        ->set('phone', '0712 345 678')
        ->call('requestCode');

    $component
        ->set('code', $component->get('placeholderCode'))
        ->call('verifyCode')
        ->assertRedirect('/auctions/example-sale');

    $this->assertAuthenticatedAs($user);
});

test('mobile login rejects an invalid code', function () {
    User::factory()->create([
        'phone' => '254712345678',
    ]);

    Livewire::test('pages::auth.mobile-login')
        ->set('phone', '0712 345 678')
        ->call('requestCode')
        ->set('code', '000000')
        ->call('verifyCode')
        ->assertHasErrors('code');
});

test('new customers must enter a valid code before the profile step', function () {
    Livewire::test('pages::auth.mobile-login')
        ->set('phone', '0712 345 678')
        ->call('requestCode')
        ->set('code', '000000')
        ->call('verifyCode')
        ->assertHasErrors('code')
        ->assertSet('step', 'code')
        ->assertSet('phoneVerified', false);
});

test('new customers can register without an email', function () {
    $component = Livewire::test('pages::auth.mobile-login')
        ->set('phone', '0712 345 678')
        ->call('requestCode');

    $component
        ->set('code', $component->get('placeholderCode'))
        ->call('verifyCode')
        ->assertSet('step', 'profile')
        ->assertSee('Email address (optional)')
        ->assertDontSee('I agree to receive UniqBid updates and offers')
        ->set('name', 'No Email')
        ->call('completeRegistration')
        ->assertHasNoErrors()
        ->assertRedirect(route('account', absolute: false));

    $user = User::query()->where('phone', '254712345678')->firstOrFail();

    expect($user->email)->toBeNull()
        ->and($user->name)->toBe('No Email');
    $this->assertAuthenticatedAs($user);
});

test('mobile sign in keeps the consent a bidder gave when bidding', function () {
    Bidder::factory()->create([
        'phone' => '254712345678',
        'marketing_consent' => true,
        'marketing_consented_at' => now(),
    ]);

    $component = Livewire::test('pages::auth.mobile-login')
        ->set('phone', '0712 345 678')
        ->call('requestCode');

    $component
        ->set('code', $component->get('placeholderCode'))
        ->call('verifyCode')
        ->set('name', 'Returning Guest')
        ->call('completeRegistration')
        ->assertHasNoErrors();

    expect(Bidder::query()->where('phone', '254712345678')->sole()->marketing_consent)->toBeTrue();
});
