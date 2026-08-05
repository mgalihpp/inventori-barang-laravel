<?php

namespace Tests\Feature\Transaction;

use App\Livewire\Transaction\StockInCreate;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StockInTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function staff(): User
    {
        return User::factory()->create(['role' => 'staff']);
    }

    public function test_index_lists_transactions(): void
    {
        $this->actingAs($this->staff())
            ->get(route('transaksi.masuk'))
            ->assertOk();
    }

    public function test_create_transaction_increases_stock(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $supplier = Supplier::factory()->create();

        Livewire::actingAs($this->staff())
            ->test(StockInCreate::class)
            ->set('date', now()->format('Y-m-d'))
            ->set('supplier_id', $supplier->id)
            ->set('items', [[
                'product_id' => $product->id,
                'qty' => 5,
                'price' => 100000,
            ]])
            ->call('save')
            ->assertRedirect(route('transaksi.masuk'));

        $product->refresh();
        $this->assertEquals(15, $product->stock);
    }

    public function test_validation_fails_without_supplier(): void
    {
        Livewire::actingAs($this->staff())
            ->test(StockInCreate::class)
            ->set('date', now()->format('Y-m-d'))
            ->set('items', [])
            ->call('save')
            ->assertHasErrors(['supplier_id', 'items']);
    }
}
