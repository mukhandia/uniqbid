<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <a href="{{ route('admin.products') }}" class="text-sm text-zinc-500 hover:underline" wire:navigate>← Products</a>
        <flux:heading size="xl" class="mt-2">Add product</flux:heading>
        <flux:text class="mt-1">Create a product, then assign it to Shop, Auction, or both from the products list.</flux:text>
    </div>

    <form wire:submit="save" class="space-y-4 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        @include('livewire.admin.product-form', [
            'submitLabel' => 'Create product',
            'categories' => $categories,
            'subcategories' => $subcategories,
        ])
    </form>
</div>
