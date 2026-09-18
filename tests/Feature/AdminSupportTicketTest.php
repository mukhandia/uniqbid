<?php

use App\Models\Product;
use App\Models\SupportTicket;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('guests cannot access support tickets', function () {
    $this->get(route('admin.tickets'))->assertRedirect(route('login'));
});

test('non admins cannot access support tickets', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.tickets'))
        ->assertForbidden();
});

test('admin can list support tickets with unread first', function () {
    $read = SupportTicket::factory()->read()->create([
        'name' => 'Read Customer',
        'subject' => 'Old question',
    ]);
    $unread = SupportTicket::factory()->create([
        'name' => 'New Customer',
        'subject' => 'Need help today',
    ]);

    $this->actingAs($this->admin);

    Livewire::test('pages::admin.tickets')
        ->assertSee($unread->reference())
        ->assertSee('New Customer')
        ->assertSee('Need help today')
        ->assertSee($read->reference())
        ->assertSee('1 unread');
});

test('opening a ticket marks it as read', function () {
    $ticket = SupportTicket::factory()->create([
        'name' => 'Amina Wanjiku',
        'subject' => 'Bid status',
        'message' => 'Please confirm my latest bid went through successfully.',
    ]);

    expect($ticket->isUnread())->toBeTrue();

    $this->actingAs($this->admin);

    Livewire::test('pages::admin.ticket', ['ticket' => $ticket])
        ->assertSee($ticket->reference())
        ->assertSee('Amina Wanjiku')
        ->assertSee('Please confirm my latest bid went through successfully.');

    expect($ticket->fresh()->isUnread())->toBeFalse();
});

test('admin can update a ticket status', function () {
    $ticket = SupportTicket::factory()->create();

    $this->actingAs($this->admin);

    Livewire::test('pages::admin.ticket', ['ticket' => $ticket])
        ->set('status', SupportTicket::STATUS_IN_PROGRESS)
        ->call('updateStatus')
        ->assertHasNoErrors();

    expect($ticket->fresh()->status)->toBe(SupportTicket::STATUS_IN_PROGRESS)
        ->and($ticket->fresh()->statusLabel())->toBe('In progress');
});

test('ticket bell counts unread tickets', function () {
    SupportTicket::factory()->count(2)->create();
    SupportTicket::factory()->read()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.tickets'))
        ->assertOk()
        ->assertSee('2 unread support tickets', false);
});

test('ticket bell polls every three seconds and picks up new unread tickets', function () {
    $this->actingAs($this->admin);

    $bell = Livewire::test('admin-ticket-bell')
        ->assertSeeHtml('wire:poll.3s.visible')
        ->assertSee('No items needing attention', false);

    SupportTicket::factory()->create();

    $bell->call('$refresh')
        ->assertSee('1 unread support ticket', false);
});

test('attention bell combines unread tickets and unseen guest listings', function () {
    SupportTicket::factory()->create(['subject' => 'Where is my order?']);

    $seller = User::factory()->create(['is_admin' => false]);
    Product::factory()->create([
        'name' => 'Pending Guest Lamp',
        'seller_user_id' => $seller->id,
        'approval_status' => Product::APPROVAL_PENDING,
        'channel' => Product::CHANNEL_NONE,
    ]);

    Livewire::actingAs($this->admin)
        ->test('admin-ticket-bell')
        ->assertSee('1 unread support ticket', false)
        ->assertSee('1 unseen guest listing', false)
        ->assertSee('Where is my order?')
        ->assertSee('Pending Guest Lamp');
});

test('attention bell hides guest listings from roles without that permission', function () {
    $this->seed(RolePermissionSeeder::class);

    SupportTicket::factory()->create();
    $seller = User::factory()->create(['is_admin' => false]);
    Product::factory()->create([
        'seller_user_id' => $seller->id,
        'approval_status' => Product::APPROVAL_PENDING,
        'channel' => Product::CHANNEL_NONE,
    ]);

    $care = User::factory()->create(['is_admin' => false]);
    $care->assignRole('Customer Care');

    Livewire::actingAs($care->fresh())
        ->test('admin-ticket-bell')
        ->assertSee('1 unread support ticket', false)
        ->assertSee('1 unseen guest listing', false);

    $logistics = User::factory()->create(['is_admin' => false]);
    $logistics->assignRole('Logistics');

    Livewire::actingAs($logistics->fresh())
        ->test('admin-ticket-bell')
        ->assertSee('No items needing attention', false);
});
