<?php

namespace Tests\Feature\Master;

use App\Livewire\Master\CategoryCreate;
use App\Livewire\Master\CategoryEdit;
use App\Livewire\Master\CategoryIndex;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_index_lists_categories(): void
    {
        Category::factory()->create(['name' => 'Elektronik']);
        Category::factory()->create(['name' => 'Furniture']);
        Category::factory()->create(['name' => 'Alat Tulis']);

        $this->actingAs($this->admin())
            ->get(route('master.kategori'))
            ->assertOk()
            ->assertSee('Elektronik')
            ->assertSee('Furniture');
    }

    public function test_index_searches_categories(): void
    {
        Category::factory()->create(['name' => 'Elektronik']);
        Category::factory()->create(['name' => 'Furniture']);

        Livewire::actingAs($this->admin())
            ->test(CategoryIndex::class)
            ->set('search', 'Elektro')
            ->assertSee('Elektronik')
            ->assertDontSee('Furniture');
    }

    public function test_staff_cannot_access_category_routes(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('master.kategori'))
            ->assertForbidden();
    }

    public function test_create_category_persists(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CategoryCreate::class)
            ->set('name', 'Elektronik')
            ->set('description', 'Barang elektronik')
            ->call('save')
            ->assertRedirect(route('master.kategori'));

        $this->assertDatabaseHas('categories', ['name' => 'Elektronik']);
    }

    public function test_create_category_requires_unique_name(): void
    {
        Category::factory()->create(['name' => 'Elektronik']);

        Livewire::actingAs($this->admin())
            ->test(CategoryCreate::class)
            ->set('name', 'Elektronik')
            ->call('save')
            ->assertHasErrors(['name']);
    }

    public function test_edit_category_updates(): void
    {
        $category = Category::factory()->create(['name' => 'Lama']);

        Livewire::actingAs($this->admin())
            ->test(CategoryEdit::class, ['category' => $category])
            ->set('name', 'Baru')
            ->call('save')
            ->assertRedirect(route('master.kategori'));

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Baru']);
    }

    public function test_edit_category_allows_keeping_same_name(): void
    {
        $category = Category::factory()->create(['name' => 'Elektronik']);

        Livewire::actingAs($this->admin())
            ->test(CategoryEdit::class, ['category' => $category])
            ->set('name', 'Elektronik')
            ->call('save')
            ->assertSuccessful();
    }

    public function test_delete_category_removes_it(): void
    {
        $category = Category::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(CategoryIndex::class)
            ->call('delete', $category->id)
            ->assertDispatched('refresh-categories');

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_delete_category_with_products_denied(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);

        Livewire::actingAs($this->admin())
            ->test(CategoryIndex::class)
            ->call('delete', $category->id)
            ->assertNotDispatched('refresh-categories');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }
}