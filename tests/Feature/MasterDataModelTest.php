<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_can_have_many_products(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $this->assertTrue($category->products->contains($product));
        $this->assertEquals($category->id, $product->category->id);
    }

    public function test_supplier_can_have_many_products(): void
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);

        $this->assertTrue($supplier->products->contains($product));
        $this->assertEquals($supplier->id, $product->supplier->id);
    }

    public function test_category_without_products_is_nullable(): void
    {
        $product = Product::factory()->create(['category_id' => null, 'supplier_id' => null]);

        $this->assertNull($product->category);
        $this->assertNull($product->supplier);
    }

    public function test_product_sku_is_auto_generated_on_create(): void
    {
        $category = Category::factory()->create(['name' => 'Peralatan']);

        $product = Product::factory()->create(['category_id' => $category->id]);

        $this->assertSame('PER-0001', $product->fresh()->sku);
    }

    public function test_product_without_category_gets_gen_sku(): void
    {
        $product = Product::factory()->create(['category_id' => null]);

        $this->assertStringStartsWith('GEN-', $product->fresh()->sku);
    }
}