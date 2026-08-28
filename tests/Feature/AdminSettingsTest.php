<?php

use App\Models\SiteSetting;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('guests cannot access admin settings', function () {
    $this->get(route('admin.settings'))->assertRedirect(route('login'));
});

test('non admins cannot access admin settings', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.settings'))
        ->assertForbidden();
});

test('admin can update contact details used on the public site', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::admin.settings')
        ->set('phone', '+254 711 111 111')
        ->set('whatsapp', '254711111111')
        ->set('email', 'hello@uniqbid.co.ke')
        ->set('address', "New Office\nWaiyaki Way")
        ->set('hours', 'Tue–Fri, 9:00 – 17:00 EAT')
        ->set('mapUrl', 'https://www.google.com/maps/place/Westlands/@-1.267,36.811,17z')
        ->call('save')
        ->assertHasNoErrors();

    $settings = SiteSetting::current();

    expect($settings->phone)->toBe('+254 711 111 111')
        ->and($settings->email)->toBe('hello@uniqbid.co.ke')
        ->and($settings->hours)->toBe('Tue–Fri, 9:00 – 17:00 EAT');

    $this->get(route('contact'))
        ->assertOk()
        ->assertSee('+254 711 111 111')
        ->assertSee('hello@uniqbid.co.ke')
        ->assertSee('New Office')
        ->assertSee('Tue–Fri, 9:00 – 17:00 EAT')
        ->assertSee('google.com/maps', false)
        ->assertSee('output=embed', false);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('+254 711 111 111')
        ->assertSee('hello@uniqbid.co.ke');
});

test('admin settings rejects a map url from an unknown host', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::admin.settings')
        ->set('mapUrl', 'https://evil.example/not-a-map')
        ->call('save')
        ->assertHasErrors(['mapUrl']);
});

test('admin settings uses tabs for company info profile and security', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.settings'))
        ->assertOk()
        ->assertSee('Company Info')
        ->assertSee(route('profile.edit'), false)
        ->assertSee(route('security.edit'), false)
        ->assertDontSee('Appearance')
        ->assertDontSee('Your account');
});

test('admin chrome shows name email logout and no profile dropdown', function () {
    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee($this->admin->name)
        ->assertSee($this->admin->email)
        ->assertSee('Log out')
        ->assertDontSee('Platform')
        ->assertSee('Sales & shop')
        ->assertSee('People')
        ->assertSee('Support & reports')
        ->assertSee('Support tickets')
        ->assertSee('Reports')
        ->assertSee('Settings')
        ->assertDontSee('data-test="sidebar-menu-button"', false)
        ->assertSee('data-test="logout-button"', false)
        ->assertSee('data-test="admin-ticket-bell"', false);
});
