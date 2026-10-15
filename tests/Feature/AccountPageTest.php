<?php

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\Bidder;
use App\Models\CustomerControl;
use App\Models\User;
use Livewire\Livewire;

test('guests see sign in on the account page', function () {
    $this->get(route('account'))
        ->assertOk()
        ->assertSee('Sign in with mobile')
        ->assertSee('Guest')
        ->assertDontSee('Go to dashboard');
});

test('admin users see dashboard and profile links', function () {
    $user = User::factory()->admin()->create([
        'email' => 'admin@uniqbid.test',
    ]);

    $this->actingAs($user)
        ->get(route('account'))
        ->assertOk()
        ->assertSee('Go to dashboard')
        ->assertSee('Profile settings')
        ->assertSee('admin@uniqbid.test')
        ->assertDontSee('Sign in with mobile');
});

test('customers see profile controls and bid history', function () {
    $user = User::factory()->create([
        'phone' => '254712345678',
        'phone_verified_at' => now(),
        'name' => 'Jane Bidder',
        'email' => 'jane@example.com',
    ]);

    $bidder = Bidder::factory()->create([
        'user_id' => $user->id,
        'phone' => $user->phone,
        'name' => $user->name,
        'email' => $user->email,
    ]);

    $auction = Auction::factory()->create([
        'name' => 'Weekend Gadget Sale',
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    AuctionBid::factory()->accepted()->create([
        'auction_id' => $auction->id,
        'bidder_id' => $bidder->id,
        'amount' => 25,
        'reference' => 'BID-TEST1234',
    ]);

    $this->actingAs($user)
        ->get(route('account'))
        ->assertOk()
        ->assertSee('Jane Bidder')
        ->assertSee('Weekend Gadget Sale')
        ->assertSee('BID-TEST1234')
        ->assertSee('Open')
        ->assertSee('Participation fee limit')
        ->assertDontSee('Go to dashboard')
        ->assertDontSee('Profile settings');
});

test('customers can update profile from my account', function () {
    $user = User::factory()->marketingConsent()->create([
        'phone' => '254712345678',
        'phone_verified_at' => now(),
    ]);

    Bidder::factory()->create([
        'user_id' => $user->id,
        'phone' => $user->phone,
        'name' => $user->name,
        'email' => $user->email,
    ]);

    Livewire::actingAs($user)
        ->test('pages::account')
        ->set('name', 'Updated Name')
        ->set('email', 'updated@example.com')
        ->set('whatsappNumber', '0711222333')
        ->call('saveProfile')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toBe('Updated Name')
        ->and($user->email)->toBe('updated@example.com')
        ->and($user->marketing_consent)->toBeTrue()
        ->and($user->bidder->whatsapp_number)->toBe('254711222333');
});

test('customers can save their profile without an email', function () {
    $user = User::factory()->create([
        'phone' => '254712345678',
        'phone_verified_at' => now(),
        'email' => 'old@example.com',
    ]);

    Bidder::factory()->create([
        'user_id' => $user->id,
        'phone' => $user->phone,
        'email' => 'old@example.com',
    ]);

    Livewire::actingAs($user)
        ->test('pages::account')
        ->set('email', '')
        ->call('saveProfile')
        ->assertHasNoErrors();

    expect($user->fresh()->email)->toBeNull()
        ->and($user->fresh()->bidder->email)->toBeNull();
});

test('my account no longer shows the updates and offers checkboxes', function () {
    $user = User::factory()->create([
        'phone' => '254712345678',
        'phone_verified_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::account')
        ->assertSee('Email (optional)')
        ->assertDontSee('Receive UniqBid updates and offers')
        ->assertDontSee('Send sale and account updates by SMS')
        ->assertDontSee('Send sale and account updates by WhatsApp');
});

test('saving the profile keeps the consent given when bidding', function () {
    $user = User::factory()->marketingConsent()->create([
        'phone' => '254712345678',
        'phone_verified_at' => now(),
        'sms_notifications' => true,
        'whatsapp_notifications' => true,
    ]);

    Livewire::actingAs($user)
        ->test('pages::account')
        ->set('name', 'Still Consenting')
        ->call('saveProfile')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->marketing_consent)->toBeTrue()
        ->and($user->sms_notifications)->toBeTrue()
        ->and($user->whatsapp_notifications)->toBeTrue();
});

test('customers can set an optional participation fee limit', function () {
    $user = User::factory()->create([
        'phone' => '254712345678',
        'phone_verified_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::account')
        ->set('participationFeeLimit', 500)
        ->set('limitPeriod', 'daily')
        ->call('saveSpendLimit')
        ->assertHasNoErrors();

    $control = CustomerControl::query()->where('user_id', $user->id)->first();

    expect($control)->not->toBeNull()
        ->and($control->participation_fee_limit)->toBe(500)
        ->and($control->limit_period)->toBe('daily');
});
