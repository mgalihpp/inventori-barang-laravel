<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_role_attribute(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'admin',
        ]);
    }

    public function test_user_is_admin_helper(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->isStaff());
        $this->assertTrue($staff->isStaff());
        $this->assertFalse($staff->isAdmin());
    }
}
