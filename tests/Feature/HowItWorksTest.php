<?php

use Livewire\Livewire;

test('how it works defaults to english copy', function () {
    Livewire::test('pages::how-it-works')
        ->assertSet('lang', 'en')
        ->assertSee('How UniqBid works')
        ->assertSee('Pay for Bid')
        ->assertSee('Successful Buy');
});

test('how it works can switch to kiswahili', function () {
    Livewire::test('pages::how-it-works')
        ->set('lang', 'sw')
        ->assertSee('UniqBid Inavyofanya Kazi')
        ->assertDontSee('How UniqBid works');
});
