<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_dashboard_counts_real_data(): void
    {
        Category::factory()->count(3)->create();
        Supplier::factory()->count(2)->create();
        Product::factory()->count(5)->create(['stock' => 50, 'min_stock' => 1]);
        Product::factory()->count(2)->create(['stock' => 0, 'min_stock' => 5]);

        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertOk()
            ->assertSet('productCount', 7)
            ->assertSet('categoryCount', 3)
            ->assertSet('supplierCount', 2)
            ->assertSet('lowStockCount', 2);
    }
}