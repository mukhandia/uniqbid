<?php

namespace App\Livewire\Admin;

use App\Concerns\AdminProductForm;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Admin: Add product')]
class ProductCreate extends Component
{
    use AdminProductForm;

    public function save(): void
    {
        $product = $this->persistProduct();

        if ($product === null) {
            return;
        }

        $this->redirect(route('admin.products'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.admin.product-create', [
            'categories' => $this->formCategories(),
            'subcategories' => $this->formSubcategories(),
        ]);
    }
}
