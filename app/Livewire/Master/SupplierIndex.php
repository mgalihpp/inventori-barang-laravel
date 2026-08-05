<?php

namespace App\Livewire\Master;

use App\Models\Supplier;
use Livewire\Component;
use Livewire\WithPagination;

class SupplierIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public function delete(int $supplierId): void
    {
        $supplier = Supplier::findOrFail($supplierId);

        if ($supplier->products()->exists()) {
            $this->dispatch('supplier-delete-error');
            return;
        }

        $supplier->delete();
        $this->dispatch('refresh-suppliers');
    }

    public function render()
    {
        $suppliers = Supplier::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(10);

        return view('pages.master.suppliers', ['suppliers' => $suppliers]);
    }
}