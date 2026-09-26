@props([
    'submitLabel' => 'Save',
    'categories',
    'subcategories',
])

@php
    $requiredMark = '<span class="text-red-600" aria-hidden="true">*</span>';
@endphp

<div class="grid gap-4 md:grid-cols-2">
    <flux:field class="md:col-span-2">
        <flux:label>Name {!! $requiredMark !!}</flux:label>
        <flux:input wire:model="name" required />
        <flux:error name="name" />
    </flux:field>
    <flux:field>
        <flux:label>Category {!! $requiredMark !!}</flux:label>
        <flux:select wire:model.live="categoryId" required>
            <flux:select.option value="">Select…</flux:select.option>
            @foreach ($categories as $category)
                <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:error name="categoryId" />
    </flux:field>
    <flux:field>
        <flux:label>Sub-category {!! $requiredMark !!}</flux:label>
        <flux:select wire:model="subcategoryId" required>
            <flux:select.option value="">Select…</flux:select.option>
            @foreach ($subcategories as $subcategory)
                <flux:select.option value="{{ $subcategory->id }}">{{ $subcategory->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:error name="subcategoryId" />
    </flux:field>
    <flux:field>
        <flux:label>Price (KES) {!! $requiredMark !!}</flux:label>
        <flux:input type="number" wire:model="price" min="1" required />
        <flux:error name="price" />
    </flux:field>
    <flux:field>
        <flux:label>Stock {!! $requiredMark !!}</flux:label>
        <flux:input type="number" wire:model="stock" min="0" required />
        <flux:error name="stock" />
    </flux:field>
    <flux:field class="md:col-span-2">
        <flux:label>Description {!! $requiredMark !!}</flux:label>
        <flux:textarea wire:model="description" rows="3" required />
        <flux:error name="description" />
    </flux:field>
    <flux:field>
        <flux:label>Gender / age</flux:label>
        <flux:input wire:model="genderAge" placeholder="e.g. Unisex, Men, Women, Kids" />
        <flux:error name="genderAge" />
    </flux:field>
    <flux:field>
        <flux:label>Price tier</flux:label>
        <flux:input wire:model="priceTier" placeholder="e.g. Affordable, Mid-range, Premium" />
        <flux:error name="priceTier" />
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
                    <input type="file" wire:model="imageUpload" accept="{{ \App\Support\ProductImageRules::acceptAttribute() }}" class="sr-only" />
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
                    {{ \App\Support\ProductImageRules::guidance() }}
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
            <flux:checkbox wire:model="isFeatured" label="Set as Featured" />
            <flux:checkbox wire:model="isReturnable" label="Allow shop returns" />
        </div>
        <p class="text-xs text-zinc-500 dark:text-zinc-400">
            Leave returns on for normal shop items (7 days if unused). Turn it off for hygiene, personalised, or perishable goods. Customers then see a non-returnable notice on the product page, at checkout, and on the order.
        </p>
    </div>
</div>

<p class="mt-4 text-xs text-zinc-500 dark:text-zinc-400">
    <span class="text-red-600" aria-hidden="true">*</span> Required fields
</p>

<div class="mt-6 flex flex-wrap gap-2">
    <flux:button type="submit" class="bg-accent! text-accent-foreground!">{{ $submitLabel }}</flux:button>
    <flux:button type="button" variant="ghost" href="{{ route('admin.products') }}" wire:navigate>Cancel</flux:button>
</div>
