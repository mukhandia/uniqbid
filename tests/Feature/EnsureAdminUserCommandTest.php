<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('ensure admin command creates the admin user', function () {
    $this->artisan('uniqbid:ensure-admin')
        ->assertSuccessful();

    $user = User::query()->where('email', config('uniqbid.admin.email'))->first();

    expect($user)->not->toBeNull()
        ->and($user->is_admin)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check(config('uniqbid.admin.password'), $user->password))->toBeTrue();
});

test('ensure admin command can reset the admin password', function () {
    $user = User::factory()->create([
        'email' => config('uniqbid.admin.email'),
        'password' => 'old-password',
        'is_admin' => false,
    ]);

    $this->artisan('uniqbid:ensure-admin', ['--reset-password' => true])
        ->assertSuccessful();

    $user->refresh();

    expect($user->is_admin)->toBeTrue()
        ->and(Hash::check(config('uniqbid.admin.password'), $user->password))->toBeTrue();
});

test('created admin can authenticate on the login screen', function () {
    $this->artisan('uniqbid:ensure-admin')->assertSuccessful();

    $response = $this->post(route('login.store'), [
        'email' => config('uniqbid.admin.email'),
        'password' => config('uniqbid.admin.password'),
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});
