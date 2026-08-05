<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => Transaction::TYPE_MASUK,
            'date' => now(),
            'notes' => null,
            'user_id' => User::factory(),
            'direction' => null,
            'supplier_id' => null,
        ];
    }
}
