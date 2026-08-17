<?php

use App\Models\Auction;
use App\Models\Bidder;
use App\Models\User;
use Livewire\Livewire;

test('non live auctions return not found', function () {
    $auction = Auction::factory()->create([
        'status' => 'closed',
        'ends_at' => now()->subHour(),
    ]);

    $this->get(route('auctions.show', $auction))->assertNotFound();
});

test('bid form validates contact details amount and rules consent', function () {
    $auction = Auction::factory()->create([
        'min_bid' => 10,
        'max_bid' => 1000,
        'bid_increment' => 5,
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    Livewire::test('pages::auctions.show', ['auction' => $auction])
        ->set('name', 'A')
        ->set('email', 'not-an-email')
        ->set('phone', '12345')
        ->set('amount', '3')
        ->set('rulesConsent', false)
        ->call('submitBid')
        ->assertHasErrors(['name', 'email', 'phone', 'amount', 'rulesConsent']);
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
        ->set('email', 'amina@example.com')
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
        ->set('email', 'amina@example.com')
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
        ->assertSet('email', 'jane@example.com')
        ->assertSet('phone', '0712345678')
        ->assertSet('whatsappNumber', '0711222333');

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

test('an unauthenticated visitor cannot submit a bid using an existing customer number', function () {
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
        ->set('email', 'amina@example.com')
        ->set('phone', '0712345678')
        ->set('amount', '15')
        ->set('rulesConsent', true)
        ->call('submitBid')
        ->assertHasErrors(['phone'])
        ->assertSet('formState', 'idle');
});

test('a direct guest bid saves the bidder profile before payment confirmation', function () {
    $auction = Auction::factory()->create([
        'min_bid' => 10,
        'max_bid' => 1000,
        'bid_increment' => 5,
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    Livewire::test('pages::auctions.show', ['auction' => $auction])
        ->set('name', 'Amina Wanjiku')
        ->set('email', 'amina@example.com')
        ->set('phone', '0712345678')
        ->set('whatsappNumber', '0711222333')
        ->set('amount', '15')
        ->set('rulesConsent', true)
        ->call('submitBid')
        ->assertHasNoErrors()
        ->assertSet('formState', 'pending');

    $bidder = Bidder::query()->where('phone', '254712345678')->firstOrFail();

    expect($bidder->name)->toBe('Amina Wanjiku')
        ->and($bidder->email)->toBe('amina@example.com')
        ->and($bidder->whatsapp_number)->toBe('254711222333');
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
        ->set('email', 'james@example.com')
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
        ->set('email', 'james@example.com')
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
