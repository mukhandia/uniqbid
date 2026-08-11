<?php

use App\Models\User;

test('guests see sign in on the account page', function () {
    $this->get(route('account'))
        ->assertOk()
        ->assertSee('Sign in')
        ->assertSee('Guest')
        ->assertDontSee('Go to dashboard');
});

test('authenticated users see dashboard and profile links', function () {
    $user = User::factory()->create([
        'email' => 'member@uniqbid.test',
    ]);

    $this->actingAs($user)
        ->get(route('account'))
        ->assertOk()
        ->assertSee('Go to dashboard')
        ->assertSee('Profile settings')
        ->assertSee('member@uniqbid.test')
        ->assertDontSee('Sign in');
});
