<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::home')->name('home');

Route::livewire('auctions', 'pages::auctions.index')->name('auctions.index');
Route::livewire('auctions/{auction}', 'pages::auctions.show')->name('auctions.show');

Route::livewire('shop', 'pages::shop.index')->name('shop.index');
Route::livewire('shop/{product}', 'pages::shop.show')->name('shop.show');

Route::livewire('cart', 'pages::cart')->name('cart');
Route::livewire('checkout', 'pages::checkout')->name('checkout');

Route::livewire('past-sales', 'pages::results')->name('past-sales');
Route::redirect('results', '/past-sales');
Route::livewire('how-it-works', 'pages::how-it-works')->name('how-it-works');
Route::livewire('testimonials', 'pages::testimonials')->name('testimonials');
Route::livewire('contact', 'pages::contact')->name('contact');
Route::livewire('legal', 'pages::legal')->name('legal');
Route::livewire('account', 'pages::account')->name('account');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::livewire('auctions', 'pages::admin.auctions')->name('auctions');
    Route::livewire('auctions/{auction}/bids', 'pages::admin.auction-bids')->name('auction-bids');
});

require __DIR__.'/settings.php';
