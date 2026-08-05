<?php

namespace App\Livewire\Master;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Livewire\Component;

class ProductCreate extends Component
{
    public ?Product $product = null;
    public string $name = '';
    public ?int $category_id = null;
    public ?int $supplier_id = null;
    public string $price = '0';
    public string $unit = '';
    public int $min_stock = 0;
    public int $stock = 0;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:20'],
            'min_stock' => ['integer', 'min:0'],
            'stock' => ['integer', 'min:0'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        Product::create($this->only([
            'name', 'category_id', 'supplier_id', 'price', 'unit', 'min_stock', 'stock',
        ]));

        $this->redirect(route('master.product'));
    }

    public function render()
    {
        return view('pages.master.product-form', [
            'categories' => Category::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
        ]);
    }
}