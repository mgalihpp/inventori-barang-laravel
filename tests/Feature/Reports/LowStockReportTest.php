<?php

namespace Tests\Feature\Reports;

use App\Livewire\Reports\LowStockReport;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LowStockReportTest extends TestCase
{
    use RefreshDatabase;

    protected function staff(): User
    {
        return User::factory()->create(['role' => 'staff']);
    }

    public function test_index_loads(): void
    {
        $this->actingAs($this->staff())
            ->get(route('laporan.low-stock'))
            ->assertOk();
    }

    public function test_shows_products_below_min_stock(): void
    {
        Product::factory()->create(['name' => 'Normal Stock', 'stock' => 50, 'min_stock' => 10]);
        Product::factory()->create(['name' => 'Low Stock', 'stock' => 5, 'min_stock' => 10]);
        Product::factory()->create(['name' => 'Out of Stock', 'stock' => 0, 'min_stock' => 10]);

        Livewire::actingAs($this->staff())
            ->test(LowStockReport::class)
            ->assertSee('Low Stock')
            ->assertSee('Out of Stock')
            ->assertDontSee('Normal Stock');
    }

    public function test_shows_badges_for_status(): void
    {
        Product::factory()->create(['stock' => 0, 'min_stock' => 5]);
        Product::factory()->create(['stock' => 3, 'min_stock' => 10]);

        Livewire::actingAs($this->staff())
            ->test(LowStockReport::class)
            ->assertSee('Habis')
            ->assertSee('Menipis');
    }

    public function test_search_filters_products(): void
    {
        Product::factory()->create(['name' => 'Laptop ABC', 'stock' => 2, 'min_stock' => 10]);
        Product::factory()->create(['name' => 'Mouse XYZ', 'stock' => 3, 'min_stock' => 5]);

        Livewire::actingAs($this->staff())
            ->test(LowStockReport::class)
            ->set('search', 'Laptop')
            ->assertSee('Laptop ABC')
            ->assertDontSee('Mouse XYZ');
    }

    public function test_shows_empty_when_all_safe(): void
    {
        Product::factory()->create(['stock' => 50, 'min_stock' => 10]);

        Livewire::actingAs($this->staff())
            ->test(LowStockReport::class)
            ->assertSee('Semua stok dalam kondisi aman');
    }
}