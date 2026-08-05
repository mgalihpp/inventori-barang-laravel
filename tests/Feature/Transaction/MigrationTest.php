<?php

namespace Tests\Feature\Transaction;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_transactions_table_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('transactions'));
        $this->assertTrue(Schema::hasColumn('transactions', 'type'));
        $this->assertTrue(Schema::hasColumn('transactions', 'date'));
        $this->assertTrue(Schema::hasColumn('transactions', 'notes'));
        $this->assertTrue(Schema::hasColumn('transactions', 'user_id'));
        $this->assertTrue(Schema::hasColumn('transactions', 'direction'));
        $this->assertTrue(Schema::hasColumn('transactions', 'supplier_id'));
    }

    public function test_transaction_items_table_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('transaction_items'));
        $this->assertTrue(Schema::hasColumn('transaction_items', 'transaction_id'));
        $this->assertTrue(Schema::hasColumn('transaction_items', 'product_id'));
        $this->assertTrue(Schema::hasColumn('transaction_items', 'qty'));
        $this->assertTrue(Schema::hasColumn('transaction_items', 'price'));
    }
}
