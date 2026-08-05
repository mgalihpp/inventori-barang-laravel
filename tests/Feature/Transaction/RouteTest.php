<?php

namespace Tests\Feature\Transaction;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteTest extends TestCase
{
    use RefreshDatabase;

    protected function staff(): User
    {
        return User::factory()->create(['role' => 'staff']);
    }

    public function test_stock_in_index_accessible_by_staff(): void
    {
        $this->actingAs($this->staff())
            ->get(route('transaksi.masuk'))
            ->assertOk();
    }

    public function test_stock_out_index_accessible_by_staff(): void
    {
        $this->actingAs($this->staff())
            ->get(route('transaksi.keluar'))
            ->assertOk();
    }

    public function test_retur_index_accessible_by_staff(): void
    {
        $this->actingAs($this->staff())
            ->get(route('transaksi.retur'))
            ->assertOk();
    }

    public function test_transaction_routes_require_authentication(): void
    {
        $this->get(route('transaksi.masuk'))
            ->assertRedirect(route('login'));
    }
}
