<?php

use App\Models\Auction;
use App\Models\Category;
use App\Models\Product;
use App\Models\Subcategory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('admin can create and update categories and subcategories', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::admin.categories')
        ->set('categoryName', 'Watches')
        ->call('saveCategory')
        ->assertHasNoErrors();

    $category = Category::query()->where('name', 'Watches')->first();
    expect($category)->not->toBeNull()
        ->and($category->slug)->toBe('watches');

    Livewire::test('pages::admin.categories')
        ->call('selectCategory', $category->id)
        ->set('subcategoryName', "Men's watches")
        ->call('saveSubcategory')
        ->assertHasNoErrors();

    expect(Subcategory::query()->where('name', "Men's watches")->exists())->toBeTrue();
});

test('admin can create a product under a subcategory', function () {
    $this->actingAs($this->admin);

    $category = Category::factory()->create(['name' => 'Jewellery', 'slug' => 'jewellery']);
    $subcategory = Subcategory::factory()->create([
        'category_id' => $category->id,
        'name' => 'Earrings',
        'slug' => 'earrings',
    ]);

    Livewire::test('pages::admin.products.create')
        ->set('name', 'Stud earring set')
        ->set('categoryId', (string) $category->id)
        ->set('subcategoryId', (string) $subcategory->id)
        ->set('description', 'Fashion jewellery stud set.')
        ->set('price', 750)
        ->set('stock', 20)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.products'));

    $product = Product::query()->where('name', 'Stud earring set')->first();

    expect($product)->not->toBeNull()
        ->and($product->subcategory_id)->toBe($subcategory->id)
        ->and($product->category)->toBe('Jewellery')
        ->and($product->subcategory)->toBe('Earrings')
        ->and($product->price)->toBe(750);
});

test('admin product form shows image guidance and a formatted chooser', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::admin.products.create')
        ->assertSee('Choose image')
        ->assertDontSee('No file chosen', false)
        ->assertSee('Square 1:1 photos display best')
        ->assertSee('JPG, PNG, or WebP')
        ->assertSee('1024×1024px')
        ->assertSee('Allow shop returns');
});

test('admin can upload a jpg product image', function () {
    Storage::fake('public');
    $this->actingAs($this->admin);

    $category = Category::factory()->create(['name' => 'Watches', 'slug' => 'watches']);
    $subcategory = Subcategory::factory()->create([
        'category_id' => $category->id,
        'name' => "Men's watches",
        'slug' => 'mens-watches',
    ]);
    $file = UploadedFile::fake()->image('dress-watch.jpg', 1024, 1024);

    Livewire::test('pages::admin.products.create')
        ->set('name', 'Classic leather-strap dress watch')
        ->set('categoryId', (string) $category->id)
        ->set('subcategoryId', (string) $subcategory->id)
        ->set('description', 'Leather strap dress watch.')
        ->set('price', 2650)
        ->set('stock', 10)
        ->set('imageUpload', $file)
        ->assertSee('dress-watch.jpg')
        ->assertSee('Replace image')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.products'));

    $product = Product::query()->where('name', 'Classic leather-strap dress watch')->first();

    expect($product)->not->toBeNull()
        ->and($product->image)->toStartWith('/storage/products/');

    Storage::disk('public')->assertExists(str_replace('/storage/', '', $product->image));
});

test('admin product images reject formats other than jpg png or webp', function () {
    $this->actingAs($this->admin);

    $category = Category::factory()->create();
    $subcategory = Subcategory::factory()->create(['category_id' => $category->id]);

    Livewire::test('pages::admin.products.create')
        ->set('name', 'Animated gift')
        ->set('categoryId', (string) $category->id)
        ->set('subcategoryId', (string) $subcategory->id)
        ->set('description', 'Should not accept a gif.')
        ->set('price', 500)
        ->set('stock', 3)
        ->set('imageUpload', UploadedFile::fake()->image('gift.gif', 1024, 1024))
        ->call('save')
        ->assertHasErrors(['imageUpload']);

    expect(Product::query()->where('name', 'Animated gift')->exists())->toBeFalse();
});

test('admin can create a product without gender age or price tier', function () {
    $this->actingAs($this->admin);

    $category = Category::factory()->create(['name' => 'Home', 'slug' => 'home']);
    $subcategory = Subcategory::factory()->create([
        'category_id' => $category->id,
        'name' => 'Decor',
        'slug' => 'decor',
    ]);

    Livewire::test('pages::admin.products.create')
        ->set('name', 'Plain vase')
        ->set('categoryId', (string) $category->id)
        ->set('subcategoryId', (string) $subcategory->id)
        ->set('description', 'Simple ceramic vase.')
        ->set('price', 1200)
        ->set('stock', 5)
        ->set('genderAge', '')
        ->set('priceTier', '')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.products'));

    $product = Product::query()->where('name', 'Plain vase')->first();

    expect($product)->not->toBeNull()
        ->and($product->gender_age)->toBeNull()
        ->and($product->price_tier)->toBeNull();
});

test('admin can edit a product on its own page', function () {
    $this->actingAs($this->admin);

    $category = Category::factory()->create(['name' => 'Jewellery', 'slug' => 'jewellery']);
    $subcategory = Subcategory::factory()->create([
        'category_id' => $category->id,
        'name' => 'Earrings',
        'slug' => 'earrings',
    ]);
    $product = Product::factory()->create([
        'name' => 'Old earring name',
        'subcategory_id' => $subcategory->id,
        'category' => 'Jewellery',
        'subcategory' => 'Earrings',
        'price' => 500,
        'stock' => 4,
    ]);

    Livewire::test('pages::admin.products.edit', ['product' => $product])
        ->assertSet('name', 'Old earring name')
        ->set('name', 'Updated earring set')
        ->set('price', 900)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.products'));

    expect($product->fresh()->name)->toBe('Updated earring set')
        ->and($product->fresh()->price)->toBe(900);
});

test('admin can create a sale from a product as the homepage hero', function () {
    $this->actingAs($this->admin);

    $previous = Auction::factory()->create(['is_featured' => true]);
    $product = Product::factory()->create([
        'name' => 'Sale from product watch',
        'price' => 2650,
        'stock' => 4,
        'reserved_stock' => 0,
    ]);

    Livewire::test('pages::admin.products')
        ->call('openSaleForm', $product->id)
        ->assertSet('saleBidFee', 3)
        ->assertSet('saleMinBid', 1)
        ->assertSet('saleMaxBid', 265)
        ->set('saleFeatureOnHomepage', true)
        ->call('createSale')
        ->assertHasNoErrors();

    $sale = Auction::query()->where('product_id', $product->id)->first();

    expect($sale)->not->toBeNull()
        ->and($sale->bid_fee)->toBe(3)
        ->and($sale->min_bid)->toBe(1)
        ->and($sale->max_bid)->toBe(265)
        ->and($sale->is_featured)->toBeTrue()
        ->and($previous->fresh()->is_featured)->toBeFalse();
});

test('admin products page points to the live sale instead of creating another', function () {
    $this->actingAs($this->admin);

    $product = Product::factory()->create([
        'name' => 'Single Live Watch',
        'stock' => 4,
        'reserved_stock' => 1,
    ]);
    $sale = Auction::factory()->create([
        'product_id' => $product->id,
        'name' => $product->name,
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    Livewire::test('pages::admin.products')
        ->assertSee('View sale')
        ->assertDontSee('Create sale')
        ->call('openSaleForm', $product->id)
        ->assertRedirect(route('admin.auction-bids', $sale));
});

test('admin products page asks to settle an ended sale before creating another', function () {
    $this->actingAs($this->admin);

    $product = Product::factory()->create([
        'name' => 'Ended Camera Sale Product',
        'stock' => 4,
        'reserved_stock' => 1,
    ]);
    Auction::factory()->create([
        'product_id' => $product->id,
        'name' => $product->name,
        'status' => 'live',
        'ends_at' => now()->subHour(),
        'settled_at' => null,
    ]);

    Livewire::test('pages::admin.products')
        ->assertSee('Settle first')
        ->assertDontSee('Create sale')
        ->call('openSaleForm', $product->id)
        ->assertRedirect(route('admin.auctions', [
            'status' => 'needs_settle',
            'search' => $product->name,
        ]));
});

test('non-admin cannot access catalog admin pages', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $this->actingAs($user);

    $this->get(route('admin.categories'))->assertForbidden();
    $this->get(route('admin.products'))->assertForbidden();
    $this->get(route('admin.products.create'))->assertForbidden();
});
