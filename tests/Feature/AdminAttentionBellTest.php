<?php

use App\Models\Product;
use App\Models\SupportTicket;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('attention bell opens a panel instead of navigating away', function () {
    SupportTicket::factory()->create(['subject' => 'Where is my order?']);

    Livewire::actingAs($this->admin)
        ->test('admin-ticket-bell')
        ->assertSeeHtml('data-test="admin-attention-panel"')
        ->assertSee('Needs attention')
        ->assertSee('Where is my order?')
        ->assertDontSeeHtml('href="'.route('dashboard').'"');
});

test('opening a ticket from the bell marks it read and redirects', function () {
    $ticket = SupportTicket::factory()->create(['subject' => 'Need help today']);

    Livewire::actingAs($this->admin)
        ->test('admin-ticket-bell')
        ->call('openTicket', $ticket->id)
        ->assertRedirect(route('admin.tickets.show', $ticket));

    expect($ticket->fresh()->isUnread())->toBeFalse();
});

test('opening a guest listing from the bell marks it seen and redirects', function () {
    $seller = User::factory()->create(['is_admin' => false]);
    $listing = Product::factory()->create([
        'name' => 'Guest Bamboo Shelf',
        'seller_user_id' => $seller->id,
        'approval_status' => Product::APPROVAL_PENDING,
        'channel' => Product::CHANNEL_NONE,
        'admin_seen_at' => null,
    ]);

    Livewire::actingAs($this->admin)
        ->test('admin-ticket-bell')
        ->assertSee('Guest Bamboo Shelf')
        ->call('openGuestListing', $listing->id)
        ->assertRedirect(route('admin.guest-listings', ['status' => 'pending']));

    expect($listing->fresh()->admin_seen_at)->not->toBeNull();

    Livewire::actingAs($this->admin)
        ->test('admin-ticket-bell')
        ->assertDontSee('Guest Bamboo Shelf')
        ->assertSee('No items needing attention', false);
});

test('seen guest listings stay pending for approval but leave the bell', function () {
    $seller = User::factory()->create(['is_admin' => false, 'name' => 'Mary Seller']);
    $listing = Product::factory()->create([
        'name' => 'Seen Guest Chair',
        'seller_user_id' => $seller->id,
        'approval_status' => Product::APPROVAL_PENDING,
        'channel' => Product::CHANNEL_NONE,
        'admin_seen_at' => now(),
        'price' => 4200,
    ]);

    Livewire::actingAs($this->admin)
        ->test('admin-ticket-bell')
        ->assertDontSee('Seen Guest Chair');

    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Guest listings awaiting approval')
        ->assertSee('Seen Guest Chair')
        ->assertSee('Mary Seller');

    expect($listing->fresh()->approval_status)->toBe(Product::APPROVAL_PENDING);
});

test('logistics role does not see ticket or guest listing notifications', function () {
    $this->seed(RolePermissionSeeder::class);

    SupportTicket::factory()->create();
    $seller = User::factory()->create(['is_admin' => false]);
    Product::factory()->create([
        'seller_user_id' => $seller->id,
        'approval_status' => Product::APPROVAL_PENDING,
        'channel' => Product::CHANNEL_NONE,
    ]);

    $logistics = User::factory()->create(['is_admin' => false]);
    $logistics->assignRole('Logistics');

    Livewire::actingAs($logistics->fresh())
        ->test('admin-ticket-bell')
        ->assertSee('No items needing attention', false)
        ->assertSee('All caught up');
});
