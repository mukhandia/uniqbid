<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Subcategory;
use App\Models\User;
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

test('non-admin cannot access catalog admin pages', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $this->actingAs($user);

    $this->get(route('admin.categories'))->assertForbidden();
    $this->get(route('admin.products'))->assertForbidden();
});
