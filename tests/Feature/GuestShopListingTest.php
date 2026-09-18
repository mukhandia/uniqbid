<?php

use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\Subcategory;
use App\Models\User;
use App\Services\CartService;
use App\Services\GuestListingService;
use App\Services\OrderService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    SiteSetting::current()->update(['guest_shop_commission_percent' => 10]);
});

test('guest listings stay off the shop until approved', function () {
    $seller = User::factory()->create(['is_admin' => false, 'phone' => '254711000001']);
    $category = Category::factory()->create(['is_active' => true]);
    $subcategory = Subcategory::factory()->create([
        'category_id' => $category->id,
        'is_active' => true,
    ]);

    $product = app(GuestListingService::class)->submit($seller, [
        'name' => 'Guest Travel Mug',
        'description' => 'Insulated steel mug in excellent condition for daily commute use.',
        'price' => 2500,
        'stock' => 2,
        'category_id' => $category->id,
        'subcategory_id' => $subcategory->id,
        'image' => '/storage/guest-products/mug.jpg',
    ]);

    expect($product->approval_status)->toBe(Product::APPROVAL_PENDING)
        ->and($product->channel)->toBe(Product::CHANNEL_NONE)
        ->and(Product::query()->inShop()->whereKey($product->id)->exists())->toBeFalse();

    app(GuestListingService::class)->approve($product->fresh());

    expect($product->fresh()->approval_status)->toBe(Product::APPROVAL_APPROVED)
        ->and($product->fresh()->channel)->toBe(Product::CHANNEL_SHOP)
        ->and(Product::query()->inShop()->whereKey($product->id)->exists())->toBeTrue();
});

test('sell form shows commission preview and submits pending listing', function () {
    $seller = User::factory()->create(['is_admin' => false, 'phone' => '254711000002']);
    $category = Category::factory()->create(['is_active' => true, 'name' => 'Home']);
    $subcategory = Subcategory::factory()->create([
        'category_id' => $category->id,
        'is_active' => true,
        'name' => 'Kitchen',
    ]);

    Livewire::actingAs($seller)
        ->test('pages::sell')
        ->assertSee('Commission preview')
        ->assertSee('10%')
        ->set('name', 'Guest Blender Set')
        ->set('description', 'Barely used blender with jug and spare blades included for kitchen use.')
        ->set('price', '10000')
        ->assertSee('KES')
        ->set('stock', '1')
        ->set('categoryId', (string) $category->id)
        ->set('subcategoryId', (string) $subcategory->id)
        ->set('imageUpload', UploadedFile::fake()->image('blender.jpg'))
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('account'));

    $product = Product::query()->where('slug', 'guest-blender-set')->first();

    expect($product)->not->toBeNull()
        ->and($product->seller_user_id)->toBe($seller->id)
        ->and($product->approval_status)->toBe(Product::APPROVAL_PENDING);
});

test('guest listing submission notifies admin via placeholder channel', function () {
    Log::spy();

    $seller = User::factory()->create(['is_admin' => false, 'phone' => '254711000005', 'name' => 'Mary Seller']);
    $category = Category::factory()->create(['is_active' => true]);
    $subcategory = Subcategory::factory()->create([
        'category_id' => $category->id,
        'is_active' => true,
    ]);

    $product = app(GuestListingService::class)->submit($seller, [
        'name' => 'Guest Side Table',
        'description' => 'Solid wood side table in good condition for living room use.',
        'price' => 4500,
        'stock' => 1,
        'category_id' => $category->id,
        'subcategory_id' => $subcategory->id,
        'image' => '/storage/guest-products/table.jpg',
    ]);

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $message, array $context): bool => $message === 'notification_placeholder'
            && ($context['template'] ?? null) === 'guest_listing_submitted'
            && ($context['product_id'] ?? null) === $product->id)
        ->once();
});

test('guest listings manage permission is required to approve', function () {
    $this->seed(RolePermissionSeeder::class);

    $viewer = User::factory()->create(['is_admin' => false]);
    $viewer->assignRole('Customer Care');

    $seller = User::factory()->create(['is_admin' => false, 'phone' => '254711000006']);
    $product = Product::factory()->create([
        'seller_user_id' => $seller->id,
        'approval_status' => Product::APPROVAL_PENDING,
        'channel' => Product::CHANNEL_NONE,
    ]);

    Livewire::actingAs($viewer->fresh())
        ->test('pages::admin.guest-listings')
        ->assertOk()
        ->call('approve', $product->id)
        ->assertForbidden();
});

test('checkout on an approved guest product splits commission and seller payout', function () {
    $seller = User::factory()->create(['is_admin' => false, 'phone' => '254711000003']);
    $buyer = User::factory()->create(['is_admin' => false, 'phone' => '254711000004']);
    $product = Product::factory()->inShop()->create([
        'seller_user_id' => $seller->id,
        'approval_status' => Product::APPROVAL_APPROVED,
        'price' => 10000,
        'stock' => 3,
        'name' => 'Guest Desk Lamp',
    ]);

    app(CartService::class)->add($product->id, 1);

    $order = app(OrderService::class)->placeFromCart([
        'name' => $buyer->name,
        'phone' => '0711000004',
        'delivery_method' => 'collection',
        'user' => $buyer,
    ]);

    $item = $order->items->first();

    expect($item->seller_user_id)->toBe($seller->id)
        ->and($item->commission_amount)->toBe(1000)
        ->and($item->seller_payout_amount)->toBe(9000)
        ->and($item->seller_payout_status)->toBe(OrderItem::PAYOUT_PENDING);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::admin.guest-listings')
        ->call('simulateSellerPayout', $item->id)
        ->assertHasNoErrors();

    expect($item->fresh()->seller_payout_status)->toBe(OrderItem::PAYOUT_PAID);
});
