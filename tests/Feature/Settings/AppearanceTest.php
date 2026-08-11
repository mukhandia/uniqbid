<?php

use App\Models\User;

test('guests are redirected from appearance settings', function () {
    $this->get(route('appearance.edit'))
        ->assertRedirect(route('login'));
});

test('authenticated users can view appearance settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('appearance.edit'))
        ->assertOk()
        ->assertSee('Appearance')
        ->assertSee('Light')
        ->assertSee('Dark')
        ->assertSee('System');
});

test('unverified authenticated users can view appearance settings while MustVerifyEmail is disabled', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('appearance.edit'))
        ->assertOk();
});
