<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Subcategory;
use App\Services\CreateSaleFromProductService;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new
#[Layout('layouts.app')]
#[Title('Admin: Products')]
class extends Component {
    use WithFileUploads;
    use WithPagination;

    public string $search = '';

    public string $filterCategoryId = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $categoryId = '';

    public string $subcategoryId = '';

    public string $genderAge = 'Unisex';

    public string $priceTier = 'Affordable';

    public string $description = '';

    public int $price = 0;

    public int $stock = 0;

    public bool $isFeatured = false;

    public bool $isReturnable = true;

    public $imageUpload = null;

    public string $existingImage = '';

    public ?int $saleProductId = null;

    public int $saleBidFee = 50;

    public int $saleMinBid = 1;

    public int $saleMaxBid = 5000;

    public int $saleEndsInHours = 24;

    public bool $saleReserveStock = true;

    public bool $saleFeatureOnHomepage = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterCategoryId(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryId(): void
    {
        $this->subcategoryId = '';
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $productId): void
    {
        $product = Product::query()->with('subcategoryRelation.category')->findOrFail($productId);
        $this->editingId = $product->id;
        $this->name = $product->name;
        $this->categoryId = (string) ($product->subcategoryRelation?->category_id ?? '');
        $this->subcategoryId = (string) ($product->subcategory_id ?? '');
        $this->genderAge = $product->gender_age ?? 'Unisex';
        $this->priceTier = $product->price_tier ?? 'Affordable';
        $this->description = $product->description;
        $this->price = $product->price;
        $this->stock = $product->stock;
        $this->isFeatured = $product->is_featured;
        $this->isReturnable = $product->is_returnable;
        $this->existingImage = $product->image;
        $this->imageUpload = null;
        $this->showForm = true;
        $this->saleProductId = null;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'categoryId' => ['required', 'exists:categories,id'],
            'subcategoryId' => [
                'required',
                Rule::exists('subcategories', 'id')->where(fn ($q) => $q->where('category_id', $this->categoryId)),
            ],
            'genderAge' => ['required', 'string', 'max:40'],
            'priceTier' => ['required', 'string', 'max:40'],
            'description' => ['required', 'string'],
            'price' => ['required', 'integer', 'min:1'],
            'stock' => ['required', 'integer', 'min:0'],
            'isFeatured' => ['boolean'],
            'isReturnable' => ['boolean'],
            'imageUpload' => $this->imageUploadRules(),
        ]);

        $subcategory = Subcategory::query()->with('category')->findOrFail((int) $validated['subcategoryId']);
        $slugBase = Str::slug($validated['name']);
        $slug = $slugBase;
        $i = 1;

        while (
            Product::query()
                ->where('slug', $slug)
                ->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))
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
            'gender_age' => $validated['genderAge'],
            'price_tier' => $validated['priceTier'],
            'description' => $validated['description'],
            'price' => $validated['price'],
            'stock' => $validated['stock'],
            'image' => $imagePath,
            'is_featured' => $validated['isFeatured'],
            'is_returnable' => $validated['isReturnable'],
        ];

        if ($this->editingId) {
            $product = Product::query()->findOrFail($this->editingId);

            if ($validated['stock'] < $product->reserved_stock) {
                Flux::toast(variant: 'danger', text: 'Stock cannot be lower than reserved units ('.$product->reserved_stock.').');

                return;
            }

            $product->update($payload);
            Flux::toast(variant: 'success', text: 'Product updated.');
        } else {
            Product::query()->create($payload);
            Flux::toast(variant: 'success', text: 'Product created.');
        }

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $productId): void
    {
        $product = Product::query()->findOrFail($productId);

        if ($product->reserved_stock > 0) {
            Flux::toast(variant: 'danger', text: 'Cannot delete while units are reserved for live sales.');

            return;
        }

        $product->delete();
        Flux::toast(variant: 'success', text: 'Product deleted.');
    }

    public function openSaleForm(int $productId): void
    {
        $product = Product::query()->findOrFail($productId);
        $this->saleProductId = $product->id;
        $this->saleBidFee = 50;
        $this->saleMinBid = 1;
        $this->saleMaxBid = max(100, (int) round($product->price * 0.5));
        $this->saleEndsInHours = 24;
        $this->saleReserveStock = true;
        $this->saleFeatureOnHomepage = false;
        $this->showForm = false;
    }

    public function createSale(CreateSaleFromProductService $service): void
    {
        $this->validate([
            'saleProductId' => ['required', 'exists:products,id'],
            'saleBidFee' => ['required', 'integer', 'min:1'],
            'saleMinBid' => ['required', 'integer', 'min:1'],
            'saleMaxBid' => ['required', 'integer', 'gte:saleMinBid'],
            'saleEndsInHours' => ['required', 'integer', 'min:1', 'max:168'],
            'saleReserveStock' => ['boolean'],
            'saleFeatureOnHomepage' => ['boolean'],
        ]);

        $product = Product::query()->findOrFail($this->saleProductId);

        try {
            $auction = $service->handle($product, [
                'bid_fee' => $this->saleBidFee,
                'min_bid' => $this->saleMinBid,
                'max_bid' => $this->saleMaxBid,
                'ends_in_hours' => $this->saleEndsInHours,
                'reserve_stock' => $this->saleReserveStock,
                'is_featured' => $this->saleFeatureOnHomepage,
            ]);
        } catch (ValidationException $exception) {
            Flux::toast(variant: 'danger', text: collect($exception->errors())->flatten()->first() ?? 'Unable to create sale.');

            throw $exception;
        }

        $this->saleProductId = null;
        Flux::toast(variant: 'success', text: 'Sale created: '.$auction->name);
        $this->redirect(route('admin.auctions'), navigate: true);
    }

    public function cancelSaleForm(): void
    {
        $this->saleProductId = null;
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->categoryId = '';
        $this->subcategoryId = '';
        $this->genderAge = 'Unisex';
        $this->priceTier = 'Affordable';
        $this->description = '';
        $this->price = 0;
        $this->stock = 0;
        $this->isFeatured = false;
        $this->isReturnable = true;
        $this->imageUpload = null;
        $this->existingImage = '';
        $this->resetValidation();
    }

    public function updatedImageUpload(): void
    {
        $this->validateOnly('imageUpload', [
            'imageUpload' => $this->imageUploadRules(),
        ]);
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

    /**
     * @return list<string>
     */
    private function imageUploadRules(): array
    {
        return ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:4096'];
    }

    public function with(): array
    {
        $query = Product::query()
            ->with(['subcategoryRelation.category'])
            ->search($this->search !== '' ? $this->search : null)
            ->latest();

        if ($this->filterCategoryId !== '') {
            $query->whereHas('subcategoryRelation', fn ($q) => $q->where('category_id', $this->filterCategoryId));
        }

        $subcategories = $this->categoryId !== ''
            ? Subcategory::query()->where('category_id', $this->categoryId)->orderBy('sort_order')->orderBy('name')->get()
            : collect();

        return [
            'products' => $query->paginate(15),
            'categories' => Category::query()->orderBy('sort_order')->orderBy('name')->get(),
            'subcategories' => $subcategories,
            'saleProduct' => $this->saleProductId
                ? Product::query()->find($this->saleProductId)
                : null,
        ];
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Products</flux:heading>
            <flux:text class="mt-1">Manage shop inventory. Create a live sale from any product with available stock.</flux:text>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button href="{{ route('admin.categories') }}" variant="outline">Categories</flux:button>
            <flux:button href="{{ route('admin.auctions') }}" variant="outline">Sales</flux:button>
            <flux:button wire:click="openCreate" class="bg-accent! text-accent-foreground!">Add product</flux:button>
        </div>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Search products…" class="sm:max-w-sm" />
        <flux:select wire:model.live="filterCategoryId" class="sm:max-w-xs">
            <flux:select.option value="">All categories</flux:select.option>
            @foreach ($categories as $category)
                <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @if ($showForm)
        <form wire:submit="save" class="space-y-4 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold">{{ $editingId ? 'Edit product' : 'New product' }}</h2>
                <flux:button type="button" variant="ghost" wire:click="$set('showForm', false)">Close</flux:button>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <flux:field class="md:col-span-2">
                    <flux:label>Name</flux:label>
                    <flux:input wire:model="name" />
                    <flux:error name="name" />
                </flux:field>
                <flux:field>
                    <flux:label>Category</flux:label>
                    <flux:select wire:model.live="categoryId">
                        <flux:select.option value="">Select…</flux:select.option>
                        @foreach ($categories as $category)
                            <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="categoryId" />
                </flux:field>
                <flux:field>
                    <flux:label>Sub-category</flux:label>
                    <flux:select wire:model="subcategoryId">
                        <flux:select.option value="">Select…</flux:select.option>
                        @foreach ($subcategories as $subcategory)
                            <flux:select.option value="{{ $subcategory->id }}">{{ $subcategory->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="subcategoryId" />
                </flux:field>
                <flux:field>
                    <flux:label>Price (KES)</flux:label>
                    <flux:input type="number" wire:model="price" min="1" />
                    <flux:error name="price" />
                </flux:field>
                <flux:field>
                    <flux:label>Stock</flux:label>
                    <flux:input type="number" wire:model="stock" min="0" />
                    <flux:error name="stock" />
                </flux:field>
                <flux:field>
                    <flux:label>Gender / age</flux:label>
                    <flux:input wire:model="genderAge" />
                </flux:field>
                <flux:field>
                    <flux:label>Price tier</flux:label>
                    <flux:input wire:model="priceTier" />
                </flux:field>
                <flux:field class="md:col-span-2">
                    <flux:label>Description</flux:label>
                    <flux:textarea wire:model="description" rows="3" />
                    <flux:error name="description" />
                </flux:field>
                <flux:field class="md:col-span-2">
                    <flux:label>Product image</flux:label>
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                        <div class="aspect-square w-32 shrink-0 overflow-hidden rounded-xl border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800">
                            @if ($this->imagePreviewUrl())
                                <img src="{{ $this->imagePreviewUrl() }}" alt="Product image preview" class="h-full w-full object-cover" />
                            @else
                                <div class="grid h-full place-items-center text-zinc-400">
                                    <flux:icon.photo class="size-8" />
                                </div>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1 space-y-2">
                            <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm font-medium text-zinc-800 shadow-sm hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800">
                                <flux:icon.arrow-up-tray class="size-4" />
                                <span>{{ $imageUpload ? 'Replace image' : 'Choose image' }}</span>
                                <input type="file" wire:model="imageUpload" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="sr-only" />
                            </label>
                            @if ($imageUpload)
                                <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-600 dark:text-zinc-300">
                                    <span class="truncate">{{ $imageUpload->getClientOriginalName() }}</span>
                                    <button type="button" class="font-medium text-navy underline hover:no-underline dark:text-zinc-100" wire:click="clearImageUpload">
                                        Remove
                                    </button>
                                </div>
                            @endif
                            <p wire:loading wire:target="imageUpload" class="text-xs text-zinc-500">Uploading preview…</p>
                            <p class="text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">
                                Square 1:1 photos display best. Use JPG, PNG, or WebP, 1024×1024px recommended (minimum 800×800px), max 4 MB. Shop tiles, product pages, and the homepage all show a square crop; wide or tall photos will lose the edges.
                            </p>
                            @if ($existingImage && ! $imageUpload)
                                <p class="text-xs text-zinc-500">Current image is in use until you choose a replacement.</p>
                            @endif
                            <flux:error name="imageUpload" />
                        </div>
                    </div>
                </flux:field>
                <div class="flex flex-col gap-2 md:col-span-2">
                    <div class="flex flex-wrap gap-4">
                        <flux:checkbox wire:model="isFeatured" label="Show in homepage shop" />
                        <flux:checkbox wire:model="isReturnable" label="Allow shop returns" />
                    </div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        Leave returns on for normal shop items (7 days if unused). Turn it off for hygiene, personalised, or perishable goods. Customers then see a non-returnable notice on the product page, at checkout, and on the order.
                    </p>
                </div>
            </div>
            <flux:button type="submit">{{ $editingId ? 'Save changes' : 'Create product' }}</flux:button>
        </form>
    @endif

    @if ($saleProduct)
        <form wire:submit="createSale" class="space-y-4 rounded-xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-900 dark:bg-amber-950/30">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="font-semibold">Create sale from product</h2>
                    <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ $saleProduct->name }} · available {{ $saleProduct->availableStock() }} · retail {{ Money::kes($saleProduct->price) }}</p>
                </div>
                <flux:button type="button" variant="ghost" wire:click="cancelSaleForm">Cancel</flux:button>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <flux:field>
                    <flux:label>Bid fee (KES)</flux:label>
                    <flux:input type="number" wire:model="saleBidFee" min="1" />
                    <flux:error name="saleBidFee" />
                </flux:field>
                <flux:field>
                    <flux:label>Min bid</flux:label>
                    <flux:input type="number" wire:model="saleMinBid" min="1" />
                </flux:field>
                <flux:field>
                    <flux:label>Max bid</flux:label>
                    <flux:input type="number" wire:model="saleMaxBid" min="1" />
                    <flux:error name="saleMaxBid" />
                </flux:field>
                <flux:field>
                    <flux:label>Ends in (hours)</flux:label>
                    <flux:input type="number" wire:model="saleEndsInHours" min="1" max="168" />
                </flux:field>
            </div>
            <flux:checkbox wire:model="saleReserveStock" label="Reserve 1 unit of shop stock while the sale is live" />
            <flux:checkbox wire:model="saleFeatureOnHomepage" label="Show as homepage hero (replaces any current homepage sale)" />
            <flux:button type="submit" class="bg-accent! text-accent-foreground!">Create live sale</flux:button>
        </form>
    @endif

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
            <thead class="bg-zinc-50 text-left dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 font-medium">Product</th>
                    <th class="px-4 py-3 font-medium">Category</th>
                    <th class="px-4 py-3 font-medium">Price</th>
                    <th class="px-4 py-3 font-medium">Stock</th>
                    <th class="px-4 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($products as $product)
                    <tr wire:key="product-{{ $product->id }}">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <img src="{{ $product->image }}" alt="" class="size-10 rounded object-cover" />
                                <div>
                                    <div class="font-medium">{{ $product->name }}</div>
                                    <div class="text-xs text-zinc-500">{{ $product->subcategory }}</div>
                                    @if ($product->is_featured)
                                        <span class="mt-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-900 dark:bg-amber-900/40 dark:text-amber-100">Homepage shop</span>
                                    @endif
                                    @unless ($product->is_returnable)
                                        <span class="mt-1 inline-flex rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">Non-returnable</span>
                                    @endunless
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-xs">{{ $product->category }}</td>
                        <td class="px-4 py-3">{{ Money::kes($product->price) }}</td>
                        <td class="px-4 py-3 text-xs">
                            <div>{{ $product->availableStock() }} available</div>
                            @if ($product->reserved_stock > 0)
                                <div class="text-amber-700">{{ $product->reserved_stock }} reserved</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-1">
                                <flux:button size="sm" variant="outline" wire:click="openSaleForm({{ $product->id }})">Create sale</flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="edit({{ $product->id }})">Edit</flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="delete({{ $product->id }})" wire:confirm="Delete this product?">Del</flux:button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div>{{ $products->links() }}</div>
</div>
