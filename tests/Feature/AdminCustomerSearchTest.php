<?php

use App\Models\Bidder;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('admins can search customers by name, email, whatsapp and any phone format', function (string $term) {
    Bidder::factory()->create(['name' => 'Achieng Otieno', 'phone' => '254712345678', 'email' => 'achieng@example.com', 'whatsapp_number' => '254799000111']);
    Bidder::factory()->create(['name' => 'Brian Mutua', 'phone' => '254733111222', 'email' => 'brian@example.com', 'whatsapp_number' => null]);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.customers')
        ->set('search', $term)
        ->assertSee('Achieng Otieno')
        ->assertDontSee('Brian Mutua')
        ->assertSee('1 customer');
})->with([
    'name' => 'achieng',
    'email' => 'achieng@example',
    'local phone' => '0712 345',
    'international phone' => '+254712345678',
    'whatsapp' => '0799000',
]);

test('customer search finds the linked account name and can be cleared', function () {
    $user = User::factory()->create(['name' => 'Account Holder Name', 'phone' => '254700000001']);
    Bidder::factory()->create(['user_id' => $user->id, 'name' => 'Bidder Alias', 'phone' => '254700000001']);
    Bidder::factory()->create(['name' => 'Someone Else', 'phone' => '254700000002']);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.customers')
        ->set('search', 'Account Holder')
        ->assertSee('Bidder Alias')
        ->assertDontSee('Someone Else')
        ->call('clearSearch')
        ->assertSet('search', '')
        ->assertSee('Someone Else');
});

test('customer search shows an empty message when nothing matches', function () {
    Bidder::factory()->create(['name' => 'Achieng Otieno']);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.customers')
        ->set('search', 'nobody-here')
        ->assertSee('No customers match')
        ->assertDontSee('Achieng Otieno');
});

test('customer search is kept in the url', function () {
    Bidder::factory()->create(['name' => 'Achieng Otieno']);
    Bidder::factory()->create(['name' => 'Brian Mutua']);

    $this->actingAs($this->admin)
        ->get(route('admin.customers', ['search' => 'Achieng']))
        ->assertOk()
        ->assertSee('Achieng Otieno')
        ->assertDontSee('Brian Mutua');
});

test('admins can search and filter customer verification', function () {
    $submitted = User::factory()->create(['name' => 'Submitted Today', 'phone' => '254711000001']);
    $submitted->customerControl()->create(['verification_status' => 'submitted', 'verification_submitted_at' => now()]);
    $submitted->verificationDocuments()->create([
        'path' => 'verification-documents/'.$submitted->id.'/id.pdf',
        'original_name' => 'id.pdf',
        'mime_type' => 'application/pdf',
        'size' => 1000,
    ]);

    $old = User::factory()->create(['name' => 'Submitted Long Ago', 'phone' => '254711000002']);
    $old->customerControl()->create(['verification_status' => 'submitted', 'verification_submitted_at' => now()->subDays(20)]);

    $rejected = User::factory()->create(['name' => 'Rejected Customer', 'phone' => '254711000003']);
    $rejected->customerControl()->create(['verification_status' => 'rejected']);

    User::factory()->create(['name' => 'Never Asked', 'phone' => '254711000004']);

    $component = Livewire::actingAs($this->admin)
        ->test('pages::admin.customer-verifications')
        ->assertSee('Awaiting review (2)')
        ->assertSee('Rejected (1)')
        ->assertSee('Not requested (1)');

    $component->set('search', '0711000003')
        ->assertSee('Rejected Customer')
        ->assertDontSee('Submitted Today')
        ->assertSee('Clear filters');

    $component->call('clearFilters')
        ->assertSet('search', '')
        ->set('documents', 'with')
        ->assertSee('Submitted Today')
        ->assertDontSee('Submitted Long Ago')
        ->assertDontSee('Rejected Customer');

    $component->set('documents', 'without')
        ->assertDontSee('Submitted Today')
        ->assertSee('Never Asked');

    $component->call('clearFilters')
        ->set('status', 'submitted')
        ->set('period', '7d')
        ->assertSee('Submitted Today')
        ->assertDontSee('Submitted Long Ago');

    $component->set('period', '30d')
        ->assertSee('Submitted Long Ago')
        ->assertDontSee('Rejected Customer');

    $component->set('status', 'not_requested')
        ->set('period', 'all')
        ->assertSee('Never Asked')
        ->assertDontSee('Rejected Customer');
});

test('verification filters are kept in the url', function () {
    $rejected = User::factory()->create(['name' => 'Rejected Customer', 'phone' => '254711000003']);
    $rejected->customerControl()->create(['verification_status' => 'rejected']);
    User::factory()->create(['name' => 'Never Asked', 'phone' => '254711000004']);

    $this->actingAs($this->admin)
        ->get(route('admin.customer-verifications', ['status' => 'rejected']))
        ->assertOk()
        ->assertSee('Rejected Customer')
        ->assertDontSee('Never Asked');
});
