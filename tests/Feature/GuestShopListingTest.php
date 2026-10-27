<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\Subcategory;
use App\Models\User;
use App\Services\CartService;
use App\Services\GuestListingService;
use App\Services\OrderService;
use App\Support\ProductImageRules;
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
        'category_id' => $category->id,
        'subcategory_id' => $subcategory->id,
        'image' => '/storage/guest-products/mug.jpg',
    ]);

    expect($product->approval_status)->toBe(Product::APPROVAL_PENDING)
        ->and($product->stock)->toBe(1)
        ->and($product->channel)->toBe(Product::CHANNEL_NONE)
        ->and(Product::query()->inShop()->whereKey($product->id)->exists())->toBeFalse();

    app(GuestListingService::class)->approve($product->fresh());

    expect($product->fresh()->approval_status)->toBe(Product::APPROVAL_APPROVED)
        ->and($product->fresh()->channel)->toBe(Product::CHANNEL_SHOP)
        ->and(Product::query()->inShop()->whereKey($product->id)->exists())->toBeTrue();
});

test('sell form shows commission preview and submits pending listing with stock one', function () {
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
        ->assertSee(ProductImageRules::guidance())
        ->assertSee('one unit only')
        ->set('name', 'Guest Blender Set')
        ->set('description', 'Barely used blender with jug and spare blades included for kitchen use.')
        ->set('price', '10000')
        ->assertSee('KES')
        ->set('categoryId', (string) $category->id)
        ->set('subcategoryId', (string) $subcategory->id)
        ->set('imageUpload', UploadedFile::fake()->image('blender.jpg', 1024, 1024))
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('account'));

    $product = Product::query()->where('slug', 'guest-blender-set')->first();

    expect($product)->not->toBeNull()
        ->and($product->seller_user_id)->toBe($seller->id)
        ->and($product->stock)->toBe(1)
        ->and($product->approval_status)->toBe(Product::APPROVAL_PENDING)
        ->and($product->image)->toStartWith('/storage/guest-products/');

    Storage::disk('public')->assertExists(str_replace('/storage/', '', $product->image));
});

test('sell form rejects non square or unsupported product images', function () {
    $seller = User::factory()->create(['is_admin' => false, 'phone' => '254711000012']);
    $category = Category::factory()->create(['is_active' => true]);
    $subcategory = Subcategory::factory()->create([
        'category_id' => $category->id,
        'is_active' => true,
    ]);

    Livewire::actingAs($seller)
        ->test('pages::sell')
        ->set('name', 'Guest Wide Frame')
        ->set('description', 'A listing that should fail because the photo is not square enough.')
        ->set('price', '5000')
        ->set('categoryId', (string) $category->id)
        ->set('subcategoryId', (string) $subcategory->id)
        ->set('imageUpload', UploadedFile::fake()->image('wide.jpg', 1200, 800))
        ->call('submit')
        ->assertHasErrors(['imageUpload']);

    Livewire::actingAs($seller)
        ->test('pages::sell')
        ->set('name', 'Guest Gif Frame')
        ->set('description', 'A listing that should fail because gif uploads are not allowed.')
        ->set('price', '5000')
        ->set('categoryId', (string) $category->id)
        ->set('subcategoryId', (string) $subcategory->id)
        ->set('imageUpload', UploadedFile::fake()->image('frame.gif', 1024, 1024))
        ->call('submit')
        ->assertHasErrors(['imageUpload']);
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

test('guest can monitor listing status without editing', function () {
    $seller = User::factory()->create(['is_admin' => false, 'phone' => '254711000007']);
    $pending = Product::factory()->create([
        'name' => 'Pending Guest Chair',
        'seller_user_id' => $seller->id,
        'approval_status' => Product::APPROVAL_PENDING,
        'channel' => Product::CHANNEL_NONE,
        'stock' => 1,
    ]);
    $sold = Product::factory()->inShop()->create([
        'name' => 'Sold Guest Lamp',
        'seller_user_id' => $seller->id,
        'approval_status' => Product::APPROVAL_APPROVED,
        'stock' => 0,
        'price' => 8000,
    ]);
    OrderItem::factory()->create([
        'product_id' => $sold->id,
        'seller_user_id' => $seller->id,
        'product_name' => $sold->name,
        'quantity' => 1,
        'seller_payout_amount' => 7200,
        'seller_payout_status' => OrderItem::PAYOUT_PENDING,
        'commission_amount' => 800,
    ]);
    $cancelledOrder = Order::factory()->create([
        'reference' => 'ORD-CANCELLED',
        'status' => Order::STATUS_CANCELLED,
        'cancelled_at' => now(),
        'paid_at' => null,
    ]);
    OrderItem::factory()->create([
        'order_id' => $cancelledOrder->id,
        'product_id' => $sold->id,
        'seller_user_id' => $seller->id,
        'product_name' => $sold->name,
        'quantity' => 1,
        'seller_payout_amount' => 0,
        'seller_payout_status' => OrderItem::PAYOUT_NONE,
    ]);

    Livewire::actingAs($seller)
        ->test('pages::account')
        ->assertSee('Pending Guest Chair')
        ->assertSee('Pending approval')
        ->assertSee('Sold Guest Lamp')
        ->assertSee('Live in shop')
        ->assertSee('Bought 1 time')
        ->assertSee('1 cancelled')
        ->assertSee('Payout pending');

    expect($pending->guestListingStatusLabel())->toBe('Pending approval')
        ->and($sold->guestListingStatusLabel())->toBe('Live in shop');
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
        'stock' => 1,
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
});

test('guest listing shop orders sync into logistics with guest context', function () {
    $seller = User::factory()->create(['is_admin' => false, 'phone' => '254711000008', 'name' => 'Amina Seller']);
    $buyer = User::factory()->create(['is_admin' => false, 'phone' => '254711000009']);
    $product = Product::factory()->inShop()->create([
        'seller_user_id' => $seller->id,
        'approval_status' => Product::APPROVAL_APPROVED,
        'price' => 6000,
        'stock' => 1,
        'name' => 'Guest Coffee Table',
    ]);

    app(CartService::class)->add($product->id, 1);

    $order = app(OrderService::class)->placeFromCart([
        'name' => $buyer->name,
        'phone' => '0711000009',
        'delivery_method' => 'collection',
        'user' => $buyer,
    ]);

    app(OrderService::class)->markProcessing($order);
    $shipment = $order->fresh()->activeShipment();

    expect($shipment)->not->toBeNull()
        ->and($shipment->notes)->toContain('Guest listing')
        ->and($shipment->notes)->toContain('Guest Coffee Table')
        ->and($shipment->notes)->toContain('Amina Seller')
        ->and($order->fresh(['items'])->hasGuestListingItems())->toBeTrue();

    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.logistics')
        ->assertSee($order->reference)
        ->assertSee('Guest listing')
        ->assertSee('Guest Coffee Table')
        ->assertSee('Amina Seller');

    Livewire::actingAs($admin)
        ->test('pages::admin.order', ['order' => $order])
        ->assertSee('Guest listing')
        ->assertSee('Amina Seller');
});
