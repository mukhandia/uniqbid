<?php

use App\Models\Auction;
use App\Models\Bidder;
use App\Models\User;
use App\Services\AuctionBidService;
use Livewire\Livewire;

test('non live auctions return not found', function () {
    $auction = Auction::factory()->create([
        'status' => 'closed',
        'ends_at' => now()->subHour(),
    ]);

    $this->get(route('auctions.show', $auction))->assertNotFound();
});

test('bid form validates name amount phone and rules consent', function () {
    $auction = Auction::factory()->create([
        'min_bid' => 10,
        'max_bid' => 1000,
        'bid_increment' => 5,
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    Livewire::test('pages::auctions.show', ['auction' => $auction])
        ->set('name', 'A')
        ->set('phone', '12345')
        ->set('amount', '3')
        ->set('rulesConsent', false)
        ->call('submitBid')
        ->assertHasErrors(['name', 'phone', 'amount', 'rulesConsent']);
});

test('bid amount must match increment', function () {
    $auction = Auction::factory()->create([
        'min_bid' => 10,
        'max_bid' => 1000,
        'bid_increment' => 5,
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    Livewire::test('pages::auctions.show', ['auction' => $auction])
        ->set('name', 'Amina Wanjiku')
        ->set('phone', '0712345678')
        ->set('amount', '12')
        ->set('rulesConsent', true)
        ->call('submitBid')
        ->assertHasErrors(['amount'])
        ->assertSet('formState', 'idle');
});

test('valid bid moves to pending payment state', function () {
    $auction = Auction::factory()->create([
        'min_bid' => 10,
        'max_bid' => 1000,
        'bid_increment' => 5,
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    Livewire::test('pages::auctions.show', ['auction' => $auction])
        ->set('name', 'Amina Wanjiku')
        ->set('phone', '0712345678')
        ->set('amount', '15')
        ->set('rulesConsent', true)
        ->call('submitBid')
        ->assertHasNoErrors()
        ->assertSet('formState', 'pending');
});

test('new mobile numbers continue to the guest details form', function () {
    $auction = Auction::factory()->create([
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    Livewire::test('pages::auctions.show', ['auction' => $auction])
        ->set('phone', '0712345678')
        ->call('continueWithPhone')
        ->assertHasNoErrors()
        ->assertSet('bidStep', 'details');
});

test('new 01 mobile numbers continue to the guest details form', function () {
    $auction = Auction::factory()->create([
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    Livewire::test('pages::auctions.show', ['auction' => $auction])
        ->set('phone', '0112345678')
        ->call('continueWithPhone')
        ->assertHasNoErrors()
        ->assertSet('phone', '0112345678')
        ->assertSet('bidStep', 'details');
});

test('existing customer numbers require mobile OTP sign in before bidding', function () {
    $user = User::factory()->create([
        'phone' => '254712345678',
        'phone_verified_at' => now(),
        'name' => 'Jane Bidder',
        'email' => 'jane@example.com',
    ]);

    Bidder::factory()->create([
        'user_id' => $user->id,
        'phone' => $user->phone,
        'name' => $user->name,
        'email' => $user->email,
        'whatsapp_number' => '254711222333',
    ]);

    $auction = Auction::factory()->create([
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    $component = Livewire::test('pages::auctions.show', ['auction' => $auction])
        ->set('phone', '0712345678')
        ->call('continueWithPhone')
        ->assertHasNoErrors()
        ->assertSet('bidStep', 'account')
        ->call('requestSignInCode')
        ->assertSet('bidStep', 'otp');

    $component
        ->set('code', $component->get('placeholderCode'))
        ->call('verifySignInCode')
        ->assertHasNoErrors()
        ->assertSet('bidStep', 'details')
        ->assertSet('name', 'Jane Bidder')
        ->assertSet('phone', '0712345678');

    $this->assertAuthenticatedAs($user);
});

test('inline bid OTP rejects an invalid code', function () {
    User::factory()->create([
        'phone' => '254712345678',
        'phone_verified_at' => now(),
    ]);

    $auction = Auction::factory()->create([
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    Livewire::test('pages::auctions.show', ['auction' => $auction])
        ->set('phone', '0712345678')
        ->call('continueWithPhone')
        ->call('requestSignInCode')
        ->set('code', '000000')
        ->call('verifySignInCode')
        ->assertHasErrors(['code'])
        ->assertSet('bidStep', 'otp');

    $this->assertGuest();
});

test('a direct guest entry saves the bidder profile before payment confirmation', function () {
    $auction = Auction::factory()->create([
        'min_bid' => 10,
        'max_bid' => 1000,
        'bid_increment' => 5,
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    Livewire::test('pages::auctions.show', ['auction' => $auction])
        ->set('name', 'Amina Wanjiku')
        ->set('phone', '0712345678')
        ->set('amount', '15')
        ->set('rulesConsent', true)
        ->call('submitBid')
        ->assertHasNoErrors()
        ->assertSet('formState', 'pending');

    $bidder = Bidder::query()->where('phone', '254712345678')->firstOrFail();

    expect($bidder->name)->toBe('Amina Wanjiku')
        ->and($bidder->email)->toBeNull()
        ->and($bidder->whatsapp_number)->toBeNull()
        ->and($bidder->marketing_consent)->toBeTrue();
});

test('bidding keeps a whatsapp number the customer saved themselves', function () {
    $user = User::factory()->create([
        'phone' => '254712345678',
        'phone_verified_at' => now(),
    ]);
    Bidder::factory()->create([
        'user_id' => $user->id,
        'phone' => $user->phone,
        'whatsapp_number' => '254799888777',
    ]);
    $auction = Auction::factory()->create([
        'min_bid' => 10,
        'max_bid' => 1000,
        'bid_increment' => 5,
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::auctions.show', ['auction' => $auction])
        ->set('amount', '15')
        ->set('rulesConsent', true)
        ->call('submitBid')
        ->assertHasNoErrors();

    expect(Bidder::query()->where('phone', '254712345678')->sole()->whatsapp_number)->toBe('254799888777');
});

test('an unauthenticated visitor cannot submit an entry using an existing customer number', function () {
    User::factory()->create([
        'phone' => '254712345678',
        'phone_verified_at' => now(),
    ]);

    $auction = Auction::factory()->create([
        'min_bid' => 10,
        'max_bid' => 1000,
        'bid_increment' => 5,
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    Livewire::test('pages::auctions.show', ['auction' => $auction])
        ->set('name', 'Amina Wanjiku')
        ->set('phone', '0712345678')
        ->set('amount', '15')
        ->set('rulesConsent', true)
        ->call('submitBid')
        ->assertHasErrors(['phone'])
        ->assertSet('formState', 'idle');
});

test('simulated STK cancellation can be reset', function () {
    $auction = Auction::factory()->create([
        'min_bid' => 10,
        'max_bid' => 1000,
        'bid_increment' => 5,
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    $component = Livewire::test('pages::auctions.show', ['auction' => $auction])
        ->set('name', 'James Mwangi')
        ->set('phone', '0798765432')
        ->set('amount', '20')
        ->set('rulesConsent', true)
        ->call('submitBid')
        ->assertSet('formState', 'pending')
        ->call('simulateStkPushFailure')
        ->assertSet('formState', 'failure')
        ->call('resetForm')
        ->assertSet('formState', 'idle')
        ->assertSet('reference', '');

    expect($component->get('formState'))->toBe('idle');
});

test('simulated STK approval persists and accepts a bid', function () {
    $auction = Auction::factory()->create([
        'min_bid' => 10,
        'max_bid' => 1000,
        'bid_increment' => 5,
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    $component = Livewire::test('pages::auctions.show', ['auction' => $auction])
        ->set('name', 'James Mwangi')
        ->set('phone', '0798765432')
        ->set('amount', '25')
        ->set('rulesConsent', true)
        ->call('submitBid')
        ->assertSet('formState', 'pending')
        ->call('simulateStkPushSuccess')
        ->assertSet('formState', 'success');

    expect($component->get('reference'))->toStartWith('BID-');
});

test('image selection updates active image index', function () {
    $auction = Auction::factory()->create([
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    $auction->images()->create([
        'url' => '/images/auctions/second.png',
        'label' => 'Second',
        'sort_order' => 1,
    ]);

    Livewire::test('pages::auctions.show', ['auction' => $auction->fresh('images')])
        ->assertSet('activeImage', 0)
        ->call('selectImage', 1)
        ->assertSet('activeImage', 1);
});

test('a new number gets an account and is signed in once the first participation fee is paid', function () {
    $auction = Auction::factory()->create([
        'min_bid' => 10,
        'max_bid' => 1000,
        'bid_increment' => 5,
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    $component = Livewire::test('pages::auctions.show', ['auction' => $auction])
        ->set('phone', '0712345678')
        ->call('continueWithPhone')
        ->assertSet('bidStep', 'details')
        ->set('name', 'Amina Wanjiku')
        ->set('amount', '15')
        ->set('rulesConsent', true)
        ->call('submitBid')
        ->assertHasNoErrors();

    expect(User::query()->where('phone', '254712345678')->exists())->toBeFalse();
    $this->assertGuest();

    $component->call('simulateStkPushSuccess')
        ->assertSet('formState', 'success')
        ->assertSet('accountCreated', true)
        ->assertSee('We created your UniqBid account');

    $user = User::query()->where('phone', '254712345678')->sole();

    expect($user->name)->toBe('Amina Wanjiku')
        ->and($user->email)->toBeNull()
        ->and($user->is_admin)->toBeFalse()
        ->and($user->phone_verified_at)->not->toBeNull()
        ->and($user->marketing_consent)->toBeTrue()
        ->and(Bidder::query()->where('phone', '254712345678')->sole()->user_id)->toBe($user->id);

    $this->assertAuthenticatedAs($user);
});

test('a failed participation fee does not create an account', function () {
    $auction = Auction::factory()->create([
        'min_bid' => 10,
        'max_bid' => 1000,
        'bid_increment' => 5,
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    Livewire::test('pages::auctions.show', ['auction' => $auction])
        ->set('name', 'Amina Wanjiku')
        ->set('phone', '0712345678')
        ->set('amount', '15')
        ->set('rulesConsent', true)
        ->call('submitBid')
        ->call('simulateStkPushFailure')
        ->assertSet('formState', 'failure');

    expect(User::query()->where('phone', '254712345678')->exists())->toBeFalse();
    $this->assertGuest();
});

test('after the account is created the same number needs the sign in code to bid again', function () {
    $auction = Auction::factory()->create([
        'min_bid' => 10,
        'max_bid' => 1000,
        'bid_increment' => 5,
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    Livewire::test('pages::auctions.show', ['auction' => $auction])
        ->set('name', 'Amina Wanjiku')
        ->set('phone', '0712345678')
        ->set('amount', '15')
        ->set('rulesConsent', true)
        ->call('submitBid')
        ->call('simulateStkPushSuccess');

    auth()->logout();

    Livewire::test('pages::auctions.show', ['auction' => $auction])
        ->set('phone', '0712345678')
        ->call('continueWithPhone')
        ->assertSet('bidStep', 'account');
});

test('a guest bidder email is reused for the new account only when it is free', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $bidder = Bidder::factory()->create([
        'phone' => '254733333333',
        'email' => 'taken@example.com',
    ]);

    $user = app(AuctionBidService::class)->ensureCustomerAccount($bidder);

    expect($user->email)->toBeNull()
        ->and($bidder->fresh()->user_id)->toBe($user->id);
});
