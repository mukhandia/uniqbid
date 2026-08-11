<?php

use App\Models\SupportTicket;
use Livewire\Livewire;

test('contact form requires name contact and message', function () {
    Livewire::test('pages::contact')
        ->set('name', '')
        ->set('contact', '')
        ->set('message', 'short')
        ->call('submit')
        ->assertHasErrors(['name', 'contact', 'message']);
});

test('contact form stores email contacts on the email column', function () {
    Livewire::test('pages::contact')
        ->set('name', 'Amina Wanjiku')
        ->set('contact', 'amina@example.com')
        ->set('subject', '')
        ->set('message', 'Please help me with my auction bid status today.')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('ticketRef', fn ($ref) => str_starts_with((string) $ref, 'TKT'));

    $ticket = SupportTicket::query()->first();

    expect($ticket)->not->toBeNull()
        ->and($ticket->email)->toBe('amina@example.com')
        ->and($ticket->phone)->toBeNull()
        ->and($ticket->subject)->toBe('General enquiry')
        ->and($ticket->status)->toBe('open');
});

test('contact form stores phone contacts on the phone column', function () {
    Livewire::test('pages::contact')
        ->set('name', 'James Mwangi')
        ->set('contact', '0712345678')
        ->set('subject', 'Payment issue')
        ->set('message', 'My M-Pesa prompt did not complete successfully.')
        ->call('submit')
        ->assertHasNoErrors();

    $ticket = SupportTicket::query()->first();

    expect($ticket->phone)->toBe('0712345678')
        ->and($ticket->email)->toBeNull()
        ->and($ticket->subject)->toBe('Payment issue');
});

test('contact form can be reset after submission', function () {
    Livewire::test('pages::contact')
        ->set('name', 'James Mwangi')
        ->set('contact', '0712345678')
        ->set('subject', 'Help')
        ->set('message', 'I need assistance with placing another bid.')
        ->call('submit')
        ->assertSet('ticketRef', fn ($ref) => filled($ref))
        ->call('resetForm')
        ->assertSet('name', '')
        ->assertSet('contact', '')
        ->assertSet('subject', '')
        ->assertSet('message', '')
        ->assertSet('ticketRef', null);
});
