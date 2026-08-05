<?php

namespace App\Livewire\Transaction;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;

/**
 * @property-read array<int, array{product_id: int|null, qty: int, price: string}> $items
 */
class ReturCreate extends Component
{
    public string $date = '';

    public string $direction = 'in';

    public ?int $supplier_id = null;

    public ?string $notes = null;

    /** @var array<int, array{product_id: int|null, qty: int, price: string}> */
    public array $items = [];

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'direction' => ['required', 'in:in,out'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'items.required' => 'Minimal harus ada 1 barang',
        ];
    }

    public function mount(): void
    {
        $this->date = now()->format('Y-m-d');
        $this->addItem();
    }

    public function addItem(): void
    {
        $this->items[] = ['product_id' => null, 'qty' => 1, 'price' => ''];
    }

    public function removeItem(int $index): void
    {
        if (count($this->items) > 1) {
            unset($this->items[$index]);
            $this->items = array_values($this->items);
        }
    }

    public function updatedItemsProductId(int $index): void
    {
        $productId = $this->items[$index]['product_id'] ?? null;

        if (! $productId) {
            return;
        }

        $product = Product::query()->find($productId);

        if ($product) {
            $this->items[$index]['price'] = (string) $product->price;
        }
    }

    public function save(): void
    {
        $this->validate();

        if ($this->validateStock()) {
            return;
        }

        DB::transaction(function () {
            $transaction = Transaction::create([
                'type' => Transaction::TYPE_RETUR,
                'date' => $this->date,
                'user_id' => Auth::id(),
                'direction' => $this->direction,
                'supplier_id' => $this->supplier_id,
                'notes' => $this->notes,
            ]);

            foreach ($this->items as $item) {
                $transaction->items()->create($item);
            }
        });

        $this->redirect(route('transaksi.retur'));
    }

    protected function validateStock(): bool
    {
        if ($this->direction !== 'out') {
            return false;
        }

        $hasError = false;

        foreach ($this->items as $index => $item) {
            $product = $item['product_id']
                ? Product::query()->find($item['product_id'])
                : null;

            if ($product && $item['qty'] > $product->stock) {
                $this->addError("items.{$index}.qty", "Stok tidak cukup. Stok saat ini: {$product->stock}");
                $hasError = true;
            }
        }

        return $hasError;
    }

    public function render(): View
    {
        return view('pages.transaction.retur-form', [
            'suppliers' => Supplier::orderBy('name')->get(),
            'products' => Product::orderBy('name')->get(),
        ]);
    }
}
