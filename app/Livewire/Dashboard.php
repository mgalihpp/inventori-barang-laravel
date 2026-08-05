<?php

namespace App\Livewire;

use Livewire\Component;

class Dashboard extends Component
{
    public int $productCount = 0;
    public int $categoryCount = 0;
    public int $supplierCount = 0;
    public int $lowStockCount = 0;

    public function render()
    {
        return view('pages.dashboard');
    }
}
