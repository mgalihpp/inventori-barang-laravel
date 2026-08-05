<?php

namespace App\Livewire\Transaction;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;

/**
 * @property-read array<int, array{product_id: int|null, qty: int, price: string}> $items
 */
class StockInCreate extends Component
{
    public string $date = '';

    public ?int $supplier_id = null;

    public ?string $notes = null;

    /** @var array<int, array{product_id: int|null, qty: int, price: string}> */
    public array $items = [];

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
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
            'supplier_id.required' => 'Supplier wajib dipilih untuk stok masuk',
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

        DB::transaction(function () {
            $transaction = Transaction::create([
                'type' => Transaction::TYPE_MASUK,
                'date' => $this->date,
                'user_id' => auth()->id(),
                'supplier_id' => $this->supplier_id,
                'notes' => $this->notes,
            ]);

            foreach ($this->items as $item) {
                $transaction->items()->create($item);
            }
        });

        $this->redirect(route('transaksi.masuk'));
    }

    public function render(): View
    {
        return view('pages.transaction.stock-in-form', [
            'suppliers' => Supplier::orderBy('name')->get(),
            'products' => Product::orderBy('name')->get(),
        ]);
    }
}
