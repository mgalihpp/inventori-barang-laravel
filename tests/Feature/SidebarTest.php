<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_shows_inventory_menus(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Transaksi')
            ->assertSee('Laporan');
    }

    public function test_admin_sees_master_data_submenu(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Master Data');
    }

    public function test_staff_does_not_see_master_data_submenu(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Master Data');
    }
}