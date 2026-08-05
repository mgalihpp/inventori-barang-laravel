<?php

namespace Tests\Feature\Transaction;

use App\Livewire\Transaction\ReturCreate;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReturTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function staff(): User
    {
        return User::factory()->create(['role' => 'staff']);
    }

    public function test_index_lists_transactions(): void
    {
        $this->actingAs($this->staff())
            ->get(route('transaksi.retur'))
            ->assertOk();
    }

    public function test_retur_in_increases_stock(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $supplier = Supplier::factory()->create();

        Livewire::actingAs($this->staff())
            ->test(ReturCreate::class)
            ->set('date', now()->format('Y-m-d'))
            ->set('direction', 'in')
            ->set('supplier_id', $supplier->id)
            ->set('items', [[
                'product_id' => $product->id,
                'qty' => 5,
                'price' => 100000,
            ]])
            ->call('save')
            ->assertRedirect(route('transaksi.retur'));

        $product->refresh();
        $this->assertEquals(15, $product->stock);
    }

    public function test_retur_out_decreases_stock(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $supplier = Supplier::factory()->create();

        Livewire::actingAs($this->staff())
            ->test(ReturCreate::class)
            ->set('date', now()->format('Y-m-d'))
            ->set('direction', 'out')
            ->set('supplier_id', $supplier->id)
            ->set('items', [[
                'product_id' => $product->id,
                'qty' => 5,
                'price' => 100000,
            ]])
            ->call('save')
            ->assertRedirect(route('transaksi.retur'));

        $product->refresh();
        $this->assertEquals(5, $product->stock);
    }

    public function test_retur_out_fails_when_stock_insufficient(): void
    {
        $product = Product::factory()->create(['stock' => 3]);
        $supplier = Supplier::factory()->create();

        Livewire::actingAs($this->staff())
            ->test(ReturCreate::class)
            ->set('date', now()->format('Y-m-d'))
            ->set('direction', 'out')
            ->set('supplier_id', $supplier->id)
            ->set('items', [[
                'product_id' => $product->id,
                'qty' => 5,
                'price' => 100000,
            ]])
            ->call('save')
            ->assertHasErrors(['items.0.qty']);
    }
}
