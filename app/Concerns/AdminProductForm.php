<?php

namespace App\Concerns;

use App\Models\Category;
use App\Models\Product;
use App\Models\Subcategory;
use App\Support\AuditLogger;
use App\Support\ProductImageRules;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

trait AdminProductForm
{
    use WithFileUploads;

    public string $name = '';

    public string $categoryId = '';

    public string $subcategoryId = '';

    public string $genderAge = '';

    public string $priceTier = '';

    public string $description = '';

    public int $price = 0;

    public int $stock = 0;

    public bool $isFeatured = false;

    public bool $isReturnable = true;

    public $imageUpload = null;

    public string $existingImage = '';

    public function updatedCategoryId(): void
    {
        $this->subcategoryId = '';
    }

    public function updatedImageUpload(): void
    {
        $this->validateOnly('imageUpload', [
            'imageUpload' => $this->imageUploadRules(),
        ], ProductImageRules::messages());
    }

    public function clearImageUpload(): void
    {
        $this->imageUpload = null;
        $this->resetValidation('imageUpload');
    }

    public function imagePreviewUrl(): ?string
    {
        if ($this->imageUpload instanceof TemporaryUploadedFile) {
            try {
                return $this->imageUpload->temporaryUrl();
            } catch (\Throwable) {
                return null;
            }
        }

        return $this->existingImage !== '' ? $this->existingImage : null;
    }

    protected function fillFromProduct(Product $product): void
    {
        $product->loadMissing('subcategoryRelation.category');

        $this->name = $product->name;
        $this->categoryId = (string) ($product->subcategoryRelation?->category_id ?? '');
        $this->subcategoryId = (string) ($product->subcategory_id ?? '');
        $this->genderAge = $product->gender_age ?? '';
        $this->priceTier = $product->price_tier ?? '';
        $this->description = $product->description;
        $this->price = $product->price;
        $this->stock = $product->stock;
        $this->isFeatured = $product->is_featured;
        $this->isReturnable = $product->is_returnable;
        $this->existingImage = $product->image;
        $this->imageUpload = null;
    }

    protected function persistProduct(?Product $existing = null): ?Product
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'categoryId' => ['required', 'exists:categories,id'],
            'subcategoryId' => [
                'required',
                Rule::exists('subcategories', 'id')->where(fn ($q) => $q->where('category_id', $this->categoryId)),
            ],
            'genderAge' => ['nullable', 'string', 'max:40'],
            'priceTier' => ['nullable', 'string', 'max:40'],
            'description' => ['required', 'string'],
            'price' => ['required', 'integer', 'min:1'],
            'stock' => ['required', 'integer', 'min:0'],
            'isFeatured' => ['boolean'],
            'isReturnable' => ['boolean'],
            'imageUpload' => $this->imageUploadRules(),
        ], ProductImageRules::messages());

        $subcategory = Subcategory::query()->with('category')->findOrFail((int) $validated['subcategoryId']);
        $slugBase = Str::slug($validated['name']);
        $slug = $slugBase;
        $i = 1;

        while (
            Product::query()
                ->where('slug', $slug)
                ->when($existing !== null, fn ($q) => $q->whereKeyNot($existing->id))
                ->exists()
        ) {
            $slug = $slugBase.'-'.$i;
            $i++;
        }

        $imagePath = $this->existingImage !== '' ? $this->existingImage : '/images/products/leather-cardholder.png';

        if ($this->imageUpload) {
            $stored = $this->imageUpload->store('products', 'public');
            $imagePath = '/storage/'.$stored;
        }

        $payload = [
            'subcategory_id' => $subcategory->id,
            'slug' => $slug,
            'name' => $validated['name'],
            'category' => $subcategory->category->name,
            'subcategory' => $subcategory->name,
            'gender_age' => filled($validated['genderAge'] ?? null) ? $validated['genderAge'] : null,
            'price_tier' => filled($validated['priceTier'] ?? null) ? $validated['priceTier'] : null,
            'description' => $validated['description'],
            'price' => $validated['price'],
            'stock' => $validated['stock'],
            'image' => $imagePath,
            'is_featured' => $validated['isFeatured'],
            'is_returnable' => $validated['isReturnable'],
        ];

        if ($existing !== null) {
            if ($validated['stock'] < $existing->reserved_stock) {
                Flux::toast(variant: 'danger', text: 'Stock cannot be lower than reserved units ('.$existing->reserved_stock.').');

                return null;
            }

            $existing->update($payload);
            $product = $existing->fresh();
            AuditLogger::log('products', 'product updated', 'updated', $product, [
                'name' => $product->name,
            ]);
            Flux::toast(variant: 'success', text: 'Product updated.');

            return $product;
        }

        $product = Product::query()->create([
            ...$payload,
            'channel' => Product::CHANNEL_NONE,
        ]);
        AuditLogger::log('products', 'product created', 'created', $product, [
            'name' => $product->name,
        ]);
        Flux::toast(variant: 'success', text: 'Product created. Move it to Shop or Auction when ready.');

        return $product;
    }

    /**
     * @return Collection<int, Category>
     */
    protected function formCategories(): Collection
    {
        return Category::query()->orderBy('sort_order')->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Subcategory>
     */
    protected function formSubcategories(): Collection
    {
        if ($this->categoryId === '') {
            return collect();
        }

        return Subcategory::query()
            ->where('category_id', $this->categoryId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return list<string>
     */
    private function imageUploadRules(): array
    {
        return ProductImageRules::optional();
    }
}
