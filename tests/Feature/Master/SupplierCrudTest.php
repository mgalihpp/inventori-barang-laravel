<?php

namespace Tests\Feature\Master;

use App\Livewire\Master\SupplierCreate;
use App\Livewire\Master\SupplierEdit;
use App\Livewire\Master\SupplierIndex;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SupplierCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_index_lists_suppliers(): void
    {
        Supplier::factory()->create(['name' => 'PT Maju']);

        $this->actingAs($this->admin())
            ->get(route('master.supplier'))
            ->assertOk()
            ->assertSee('PT Maju');
    }

    public function test_create_supplier_persists(): void
    {
        Livewire::actingAs($this->admin())
            ->test(SupplierCreate::class)
            ->set('name', 'PT Jaya')
            ->set('address', 'Jl. Merdeka 1')
            ->set('phone', '081234567')
            ->call('save')
            ->assertRedirect(route('master.supplier'));

        $this->assertDatabaseHas('suppliers', ['name' => 'PT Jaya']);
    }

    public function test_create_supplier_requires_name(): void
    {
        Livewire::actingAs($this->admin())
            ->test(SupplierCreate::class)
            ->set('name', '')
            ->call('save')
            ->assertHasErrors(['name']);
    }

    public function test_edit_supplier_updates(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Lama']);

        Livewire::actingAs($this->admin())
            ->test(SupplierEdit::class, ['supplier' => $supplier])
            ->set('name', 'Baru')
            ->call('save')
            ->assertRedirect(route('master.supplier'));

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'name' => 'Baru']);
    }

    public function test_delete_supplier_removes_it(): void
    {
        $supplier = Supplier::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(SupplierIndex::class)
            ->call('delete', $supplier->id)
            ->assertDispatched('refresh-suppliers');

        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    }

    public function test_delete_supplier_with_products_denied(): void
    {
        $supplier = Supplier::factory()->create();
        Product::factory()->create(['supplier_id' => $supplier->id]);

        Livewire::actingAs($this->admin())
            ->test(SupplierIndex::class)
            ->call('delete', $supplier->id)
            ->assertNotDispatched('refresh-suppliers');

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
    }
}