<?php

namespace App\Livewire\Master;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Livewire\Component;

class ProductEdit extends Component
{
    public Product $product;
    public string $name = '';
    public ?int $category_id = null;
    public ?int $supplier_id = null;
    public string $price = '0';
    public string $unit = '';
    public int $min_stock = 0;
    public int $stock = 0;

    public function mount(): void
    {
        $this->name = $this->product->name;
        $this->category_id = $this->product->category_id;
        $this->supplier_id = $this->product->supplier_id;
        $this->price = (string) $this->product->price;
        $this->unit = $this->product->unit;
        $this->min_stock = $this->product->min_stock;
        $this->stock = $this->product->stock;
    }

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

        $this->product->update($this->only([
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