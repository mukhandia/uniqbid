<?php

namespace App\Livewire\Admin;

use App\Concerns\AdminProductForm;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Admin: Edit product')]
class ProductEdit extends Component
{
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

    public function render(): View
    {
        return view('livewire.admin.product-edit', [
            'categories' => $this->formCategories(),
            'subcategories' => $this->formSubcategories(),
        ]);
    }
}
