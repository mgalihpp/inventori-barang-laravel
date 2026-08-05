<?php

namespace Tests\Feature\Reports;

use App\Livewire\Reports\CardsStockReport;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CardsStockReportTest extends TestCase
{
    use RefreshDatabase;

    protected function staff(): User
    {
        return User::factory()->create(['role' => 'staff']);
    }

    public function test_index_loads(): void
    {
        $this->actingAs($this->staff())
            ->get(route('laporan.kartu-stok'))
            ->assertOk();
    }

    public function test_shows_placeholder_when_no_product_selected(): void
    {
        Livewire::actingAs($this->staff())
            ->test(CardsStockReport::class)
            ->assertSee('Pilih barang untuk melihat kartu stok');
    }

    public function test_shows_kartu_stok_with_transactions(): void
    {
        $product = Product::factory()->create(['stock' => 50, 'min_stock' => 10]);
        $supplier = Supplier::factory()->create();

        $tx1 = Transaction::create([
            'type' => Transaction::TYPE_MASUK,
            'date' => now()->subDays(10),
            'user_id' => $this->staff()->id,
            'supplier_id' => $supplier->id,
        ]);
        TransactionItem::create([
            'transaction_id' => $tx1->id,
            'product_id' => $product->id,
            'qty' => 30,
            'price' => 10000,
        ]);

        $tx2 = Transaction::create([
            'type' => Transaction::TYPE_KELUAR,
            'date' => now()->subDays(5),
            'user_id' => $this->staff()->id,
        ]);
        TransactionItem::create([
            'transaction_id' => $tx2->id,
            'product_id' => $product->id,
            'qty' => 20,
            'price' => 10000,
        ]);

        $product->refresh();
        $this->assertEquals(60, $product->stock);

        Livewire::actingAs($this->staff())
            ->test(CardsStockReport::class)
            ->set('product_id', $product->id)
            ->set('date_from', now()->subDays(15)->format('Y-m-d'))
            ->set('date_to', now()->format('Y-m-d'))
            ->assertSee('Saldo Awal')
            ->assertSee('Masuk')
            ->assertSee('Keluar')
            ->assertSee('Saldo Akhir');
    }
}