<?php

namespace App\Livewire\Master;

use App\Models\Supplier;
use Livewire\Component;

class SupplierCreate extends Component
{
    public ?Supplier $supplier = null;
    public string $name = '';
    public ?string $address = null;
    public ?string $phone = null;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        Supplier::create($this->only(['name', 'address', 'phone']));

        $this->redirect(route('master.supplier'));
    }

    public function render()
    {
        return view('pages.master.supplier-form');
    }
}