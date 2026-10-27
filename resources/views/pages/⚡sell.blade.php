<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\Subcategory;
use App\Services\GuestListingService;
use App\Support\Money;
use App\Support\ProductImageRules;
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

    public function updatedImageUpload(): void
    {
        $this->validateOnly('imageUpload', [
            'imageUpload' => ProductImageRules::required(),
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

        return null;
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
            'categoryId' => ['required', 'integer', 'exists:categories,id'],
            'subcategoryId' => ['required', 'integer', 'exists:subcategories,id'],
            'imageUpload' => ProductImageRules::required(),
        ], ProductImageRules::messages());

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
                'category_id' => (int) $validated['categoryId'],
                'subcategory_id' => (int) $validated['subcategoryId'],
                'image' => '/storage/'.$stored,
            ]);
        } catch (ValidationException $exception) {
            Flux::toast(variant: 'danger', text: collect($exception->errors())->flatten()->first() ?? 'Unable to submit listing.');

            throw $exception;
        }

        $this->reset(['name', 'description', 'price', 'categoryId', 'subcategoryId', 'imageUpload']);

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
                ->with(['orderItems.order'])
                ->latest('id')
                ->limit(20)
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
            Submit one shop-only item for review. Stock is fixed at one unit. Listings appear only after UniqBid approves them. You can monitor status here; only UniqBid staff can edit or approve listings.
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

            <flux:field>
                <flux:label>Price (KES)</flux:label>
                <flux:input wire:model.live.debounce.300ms="price" type="number" min="100" inputmode="numeric" />
                <flux:error name="price" />
                <flux:description>Each guest listing is one unit only. UniqBid manages stock after approval.</flux:description>
            </flux:field>

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
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
                    <div class="aspect-square w-32 shrink-0 overflow-hidden rounded-xl border border-border bg-surface">
                        @if ($this->imagePreviewUrl())
                            <img src="{{ $this->imagePreviewUrl() }}" alt="Product image preview" class="h-full w-full object-cover" />
                        @else
                            <div class="grid h-full place-items-center px-2 text-center text-xs text-muted-foreground">Square preview</div>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1 space-y-2">
                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-md border border-border bg-white px-3 py-2 text-sm font-medium text-zinc-800 hover:bg-surface">
                            <span>{{ $imageUpload ? 'Replace image' : 'Choose image' }}</span>
                            <input type="file" wire:model="imageUpload" accept="{{ ProductImageRules::acceptAttribute() }}" class="sr-only" />
                        </label>
                        @if ($imageUpload)
                            <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-600">
                                <span class="truncate">{{ $imageUpload->getClientOriginalName() }}</span>
                                <button type="button" class="font-medium text-navy underline" wire:click="clearImageUpload">Remove</button>
                            </div>
                        @endif
                        <p wire:loading wire:target="imageUpload" class="text-xs text-muted-foreground">Uploading preview…</p>
                        <p class="text-xs leading-relaxed text-muted-foreground">{{ ProductImageRules::guidance() }}</p>
                        <flux:error name="imageUpload" />
                    </div>
                </div>
            </flux:field>

            <flux:button type="submit" class="w-full bg-accent! text-accent-foreground!">
                Submit for approval
            </flux:button>
        </form>
    </div>

    @if ($myListings->isNotEmpty())
        <div class="mt-10">
            <h2 class="text-lg font-bold text-navy">Your listings</h2>
            <p class="mt-1 text-sm text-muted-foreground">View-only status. UniqBid staff approve, edit, and fulfil orders.</p>
            <div class="mt-4 space-y-3">
                @foreach ($myListings as $listing)
                    <div class="flex items-start gap-3 rounded-xl border border-border bg-white p-3" wire:key="my-listing-{{ $listing->id }}">
                        <img src="{{ $listing->image }}" alt="" class="size-14 rounded-lg object-cover">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <div class="truncate font-semibold">{{ $listing->name }}</div>
                                <flux:badge size="sm" :color="$listing->guestListingStatusColor()">
                                    {{ $listing->guestListingStatusLabel() }}
                                </flux:badge>
                            </div>
                            <x-guest-listing-sales :listing="$listing" />
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
