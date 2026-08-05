<?php

namespace Tests\Feature\Reports;

use App\Livewire\Reports\PeriodReport;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PeriodReportTest extends TestCase
{
    use RefreshDatabase;

    protected function staff(): User
    {
        return User::factory()->create(['role' => 'staff']);
    }

    public function test_index_loads(): void
    {
        $this->actingAs($this->staff())
            ->get(route('laporan.transaksi-periode'))
            ->assertOk();
    }

    public function test_shows_summary_cards(): void
    {
        $product = Product::factory()->create(['stock' => 100]);
        $supplier = Supplier::factory()->create();

        $user = $this->staff();

        Transaction::create([
            'type' => Transaction::TYPE_MASUK,
            'date' => now(),
            'user_id' => $user->id,
            'supplier_id' => $supplier->id,
        ])->items()->create([
            'product_id' => $product->id,
            'qty' => 10,
            'price' => 10000,
        ]);

        Transaction::create([
            'type' => Transaction::TYPE_KELUAR,
            'date' => now(),
            'user_id' => $user->id,
        ])->items()->create([
            'product_id' => $product->id,
            'qty' => 5,
            'price' => 10000,
        ]);

        Transaction::create([
            'type' => Transaction::TYPE_RETUR,
            'date' => now(),
            'user_id' => $user->id,
            'direction' => 'in',
        ])->items()->create([
            'product_id' => $product->id,
            'qty' => 2,
            'price' => 10000,
        ]);

        Livewire::actingAs($user)
            ->test(PeriodReport::class)
            ->set('date_from', now()->startOfMonth()->format('Y-m-d'))
            ->set('date_to', now()->format('Y-m-d'))
            ->assertSeeText('Stok Masuk')
            ->assertSeeText('Stok Keluar')
            ->assertSeeText('Retur');
    }

    public function test_filters_by_date_range(): void
    {
        $product = Product::factory()->create();
        $supplier = Supplier::factory()->create();
        $user = $this->staff();

        $tx1 = Transaction::create([
            'type' => Transaction::TYPE_MASUK,
            'date' => now()->subDays(30),
            'user_id' => $user->id,
            'supplier_id' => $supplier->id,
        ]);
        TransactionItem::create([
            'transaction_id' => $tx1->id,
            'product_id' => $product->id,
            'qty' => 10,
            'price' => 10000,
        ]);

        $tx2 = Transaction::create([
            'type' => Transaction::TYPE_MASUK,
            'date' => now()->subDays(5),
            'user_id' => $user->id,
            'supplier_id' => $supplier->id,
        ]);
        TransactionItem::create([
            'transaction_id' => $tx2->id,
            'product_id' => $product->id,
            'qty' => 20,
            'price' => 10000,
        ]);

        Livewire::actingAs($user)
            ->test(PeriodReport::class)
            ->set('date_from', now()->subDays(10)->format('Y-m-d'))
            ->set('date_to', now()->format('Y-m-d'))
            ->assertSeeText($tx2->date->format('d/m/Y'))
            ->assertDontSeeText($tx1->date->format('d/m/Y'));
    }

    public function test_search_filters_transactions(): void
    {
        $product1 = Product::factory()->create(['name' => 'Laptop ABC']);
        $product2 = Product::factory()->create(['name' => 'Mouse XYZ']);
        $supplier = Supplier::factory()->create();
        $user = $this->staff();
        $date = now()->subDays(5);

        $tx1 = Transaction::create([
            'type' => Transaction::TYPE_MASUK,
            'date' => $date,
            'user_id' => $user->id,
            'supplier_id' => $supplier->id,
        ]);
        TransactionItem::create([
            'transaction_id' => $tx1->id,
            'product_id' => $product1->id,
            'qty' => 5,
            'price' => 10000,
        ]);

        $tx2 = Transaction::create([
            'type' => Transaction::TYPE_MASUK,
            'date' => $date,
            'user_id' => $user->id,
            'supplier_id' => $supplier->id,
        ]);
        TransactionItem::create([
            'transaction_id' => $tx2->id,
            'product_id' => $product2->id,
            'qty' => 10,
            'price' => 10000,
        ]);

        Livewire::actingAs($user)
            ->test(PeriodReport::class)
            ->set('date_from', now()->subDays(10)->format('Y-m-d'))
            ->set('date_to', now()->format('Y-m-d'))
            ->set('search', 'Laptop')
            ->assertSeeText('Laptop ABC')
            ->assertDontSeeText('Mouse XYZ');
    }
}
