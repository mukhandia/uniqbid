<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;

test('admin login with remember sets remember token and recaller cookie', function () {
    $user = User::factory()->admin()->create(['remember_token' => null]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'remember' => 'on',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $user->refresh();

    expect($user->remember_token)->not->toBeNull();

    $response->assertCookie(Auth::guard()->getRecallerName());
});

test('admin login without remember does not set remember token', function () {
    $user = User::factory()->admin()->create(['remember_token' => null]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $user->refresh();

    expect($user->remember_token)->toBeNull();
    $response->assertCookieMissing(Auth::guard()->getRecallerName());
});
