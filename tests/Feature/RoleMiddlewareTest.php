<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/admin-only', fn () => 'ok')->middleware(['auth', 'ensure-role:admin']);
    }

    public function test_admin_can_access_admin_route(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/admin-only')
            ->assertOk();
    }

    public function test_staff_cannot_access_admin_route(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->get('/admin-only')
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin-only')->assertRedirect(route('login'));
    }
}
