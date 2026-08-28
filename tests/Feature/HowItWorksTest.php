<?php

use Database\Seeders\ContentSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ContentSeeder::class);
});

test('how it works defaults to english copy', function () {
    Livewire::test('pages::how-it-works')
        ->assertSet('lang', 'en')
        ->assertSee('How UniqBid works')
        ->assertSee('Pay participation fee')
        ->assertSee('Successful buy');
});

test('how it works can switch to kiswahili', function () {
    Livewire::test('pages::how-it-works')
        ->set('lang', 'sw')
        ->assertSee('UniqBid Inavyofanya Kazi')
        ->assertDontSee('How UniqBid works');
});
