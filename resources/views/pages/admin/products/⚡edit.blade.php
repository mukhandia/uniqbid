<?php

use App\Concerns\AdminProductForm;
use App\Models\Product;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.app')]
#[Title('Admin: Edit product')]
class extends Component {
    use AdminProductForm;

    public Product $product;

    public function mount(Product $product): void
    {
        $this->product = $product;
        $this->fillFromProduct($product);
    }

    public function save(): void
    {
        $product = $this->persistProduct($this->product);

        if ($product === null) {
            return;
        }

        $this->redirect(route('admin.products'), navigate: true);
    }

    public function with(): array
    {
        return [
            'categories' => $this->formCategories(),
            'subcategories' => $this->formSubcategories(),
        ];
    }
}; ?>

<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <a href="{{ route('admin.products') }}" class="text-sm text-zinc-500 hover:underline" wire:navigate>← Products</a>
        <flux:heading size="xl" class="mt-2">Edit product</flux:heading>
        <flux:text class="mt-1">{{ $product->name }}</flux:text>
    </div>

    <form wire:submit="save" class="space-y-4 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        @include('pages.admin.products._form', [
            'submitLabel' => 'Save changes',
            'categories' => $categories,
            'subcategories' => $subcategories,
        ])
    </form>
</div>
