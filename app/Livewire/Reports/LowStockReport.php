<?php

namespace App\Livewire\Reports;

use App\Models\Product;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class LowStockReport extends Component
{
    use WithPagination;

    public string $search = '';

    public function render(): View
    {
        $products = Product::query()
            ->with('category')
            ->whereColumn('stock', '<=', 'min_stock')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderByRaw('stock - min_stock')
            ->paginate(10);

        return view('pages.reports.low-stock', ['products' => $products]);
    }
}
