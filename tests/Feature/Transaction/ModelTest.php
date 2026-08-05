<?php

namespace Tests\Feature\Transaction;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_relationships(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $transaction = Transaction::create([
            'type' => Transaction::TYPE_MASUK,
            'date' => now(),
            'user_id' => $user->id,
            'supplier_id' => $supplier->id,
        ]);

        $transaction->items()->create([
            'product_id' => $product->id,
            'qty' => 5,
            'price' => 100000,
        ]);

        $this->assertDatabaseHas('transactions', ['id' => $transaction->id]);
        $this->assertDatabaseHas('transaction_items', [
            'transaction_id' => $transaction->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_stock_increases_on_stock_in(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $user = User::factory()->create();

        $transaction = Transaction::create([
            'type' => Transaction::TYPE_MASUK,
            'date' => now(),
            'user_id' => $user->id,
        ]);

        $transaction->items()->create([
            'product_id' => $product->id,
            'qty' => 5,
            'price' => 100000,
        ]);

        $product->refresh();
        $this->assertEquals(15, $product->stock);
    }

    public function test_stock_decreases_on_stock_out(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $user = User::factory()->create();

        $transaction = Transaction::create([
            'type' => Transaction::TYPE_KELUAR,
            'date' => now(),
            'user_id' => $user->id,
        ]);

        $transaction->items()->create([
            'product_id' => $product->id,
            'qty' => 5,
            'price' => 100000,
        ]);

        $product->refresh();
        $this->assertEquals(5, $product->stock);
    }
}
