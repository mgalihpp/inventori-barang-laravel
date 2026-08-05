<?php

namespace Tests\Feature\Transaction;

use App\Livewire\Transaction\StockOutCreate;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StockOutTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function staff(): User
    {
        return User::factory()->create(['role' => 'staff']);
    }

    public function test_index_lists_transactions(): void
    {
        $this->actingAs($this->staff())
            ->get(route('transaksi.keluar'))
            ->assertOk();
    }

    public function test_create_transaction_decreases_stock(): void
    {
        $product = Product::factory()->create(['stock' => 10]);

        Livewire::actingAs($this->staff())
            ->test(StockOutCreate::class)
            ->set('date', now()->format('Y-m-d'))
            ->set('items', [[
                'product_id' => $product->id,
                'qty' => 5,
                'price' => 100000,
            ]])
            ->call('save')
            ->assertRedirect(route('transaksi.keluar'));

        $product->refresh();
        $this->assertEquals(5, $product->stock);
    }

    public function test_fails_when_stock_insufficient(): void
    {
        $product = Product::factory()->create(['stock' => 3]);

        Livewire::actingAs($this->staff())
            ->test(StockOutCreate::class)
            ->set('date', now()->format('Y-m-d'))
            ->set('items', [[
                'product_id' => $product->id,
                'qty' => 5,
                'price' => 100000,
            ]])
            ->call('save')
            ->assertHasErrors(['items.0.qty']);

        $product->refresh();
        $this->assertEquals(3, $product->stock);
    }
}
