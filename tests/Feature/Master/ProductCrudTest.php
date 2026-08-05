<?php

namespace Tests\Feature\Master;

use App\Livewire\Master\ProductCreate;
use App\Livewire\Master\ProductEdit;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_index_lists_products(): void
    {
        Product::factory()->create(['name' => 'Laptop X']);

        $this->actingAs($this->admin())
            ->get(route('master.product'))
            ->assertOk()
            ->assertSee('Laptop X');
    }

    public function test_create_product_generates_sku_and_persists(): void
    {
        $category = Category::factory()->create(['name' => 'Peralatan']);
        $supplier = Supplier::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ProductCreate::class)
            ->set('name', 'Laptop X')
            ->set('category_id', $category->id)
            ->set('supplier_id', $supplier->id)
            ->set('price', 500000)
            ->set('unit', 'pcs')
            ->set('min_stock', 5)
            ->set('stock', 10)
            ->call('save')
            ->assertRedirect(route('master.product'));

        $this->assertDatabaseHas('products', [
            'name' => 'Laptop X',
            'category_id' => $category->id,
            'sku' => 'PER-0001',
        ]);
    }

    public function test_create_product_requires_valid_price_and_unit(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ProductCreate::class)
            ->set('name', 'Laptop X')
            ->set('price', '-1')
            ->set('unit', '')
            ->call('save')
            ->assertHasErrors(['price', 'unit']);
    }

    public function test_edit_product_updates(): void
    {
        $category = Category::factory()->create(['name' => 'Furniture']);
        $product = Product::factory()->create(['category_id' => $category->id]);

        Livewire::actingAs($this->admin())
            ->test(ProductEdit::class, ['product' => $product])
            ->set('name', 'Meja Baru')
            ->set('unit', 'pcs')
            ->call('save')
            ->assertRedirect(route('master.product'));

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Meja Baru']);
    }

    public function test_delete_product_removes_it(): void
    {
        $product = Product::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Master\ProductIndex::class)
            ->call('delete', $product->id)
            ->assertDispatched('refresh-products');

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }
}