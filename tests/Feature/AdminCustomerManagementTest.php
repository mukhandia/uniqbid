<?php

use App\Models\AuctionBid;
use App\Models\Bidder;
use App\Models\BidderMessage;
use App\Models\CustomerVerificationDocument;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

function customerWithAccount(array $attributes = []): Bidder
{
    $user = User::factory()->create([
        'name' => $attributes['name'] ?? 'Achieng Otieno',
        'phone' => $attributes['phone'] ?? '254712345678',
        'email' => $attributes['email'] ?? 'achieng@example.com',
        'phone_verified_at' => now(),
    ]);

    return Bidder::factory()->create([
        'user_id' => $user->id,
        'name' => $user->name,
        'phone' => $user->phone,
        'email' => $user->email,
        'whatsapp_number' => $attributes['whatsapp_number'] ?? null,
    ]);
}

test('admins can add a customer with only a name and mobile number', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.customers')
        ->call('openCreate')
        ->set('name', 'Brian Mutua')
        ->set('phone', '0733 111 222')
        ->call('createCustomer')
        ->assertHasNoErrors();

    $bidder = Bidder::query()->where('phone', '254733111222')->sole();

    expect($bidder->name)->toBe('Brian Mutua')
        ->and($bidder->email)->toBeNull()
        ->and($bidder->whatsapp_number)->toBeNull()
        ->and($bidder->user)->not->toBeNull()
        ->and($bidder->user->phone)->toBe('254733111222')
        ->and($bidder->user->email)->toBeNull()
        ->and($bidder->user->phone_verified_at)->toBeNull()
        ->and($bidder->user->is_admin)->toBeFalse();
});

test('admins can add a customer with email and whatsapp', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.customers')
        ->set('name', 'Brian Mutua')
        ->set('phone', '+254 733 111222')
        ->set('email', 'Brian@Example.com')
        ->set('whatsapp', '0799 000 111')
        ->call('createCustomer')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.customers.show', Bidder::query()->sole()));

    $bidder = Bidder::query()->sole();

    expect($bidder->email)->toBe('brian@example.com')
        ->and($bidder->whatsapp_number)->toBe('254799000111')
        ->and($bidder->user->email)->toBe('brian@example.com');
});

test('adding a customer validates the details', function (array $input, string $errorKey) {
    customerWithAccount(['phone' => '254712345678', 'email' => 'taken@example.com']);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.customers')
        ->set($input)
        ->call('createCustomer')
        ->assertHasErrors([$errorKey]);

    expect(Bidder::query()->count())->toBe(1);
})->with([
    'missing name' => [['name' => '', 'phone' => '0733111222'], 'name'],
    'missing phone' => [['name' => 'Brian', 'phone' => ''], 'phone'],
    'invalid phone' => [['name' => 'Brian', 'phone' => '12345'], 'phone'],
    'phone already used' => [['name' => 'Brian', 'phone' => '0712345678'], 'phone'],
    'email already used' => [['name' => 'Brian', 'phone' => '0733111222', 'email' => 'taken@example.com'], 'email'],
    'invalid email' => [['name' => 'Brian', 'phone' => '0733111222', 'email' => 'not-an-email'], 'email'],
    'invalid whatsapp' => [['name' => 'Brian', 'phone' => '0733111222', 'whatsapp' => '999'], 'whatsapp'],
]);

test('a customer added by an admin can sign in with a one-time code without re-entering details', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.customers')
        ->set('name', 'Brian Mutua')
        ->set('phone', '0733111222')
        ->call('createCustomer');

    auth()->logout();

    $component = Livewire::test('pages::auth.mobile-login')
        ->set('phone', '0733111222')
        ->call('requestCode')
        ->assertSet('requiresProfile', false);

    $component->set('code', $component->get('placeholderCode'))
        ->call('verifyCode');

    $user = User::query()->where('phone', '254733111222')->sole();

    expect(auth()->id())->toBe($user->id)
        ->and($user->phone_verified_at)->not->toBeNull()
        ->and(Bidder::query()->where('phone', '254733111222')->count())->toBe(1);
});

test('the customer page shows profile, bids and messages', function () {
    $bidder = customerWithAccount(['whatsapp_number' => '254799000111']);
    $bid = AuctionBid::factory()->accepted()->for($bidder)->create(['amount' => 4321, 'reference' => 'BID-PAIDONE']);
    PaymentAttempt::factory()->succeeded()->create(['auction_bid_id' => $bid->id, 'amount' => 150]);
    BidderMessage::factory()->create(['auction_id' => $bid->auction_id, 'bidder_id' => $bidder->id, 'body' => 'Please call us about your bid.']);

    $this->actingAs($this->admin)
        ->get(route('admin.customers.show', $bidder))
        ->assertOk()
        ->assertSee('Achieng Otieno')
        ->assertSee('254712345678')
        ->assertSee('achieng@example.com')
        ->assertSee('254799000111')
        ->assertSee('Verified account')
        ->assertSee($bid->auction->name)
        ->assertSee('BID-PAIDONE')
        ->assertSee('Please call us about your bid.')
        ->assertSee('KES 150');
});

test('the customers list links to each customer and shows the add button', function () {
    $bidder = customerWithAccount();

    $this->actingAs($this->admin)
        ->get(route('admin.customers'))
        ->assertOk()
        ->assertSee('Add customer')
        ->assertSee(route('admin.customers.show', $bidder), false);
});

test('admins can edit a customer', function () {
    $bidder = customerWithAccount();

    Livewire::actingAs($this->admin)
        ->test('pages::admin.customer', ['bidder' => $bidder])
        ->call('openEdit')
        ->assertSet('phone', '0712345678')
        ->assertSet('email', 'achieng@example.com')
        ->set('name', 'Achieng O. Otieno')
        ->set('email', '')
        ->set('whatsapp', '0799000111')
        ->call('updateCustomer')
        ->assertHasNoErrors();

    $bidder->refresh();

    expect($bidder->name)->toBe('Achieng O. Otieno')
        ->and($bidder->email)->toBeNull()
        ->and($bidder->whatsapp_number)->toBe('254799000111')
        ->and($bidder->user->name)->toBe('Achieng O. Otieno')
        ->and($bidder->user->email)->toBeNull()
        ->and($bidder->user->phone_verified_at)->not->toBeNull();
});

test('changing a customer mobile number requires them to confirm it again', function () {
    $bidder = customerWithAccount();

    Livewire::actingAs($this->admin)
        ->test('pages::admin.customer', ['bidder' => $bidder])
        ->call('openEdit')
        ->set('phone', '0700999888')
        ->call('updateCustomer')
        ->assertHasNoErrors();

    $bidder->refresh();

    expect($bidder->phone)->toBe('254700999888')
        ->and($bidder->user->phone)->toBe('254700999888')
        ->and($bidder->user->phone_verified_at)->toBeNull();
});

test('editing a guest profile without an account only changes the profile', function () {
    $bidder = Bidder::factory()->create(['name' => 'Guest Person', 'phone' => '254711222333', 'user_id' => null]);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.customer', ['bidder' => $bidder])
        ->call('openEdit')
        ->set('name', 'Guest Renamed')
        ->call('updateCustomer')
        ->assertHasNoErrors();

    expect($bidder->fresh()->name)->toBe('Guest Renamed')
        ->and(User::query()->where('phone', '254711222333')->exists())->toBeFalse();
});

test('editing cannot take another customer mobile number', function () {
    customerWithAccount(['phone' => '254700000001', 'email' => 'one@example.com']);
    $bidder = customerWithAccount(['phone' => '254700000002', 'email' => 'two@example.com']);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.customer', ['bidder' => $bidder])
        ->call('openEdit')
        ->set('phone', '0700000001')
        ->call('updateCustomer')
        ->assertHasErrors(['phone']);

    expect($bidder->fresh()->phone)->toBe('254700000002');
});

test('admins can delete a customer without paid bids, including their files', function () {
    Storage::fake(CustomerVerificationDocument::DISK);

    $bidder = customerWithAccount();
    $user = $bidder->user;
    AuctionBid::factory()->for($bidder)->create();
    AuctionBid::factory()->failed()->for($bidder)->create();
    $user->customerControl()->create(['verification_status' => 'submitted']);
    Storage::disk(CustomerVerificationDocument::DISK)->put('verification-documents/'.$user->id.'/id.pdf', 'pdf');
    $user->verificationDocuments()->create([
        'path' => 'verification-documents/'.$user->id.'/id.pdf',
        'original_name' => 'id.pdf',
        'mime_type' => 'application/pdf',
        'size' => 3,
    ]);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.customer', ['bidder' => $bidder])
        ->assertSee('This removes their profile')
        ->call('deleteCustomer')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.customers'));

    expect(Bidder::query()->whereKey($bidder->id)->exists())->toBeFalse()
        ->and(User::query()->whereKey($user->id)->exists())->toBeFalse()
        ->and(AuctionBid::query()->count())->toBe(0)
        ->and(CustomerVerificationDocument::query()->count())->toBe(0);
    Storage::disk(CustomerVerificationDocument::DISK)->assertMissing('verification-documents/'.$user->id.'/id.pdf');
});

test('customers with paid bids, shop orders or guest listings cannot be deleted', function (Closure $setup, string $reason) {
    $bidder = customerWithAccount();
    $setup($bidder);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.customer', ['bidder' => $bidder])
        ->assertSee($reason)
        ->call('deleteCustomer')
        ->assertHasErrors(['customer']);

    expect(Bidder::query()->whereKey($bidder->id)->exists())->toBeTrue()
        ->and(User::query()->whereKey($bidder->user_id)->exists())->toBeTrue();
})->with([
    'paid bid' => [fn (Bidder $bidder) => AuctionBid::factory()->accepted()->for($bidder)->create(), 'Has 1 paid bid'],
    'shop order' => [fn (Bidder $bidder) => Order::factory()->forUser($bidder->user)->create(), 'Has 1 shop order'],
    'guest listing' => [fn (Bidder $bidder) => Product::factory()->create(['seller_user_id' => $bidder->user_id]), 'Has 1 guest shop listing'],
]);

test('staff accounts cannot be edited or deleted from customers', function () {
    $staff = User::factory()->admin()->create(['phone' => '254700123456']);
    $bidder = Bidder::factory()->create(['user_id' => $staff->id, 'phone' => '254700123456']);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.customer', ['bidder' => $bidder])
        ->call('openEdit')
        ->set('name', 'Renamed')
        ->call('updateCustomer')
        ->assertHasErrors(['customer'])
        ->call('deleteCustomer')
        ->assertHasErrors(['customer']);

    expect($staff->fresh()->is_admin)->toBeTrue()
        ->and(Bidder::query()->whereKey($bidder->id)->exists())->toBeTrue();
});

test('customers cannot open the admin customer pages', function () {
    $bidder = customerWithAccount();

    $this->actingAs($bidder->user)
        ->get(route('admin.customers.show', $bidder))
        ->assertForbidden();
});
