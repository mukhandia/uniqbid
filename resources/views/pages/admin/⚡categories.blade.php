<?php

use App\Models\Category;
use App\Models\Subcategory;
use Flux\Flux;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.app')]
#[Title('Admin: Categories')]
class extends Component {
    public string $categoryName = '';

    public ?int $editingCategoryId = null;

    public ?int $selectedCategoryId = null;

    public string $subcategoryName = '';

    public ?int $editingSubcategoryId = null;

    public function mount(): void
    {
        $this->selectedCategoryId = Category::query()->orderBy('sort_order')->value('id');
    }

    public function saveCategory(): void
    {
        $validated = $this->validate([
            'categoryName' => [
                'required',
                'string',
                'max:120',
                Rule::unique('categories', 'name')->ignore($this->editingCategoryId),
            ],
        ]);

        $slug = Str::slug($validated['categoryName']);

        if ($this->editingCategoryId) {
            $category = Category::query()->findOrFail($this->editingCategoryId);
            $category->update([
                'name' => $validated['categoryName'],
                'slug' => $slug,
            ]);
            Flux::toast(variant: 'success', text: 'Category updated.');
        } else {
            $max = (int) Category::query()->max('sort_order');
            $category = Category::query()->create([
                'name' => $validated['categoryName'],
                'slug' => $slug,
                'sort_order' => $max + 1,
                'is_active' => true,
            ]);
            $this->selectedCategoryId = $category->id;
            Flux::toast(variant: 'success', text: 'Category created.');
        }

        $this->resetCategoryForm();
    }

    public function editCategory(int $categoryId): void
    {
        $category = Category::query()->findOrFail($categoryId);
        $this->editingCategoryId = $category->id;
        $this->categoryName = $category->name;
        $this->selectedCategoryId = $category->id;
    }

    public function toggleCategory(int $categoryId): void
    {
        $category = Category::query()->findOrFail($categoryId);
        $category->update(['is_active' => ! $category->is_active]);
        Flux::toast(variant: 'success', text: $category->is_active ? 'Category activated.' : 'Category deactivated.');
    }

    public function deleteCategory(int $categoryId): void
    {
        $category = Category::query()->withCount('subcategories')->findOrFail($categoryId);

        if ($category->subcategories_count > 0) {
            Flux::toast(variant: 'danger', text: 'Remove or reassign sub-categories first.');

            return;
        }

        $category->delete();

        if ($this->selectedCategoryId === $categoryId) {
            $this->selectedCategoryId = Category::query()->orderBy('sort_order')->value('id');
        }

        $this->resetCategoryForm();
        Flux::toast(variant: 'success', text: 'Category deleted.');
    }

    public function selectCategory(int $categoryId): void
    {
        $this->selectedCategoryId = $categoryId;
        $this->resetSubcategoryForm();
    }

    public function saveSubcategory(): void
    {
        $validated = $this->validate([
            'selectedCategoryId' => ['required', 'exists:categories,id'],
            'subcategoryName' => [
                'required',
                'string',
                'max:120',
                Rule::unique('subcategories', 'name')
                    ->where(fn ($query) => $query->where('category_id', $this->selectedCategoryId))
                    ->ignore($this->editingSubcategoryId),
            ],
        ]);

        $slug = Str::slug($validated['subcategoryName']);

        if ($this->editingSubcategoryId) {
            Subcategory::query()->findOrFail($this->editingSubcategoryId)->update([
                'name' => $validated['subcategoryName'],
                'slug' => $slug,
            ]);
            Flux::toast(variant: 'success', text: 'Sub-category updated.');
        } else {
            $max = (int) Subcategory::query()->where('category_id', $this->selectedCategoryId)->max('sort_order');
            Subcategory::query()->create([
                'category_id' => $this->selectedCategoryId,
                'name' => $validated['subcategoryName'],
                'slug' => $slug,
                'sort_order' => $max + 1,
                'is_active' => true,
            ]);
            Flux::toast(variant: 'success', text: 'Sub-category created.');
        }

        $this->resetSubcategoryForm();
    }

    public function editSubcategory(int $subcategoryId): void
    {
        $subcategory = Subcategory::query()->findOrFail($subcategoryId);
        $this->editingSubcategoryId = $subcategory->id;
        $this->subcategoryName = $subcategory->name;
        $this->selectedCategoryId = $subcategory->category_id;
    }

    public function toggleSubcategory(int $subcategoryId): void
    {
        $subcategory = Subcategory::query()->findOrFail($subcategoryId);
        $subcategory->update(['is_active' => ! $subcategory->is_active]);
        Flux::toast(variant: 'success', text: $subcategory->is_active ? 'Sub-category activated.' : 'Sub-category deactivated.');
    }

    public function deleteSubcategory(int $subcategoryId): void
    {
        $subcategory = Subcategory::query()->withCount('products')->findOrFail($subcategoryId);

        if ($subcategory->products_count > 0) {
            Flux::toast(variant: 'danger', text: 'Move or delete products in this sub-category first.');

            return;
        }

        $subcategory->delete();
        $this->resetSubcategoryForm();
        Flux::toast(variant: 'success', text: 'Sub-category deleted.');
    }

    public function resetCategoryForm(): void
    {
        $this->editingCategoryId = null;
        $this->categoryName = '';
        $this->resetValidation(['categoryName']);
    }

    public function resetSubcategoryForm(): void
    {
        $this->editingSubcategoryId = null;
        $this->subcategoryName = '';
        $this->resetValidation(['subcategoryName']);
    }

    public function with(): array
    {
        $categories = Category::query()
            ->withCount('subcategories')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $selected = $this->selectedCategoryId
            ? Category::query()->with(['subcategories' => fn ($q) => $q->withCount('products')->orderBy('sort_order')->orderBy('name')])->find($this->selectedCategoryId)
            : null;

        return [
            'categories' => $categories,
            'selected' => $selected,
        ];
    }
}; ?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">Categories &amp; sub-categories</flux:heading>
        <flux:text class="mt-1">Catalogue hierarchy from UniqBid Product Catalogue (letter prefixes removed).</flux:text>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="space-y-4 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Main categories</h2>

            <form wire:submit="saveCategory" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <flux:field class="flex-1">
                    <flux:label>{{ $editingCategoryId ? 'Edit category' : 'New category' }}</flux:label>
                    <flux:input wire:model="categoryName" placeholder="e.g. Watches" />
                    <flux:error name="categoryName" />
                </flux:field>
                <div class="flex gap-2">
                    <flux:button type="submit">{{ $editingCategoryId ? 'Update' : 'Add' }}</flux:button>
                    @if ($editingCategoryId)
                        <flux:button type="button" variant="ghost" wire:click="resetCategoryForm">Cancel</flux:button>
                    @endif
                </div>
            </form>

            <div class="divide-y divide-zinc-200 overflow-hidden rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                @forelse ($categories as $category)
                    <div
                        wire:key="cat-{{ $category->id }}"
                        @class([
                            'flex items-center justify-between gap-3 px-3 py-2.5',
                            'bg-zinc-50 dark:bg-zinc-800' => $selectedCategoryId === $category->id,
                        ])
                    >
                        <button type="button" class="min-w-0 flex-1 text-left" wire:click="selectCategory({{ $category->id }})">
                            <div class="truncate font-medium">{{ $category->name }}</div>
                            <div class="text-xs text-zinc-500">
                                {{ $category->subcategories_count }} sub-categories
                                @unless ($category->is_active)
                                    · inactive
                                @endunless
                            </div>
                        </button>
                        <div class="flex shrink-0 gap-1">
                            <flux:button size="sm" variant="ghost" wire:click="editCategory({{ $category->id }})">Edit</flux:button>
                            <flux:button size="sm" variant="ghost" wire:click="toggleCategory({{ $category->id }})">
                                {{ $category->is_active ? 'Off' : 'On' }}
                            </flux:button>
                            <flux:button size="sm" variant="ghost" wire:click="deleteCategory({{ $category->id }})" wire:confirm="Delete this category?">Del</flux:button>
                        </div>
                    </div>
                @empty
                    <p class="px-3 py-4 text-sm text-zinc-500">No categories yet.</p>
                @endforelse
            </div>
        </section>

        <section class="space-y-4 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">
                Sub-categories
                @if ($selected)
                    <span class="font-normal normal-case text-zinc-400">: {{ $selected->name }}</span>
                @endif
            </h2>

            @if ($selected)
                <form wire:submit="saveSubcategory" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <flux:field class="flex-1">
                        <flux:label>{{ $editingSubcategoryId ? 'Edit sub-category' : 'New sub-category' }}</flux:label>
                        <flux:input wire:model="subcategoryName" placeholder="e.g. Men's watches" />
                        <flux:error name="subcategoryName" />
                    </flux:field>
                    <div class="flex gap-2">
                        <flux:button type="submit">{{ $editingSubcategoryId ? 'Update' : 'Add' }}</flux:button>
                        @if ($editingSubcategoryId)
                            <flux:button type="button" variant="ghost" wire:click="resetSubcategoryForm">Cancel</flux:button>
                        @endif
                    </div>
                </form>

                <div class="divide-y divide-zinc-200 overflow-hidden rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                    @forelse ($selected->subcategories as $subcategory)
                        <div class="flex items-center justify-between gap-3 px-3 py-2.5" wire:key="sub-{{ $subcategory->id }}">
                            <div class="min-w-0">
                                <div class="truncate font-medium">{{ $subcategory->name }}</div>
                                <div class="text-xs text-zinc-500">
                                    {{ $subcategory->products_count }} products
                                    @unless ($subcategory->is_active)
                                        · inactive
                                    @endunless
                                </div>
                            </div>
                            <div class="flex shrink-0 gap-1">
                                <flux:button size="sm" variant="ghost" wire:click="editSubcategory({{ $subcategory->id }})">Edit</flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="toggleSubcategory({{ $subcategory->id }})">
                                    {{ $subcategory->is_active ? 'Off' : 'On' }}
                                </flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="deleteSubcategory({{ $subcategory->id }})" wire:confirm="Delete this sub-category?">Del</flux:button>
                            </div>
                        </div>
                    @empty
                        <p class="px-3 py-4 text-sm text-zinc-500">No sub-categories in this category.</p>
                    @endforelse
                </div>
            @else
                <p class="text-sm text-zinc-500">Select a category to manage its sub-categories.</p>
            @endif
        </section>
    </div>
</div>
