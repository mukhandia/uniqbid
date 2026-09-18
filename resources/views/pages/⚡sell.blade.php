<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\Subcategory;
use App\Services\GuestListingService;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new
#[Layout('layouts.site')]
#[Title('Sell on UniqBid')]
class extends Component {
    use WithFileUploads;

    public string $name = '';

    public string $description = '';

    public string $price = '';

    public string $stock = '1';

    public string $categoryId = '';

    public string $subcategoryId = '';

    public $imageUpload = null;

    public function mount(): void
    {
        $user = Auth::user();

        if ($user === null || $user->is_admin) {
            $this->redirect(route('mobile-login', ['redirect' => route('sell')]), navigate: true);
        }
    }

    public function updatedCategoryId(): void
    {
        $this->subcategoryId = '';
    }

    public function updatedPrice(): void
    {
        // Triggers commission preview recompute via Livewire.
    }

    #[Computed]
    public function commissionPercent(): int
    {
        return SiteSetting::current()->guestShopCommissionPercent();
    }

    #[Computed]
    public function previewCommission(): int
    {
        $price = max(0, (int) $this->price);

        return (int) floor(($price * $this->commissionPercent) / 100);
    }

    #[Computed]
    public function previewPayout(): int
    {
        return max(0, (int) $this->price - $this->previewCommission);
    }

    public function submit(GuestListingService $listings): void
    {
        $user = Auth::user();

        abort_unless($user !== null && ! $user->is_admin, 403);

        $validated = $this->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'description' => ['required', 'string', 'min:20', 'max:2000'],
            'price' => ['required', 'integer', 'min:100', 'max:5000000'],
            'stock' => ['required', 'integer', 'min:1', 'max:100'],
            'categoryId' => ['required', 'integer', 'exists:categories,id'],
            'subcategoryId' => ['required', 'integer', 'exists:subcategories,id'],
            'imageUpload' => ['required', 'image', 'max:4096'],
        ], [
            'imageUpload.required' => 'Add a clear product photo.',
        ]);

        if (! $this->imageUpload instanceof TemporaryUploadedFile) {
            $this->addError('imageUpload', 'Add a clear product photo.');

            return;
        }

        $stored = $this->imageUpload->store('guest-products', 'public');

        try {
            $product = $listings->submit($user, [
                'name' => $validated['name'],
                'description' => $validated['description'],
                'price' => (int) $validated['price'],
                'stock' => (int) $validated['stock'],
                'category_id' => (int) $validated['categoryId'],
                'subcategory_id' => (int) $validated['subcategoryId'],
                'image' => '/storage/'.$stored,
            ]);
        } catch (ValidationException $exception) {
            Flux::toast(variant: 'danger', text: collect($exception->errors())->flatten()->first() ?? 'Unable to submit listing.');

            throw $exception;
        }

        $this->reset(['name', 'description', 'price', 'stock', 'categoryId', 'subcategoryId', 'imageUpload']);

        Flux::toast(variant: 'success', text: 'Listing submitted for admin approval: '.$product->name);
        $this->redirect(route('account'), navigate: true);
    }

    public function with(): array
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $subcategories = $this->categoryId !== ''
            ? Subcategory::query()
                ->where('category_id', (int) $this->categoryId)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();

        $myListings = Auth::user()
            ? Product::query()
                ->where('seller_user_id', Auth::id())
                ->latest('id')
                ->limit(10)
                ->get()
            : collect();

        return [
            'categories' => $categories,
            'subcategories' => $subcategories,
            'myListings' => $myListings,
        ];
    }
}; ?>

<div class="mx-auto max-w-3xl px-4 py-10">
    <div class="text-center">
        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-gold">Guest shop listings</div>
        <h1 class="mt-2 text-3xl font-bold text-navy">Sell on UniqBid</h1>
        <p class="mx-auto mt-3 max-w-xl text-sm text-muted-foreground">
            Submit a shop-only product for review. Listings only appear after UniqBid approves them. Auctions are not available for guest products.
        </p>
    </div>

    <div class="mt-8 rounded-2xl border border-border bg-white p-6 shadow-sm">
        <form wire:submit="submit" class="space-y-5">
            <flux:field>
                <flux:label>Product name</flux:label>
                <flux:input wire:model="name" placeholder="e.g. Leather travel wallet" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>Description</flux:label>
                <flux:textarea wire:model="description" rows="4" placeholder="Condition, size, what’s included…" />
                <flux:error name="description" />
            </flux:field>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Category</flux:label>
                    <flux:select wire:model.live="categoryId">
                        <flux:select.option value="">Select category</flux:select.option>
                        @foreach ($categories as $category)
                            <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="categoryId" />
                </flux:field>
                <flux:field>
                    <flux:label>Subcategory</flux:label>
                    <flux:select wire:model="subcategoryId" :disabled="$categoryId === ''">
                        <flux:select.option value="">Select subcategory</flux:select.option>
                        @foreach ($subcategories as $subcategory)
                            <flux:select.option value="{{ $subcategory->id }}">{{ $subcategory->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="subcategoryId" />
                </flux:field>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Price (KES)</flux:label>
                    <flux:input wire:model.live.debounce.300ms="price" type="number" min="100" inputmode="numeric" />
                    <flux:error name="price" />
                </flux:field>
                <flux:field>
                    <flux:label>Stock</flux:label>
                    <flux:input wire:model="stock" type="number" min="1" inputmode="numeric" />
                    <flux:error name="stock" />
                </flux:field>
            </div>

            <div class="rounded-xl border border-gold/30 bg-gold/5 p-4 text-sm">
                <div class="font-semibold text-navy">Commission preview</div>
                <p class="mt-1 text-muted-foreground">
                    UniqBid keeps {{ $this->commissionPercent }}% when a customer pays. The rest is wired to you (M-Pesa mock for now).
                </p>
                <dl class="mt-3 grid gap-2 sm:grid-cols-3">
                    <div>
                        <dt class="text-xs text-muted-foreground">Listing price</dt>
                        <dd class="font-semibold">{{ Money::kes((int) $price) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">UniqBid commission</dt>
                        <dd class="font-semibold">{{ Money::kes($this->previewCommission) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">You receive</dt>
                        <dd class="font-semibold text-emerald-700">{{ Money::kes($this->previewPayout) }}</dd>
                    </div>
                </dl>
            </div>

            <flux:field>
                <flux:label>Product photo</flux:label>
                <input type="file" wire:model="imageUpload" accept="image/*" class="block w-full text-sm text-zinc-600 file:mr-3 file:rounded-md file:border-0 file:bg-surface file:px-3 file:py-1.5 file:text-sm file:font-medium" />
                <flux:error name="imageUpload" />
            </flux:field>

            <flux:button type="submit" class="w-full bg-accent! text-accent-foreground!">
                Submit for approval
            </flux:button>
        </form>
    </div>

    @if ($myListings->isNotEmpty())
        <div class="mt-10">
            <h2 class="text-lg font-bold text-navy">Your recent listings</h2>
            <div class="mt-4 space-y-3">
                @foreach ($myListings as $listing)
                    <div class="flex items-center gap-3 rounded-xl border border-border bg-white p-3">
                        <img src="{{ $listing->image }}" alt="" class="size-14 rounded-lg object-cover">
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-semibold">{{ $listing->name }}</div>
                            <div class="text-xs text-muted-foreground">{{ $listing->approvalLabel() }} · {{ Money::kes($listing->price) }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
