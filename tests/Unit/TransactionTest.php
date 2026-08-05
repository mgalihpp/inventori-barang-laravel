<?php

namespace Tests\Unit;

use App\Models\Transaction;
use App\Models\TransactionItem;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    public function test_delta_calculation_stock_in(): void
    {
        $transaction = new Transaction(['type' => Transaction::TYPE_MASUK]);
        $item = new TransactionItem(['qty' => 5]);
        $item->setRelation('transaction', $transaction);

        $this->assertEquals(5, $item->getDelta());
    }

    public function test_delta_calculation_stock_out(): void
    {
        $transaction = new Transaction(['type' => Transaction::TYPE_KELUAR]);
        $item = new TransactionItem(['qty' => 5]);
        $item->setRelation('transaction', $transaction);

        $this->assertEquals(-5, $item->getDelta());
    }

    public function test_delta_calculation_retur_in(): void
    {
        $transaction = new Transaction(['type' => Transaction::TYPE_RETUR, 'direction' => 'in']);
        $item = new TransactionItem(['qty' => 5]);
        $item->setRelation('transaction', $transaction);

        $this->assertEquals(5, $item->getDelta());
    }

    public function test_delta_calculation_retur_out(): void
    {
        $transaction = new Transaction(['type' => Transaction::TYPE_RETUR, 'direction' => 'out']);
        $item = new TransactionItem(['qty' => 5]);
        $item->setRelation('transaction', $transaction);

        $this->assertEquals(-5, $item->getDelta());
    }
}
