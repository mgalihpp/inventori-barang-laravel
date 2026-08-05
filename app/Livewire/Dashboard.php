<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Livewire\Component;

class Dashboard extends Component
{
    public int $productCount = 0;
    public int $categoryCount = 0;
    public int $supplierCount = 0;
    public int $lowStockCount = 0;

    public function mount(): void
    {
        $this->productCount = Product::count();
        $this->categoryCount = Category::count();
        $this->supplierCount = Supplier::count();
        $this->lowStockCount = Product::whereColumn('stock', '<=', 'min_stock')->count();
    }

    public function render()
    {
        return view('pages.dashboard');
    }
}