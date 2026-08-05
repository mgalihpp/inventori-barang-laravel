<?php

namespace App\Livewire\Transaction;

use App\Models\Transaction;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class StockInIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public function render(): View
    {
        $transactions = Transaction::query()
            ->where('type', Transaction::TYPE_MASUK)
            ->with('items.product', 'user')
            ->when($this->search, fn ($q) => $q
                ->whereHas('items.product', fn ($p) => $p->where('name', 'like', "%{$this->search}%"))
                ->orWhere('notes', 'like', "%{$this->search}%"))
            ->latest('date')
            ->paginate(10);

        return view('pages.transaction.stock-in-index', ['transactions' => $transactions]);
    }
}
