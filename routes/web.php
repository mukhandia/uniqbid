<?php

use App\Http\Controllers\Admin\CustomerVerificationDocumentController;
use App\Http\Controllers\MpesaB2cResultController;
use App\Http\Controllers\MpesaStkCallbackController;
use App\Livewire\Admin\ProductCreate;
use App\Livewire\Admin\ProductEdit;
use Illuminate\Support\Facades\Route;

Route::post('mpesa/stk/callback', MpesaStkCallbackController::class)->name('mpesa.stk.callback');
Route::post('mpesa/b2c/result', [MpesaB2cResultController::class, 'result'])->name('mpesa.b2c.result');
Route::post('mpesa/b2c/timeout', [MpesaB2cResultController::class, 'timeout'])->name('mpesa.b2c.timeout');

Route::livewire('/', 'pages::home')->name('home');

Route::livewire('auctions', 'pages::auctions.index')->name('auctions.index');
Route::livewire('auctions/{auction}', 'pages::auctions.show')->name('auctions.show');

Route::livewire('shop', 'pages::shop.index')->name('shop.index');
Route::livewire('shop/{product}', 'pages::shop.show')->name('shop.show');

Route::livewire('cart', 'pages::cart')->name('cart');
Route::livewire('checkout', 'pages::checkout')->name('checkout');
Route::livewire('orders/track', 'pages::orders.track')->name('orders.track');
Route::livewire('orders/{order}', 'pages::orders.show')->name('orders.show');

Route::livewire('past-sales', 'pages::results')->name('past-sales');
Route::redirect('results', '/past-sales');
Route::livewire('how-it-works', 'pages::how-it-works')->name('how-it-works');
Route::livewire('redeem', 'pages::redeem')->name('redeem');
Route::livewire('testimonials', 'pages::testimonials')->name('testimonials');
Route::livewire('faqs', 'pages::faqs')->name('faqs');
Route::livewire('contact', 'pages::contact')->name('contact');
Route::livewire('legal', 'pages::legal')->name('legal');
Route::livewire('sell', 'pages::sell')->name('sell');
Route::livewire('account', 'pages::account')->name('account');
Route::livewire('mobile-login', 'pages::auth.mobile-login')->name('mobile-login');

Route::middleware(['auth', 'verified', 'redirect-non-admin'])->group(function () {
    Route::livewire('dashboard', 'pages::admin.dashboard')->name('dashboard');
});

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::livewire('auctions', 'pages::admin.auctions')->name('auctions');
    Route::livewire('auctions/{auction}/bids', 'pages::admin.auction-bids')->name('auction-bids');
    Route::livewire('orders', 'pages::admin.orders')->name('orders');
    Route::livewire('orders/{order}', 'pages::admin.order')->name('orders.show');
    Route::livewire('categories', 'pages::admin.categories')->name('categories');
    Route::livewire('products', 'pages::admin.products')->name('products');
    Route::livewire('products/create', ProductCreate::class)->name('products.create');
    Route::livewire('products/{product}/edit', ProductEdit::class)->name('products.edit');
    Route::livewire('guest-listings', 'pages::admin.guest-listings')->name('guest-listings');
    Route::livewire('customers', 'pages::admin.customers')->name('customers');
    Route::livewire('customers/{bidder}', 'pages::admin.customer')->name('customers.show');
    Route::livewire('customer-verifications', 'pages::admin.customer-verifications')->name('customer-verifications');
    Route::get('verification-documents/{document}', CustomerVerificationDocumentController::class)->name('verification-documents.show');
    Route::livewire('tickets', 'pages::admin.tickets')->name('tickets');
    Route::livewire('tickets/{ticket}', 'pages::admin.ticket')->name('tickets.show');
    Route::livewire('reports', 'pages::admin.reports')->name('reports');
    Route::livewire('testimonials', 'pages::admin.testimonials')->name('testimonials');
    Route::livewire('legal', 'pages::admin.legal')->name('legal');
    Route::livewire('how-it-works', 'pages::admin.how-it-works')->name('how-it-works');
    Route::livewire('faqs', 'pages::admin.faqs')->name('faqs');
    Route::livewire('logistics', 'pages::admin.logistics')->name('logistics');
    Route::livewire('activity', 'pages::admin.activity')->name('activity');
    Route::livewire('staff', 'pages::admin.staff')->name('staff');
    Route::livewire('roles', 'pages::admin.roles')->name('roles');
    Route::livewire('settings', 'pages::admin.settings')->name('settings');
});

require __DIR__.'/settings.php';
