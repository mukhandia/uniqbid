<?php

use App\Models\Bidder;
use App\Models\User;
use App\Services\SubscriberListService;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('guests cannot access subscribers', function () {
    $this->get(route('admin.subscribers'))->assertRedirect(route('login'));
});

test('non admins cannot access subscribers', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.subscribers'))
        ->assertForbidden();
});

test('admin subscribers page lists opted in accounts and guest bidders', function () {
    $account = User::factory()->marketingConsent()->create([
        'name' => 'Amina Wanjiku',
        'email' => 'amina@example.com',
        'phone' => '254711111111',
        'sms_notifications' => true,
        'whatsapp_notifications' => true,
    ]);
    $guest = Bidder::factory()->marketingConsent()->create([
        'name' => 'James Mwangi',
        'email' => 'james@example.com',
        'phone' => '254722222222',
        'user_id' => null,
    ]);
    User::factory()->create([
        'name' => 'Opted Out',
        'email' => 'out@example.com',
        'phone' => '254733333333',
        'marketing_consent' => false,
    ]);
    Bidder::factory()->create([
        'name' => 'Guest Out',
        'email' => 'guest-out@example.com',
        'phone' => '254744444444',
        'marketing_consent' => false,
        'user_id' => null,
    ]);
    User::factory()->admin()->marketingConsent()->create([
        'email' => 'admin-optin@example.com',
    ]);

    $this->actingAs($this->admin);

    Livewire::test('pages::admin.subscribers')
        ->assertSee('Amina Wanjiku')
        ->assertSee('amina@example.com')
        ->assertSee('254711111111')
        ->assertSee('James Mwangi')
        ->assertSee('james@example.com')
        ->assertSee('254722222222')
        ->assertDontSee('out@example.com')
        ->assertDontSee('guest-out@example.com')
        ->assertDontSee('admin-optin@example.com');

    $amina = app(SubscriberListService::class)->optedIn()->firstWhere('email', 'amina@example.com');
    $james = app(SubscriberListService::class)->optedIn()->firstWhere('email', 'james@example.com');

    expect($amina['sms'])->toBeTrue()
        ->and($amina['whatsapp'])->toBeTrue()
        ->and($james['sms'])->toBeNull()
        ->and($james['whatsapp'])->toBeNull();

    $this->get(route('admin.subscribers'))
        ->assertOk()
        ->assertSee('Subscribers');
});

test('a linked bidder is not listed twice when the account already opted in', function () {
    $user = User::factory()->marketingConsent()->create([
        'email' => 'linked@example.com',
        'phone' => '254755555555',
    ]);
    Bidder::factory()->marketingConsent()->create([
        'user_id' => $user->id,
        'email' => $user->email,
        'phone' => $user->phone,
    ]);

    $rows = app(SubscriberListService::class)->optedIn()->where('email', 'linked@example.com');

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['source'])->toBe('Account');
});

test('unchecking updates and offers removes the contact from subscribers', function () {
    $user = User::factory()->marketingConsent()->create([
        'phone' => '254712345678',
        'phone_verified_at' => now(),
        'email' => 'leave@example.com',
    ]);
    Bidder::factory()->marketingConsent()->create([
        'user_id' => $user->id,
        'phone' => $user->phone,
        'email' => $user->email,
        'name' => $user->name,
    ]);

    expect(app(SubscriberListService::class)->optedIn()->pluck('email'))
        ->toContain('leave@example.com');

    Livewire::actingAs($user)
        ->test('pages::account')
        ->set('marketingConsent', false)
        ->call('saveProfile')
        ->assertHasNoErrors();

    expect($user->fresh()->marketing_consent)->toBeFalse()
        ->and($user->bidder->fresh()->marketing_consent)->toBeFalse()
        ->and(app(SubscriberListService::class)->optedIn()->pluck('email'))
        ->not->toContain('leave@example.com');

    $this->actingAs($this->admin);

    Livewire::test('pages::admin.subscribers')
        ->assertDontSee('leave@example.com');
});

test('subscribers list reflects sms and whatsapp update preferences from my account', function () {
    $user = User::factory()->marketingConsent()->create([
        'phone' => '254712345678',
        'phone_verified_at' => now(),
        'email' => 'channels@example.com',
        'sms_notifications' => true,
        'whatsapp_notifications' => false,
    ]);

    Livewire::actingAs($user)
        ->test('pages::account')
        ->set('smsNotifications', false)
        ->set('whatsappNotifications', true)
        ->call('saveProfile')
        ->assertHasNoErrors();

    $row = app(SubscriberListService::class)->optedIn()->firstWhere('email', 'channels@example.com');

    expect($row['sms'])->toBeFalse()
        ->and($row['whatsapp'])->toBeTrue();

    $this->actingAs($this->admin)
        ->get(route('admin.subscribers'))
        ->assertOk()
        ->assertSee('SMS')
        ->assertSee('WhatsApp');
});
