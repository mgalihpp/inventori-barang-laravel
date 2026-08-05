<?php

namespace App\Livewire\Master;

use App\Models\Product;
use Livewire\Component;
use Livewire\WithPagination;

class ProductIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public function delete(int $productId): void
    {
        Product::findOrFail($productId)->delete();
        $this->dispatch('refresh-products');
    }

    public function render()
    {
        $products = Product::query()
            ->with(['category', 'supplier'])
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(10);

        return view('pages.master.products', ['products' => $products]);
    }
}