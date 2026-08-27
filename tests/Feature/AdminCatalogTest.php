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

    Livewire::test('pages::admin.products')
        ->call('openCreate')
        ->set('name', 'Stud earring set')
        ->set('categoryId', (string) $category->id)
        ->set('subcategoryId', (string) $subcategory->id)
        ->set('description', 'Fashion jewellery stud set.')
        ->set('price', 750)
        ->set('stock', 20)
        ->call('save')
        ->assertHasNoErrors();

    $product = Product::query()->where('name', 'Stud earring set')->first();

    expect($product)->not->toBeNull()
        ->and($product->subcategory_id)->toBe($subcategory->id)
        ->and($product->category)->toBe('Jewellery')
        ->and($product->subcategory)->toBe('Earrings')
        ->and($product->price)->toBe(750);
});

test('admin product form shows image guidance and a formatted chooser', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::admin.products')
        ->call('openCreate')
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

    Livewire::test('pages::admin.products')
        ->call('openCreate')
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
        ->assertHasNoErrors();

    $product = Product::query()->where('name', 'Classic leather-strap dress watch')->first();

    expect($product)->not->toBeNull()
        ->and($product->image)->toStartWith('/storage/products/');

    Storage::disk('public')->assertExists(str_replace('/storage/', '', $product->image));
});

test('admin product images reject formats other than jpg png or webp', function () {
    $this->actingAs($this->admin);

    $category = Category::factory()->create();
    $subcategory = Subcategory::factory()->create(['category_id' => $category->id]);

    Livewire::test('pages::admin.products')
        ->call('openCreate')
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
        ->set('saleFeatureOnHomepage', true)
        ->call('createSale')
        ->assertHasNoErrors();

    $sale = Auction::query()->where('product_id', $product->id)->first();

    expect($sale)->not->toBeNull()
        ->and($sale->is_featured)->toBeTrue()
        ->and($previous->fresh()->is_featured)->toBeFalse();
});

test('non-admin cannot access catalog admin pages', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $this->actingAs($user);

    $this->get(route('admin.categories'))->assertForbidden();
    $this->get(route('admin.products'))->assertForbidden();
});
