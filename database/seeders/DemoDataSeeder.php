<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        fake()->seed(20260805);

        $admin = User::firstOrCreate(
            ['email' => 'admin@inventori.test'],
            ['name' => 'Admin Demo', 'password' => 'password', 'role' => User::ROLE_ADMIN, 'email_verified_at' => now()],
        );
        $staff = User::firstOrCreate(
            ['email' => 'staff@inventori.test'],
            ['name' => 'Staff Demo', 'password' => 'password', 'role' => User::ROLE_STAFF, 'email_verified_at' => now()],
        );

        $categories = collect([
            'Elektronik', 'ATK', 'Furniture', 'Kebersihan', 'Peralatan Gudang',
            'Komponen', 'Kemasan', 'Operasional', 'Safety', 'Konsumsi',
        ])->map(fn (string $name) => Category::firstOrCreate(['name' => $name]));

        $suppliers = Supplier::factory(12)->create();
        $products = Product::factory(100)->create(function () use ($categories, $suppliers): array {
            return [
                'category_id' => $categories->random()->id,
                'supplier_id' => $suppliers->random()->id,
                'stock' => 0,
                'min_stock' => fake()->numberBetween(5, 30),
            ];
        });

        $balances = $products->mapWithKeys(fn (Product $product): array => [$product->id => 0]);

        for ($index = 0; $index < 1200; $index++) {
            $product = $products->random();
            $type = $index < 350 ? Transaction::TYPE_MASUK : fake()->randomElement([
                Transaction::TYPE_MASUK,
                Transaction::TYPE_MASUK,
                Transaction::TYPE_KELUAR,
                Transaction::TYPE_RETUR,
            ]);
            $direction = null;

            if ($type === Transaction::TYPE_KELUAR || $type === Transaction::TYPE_RETUR) {
                $available = $products->filter(fn (Product $item): bool => $balances[$item->id] > 0);

                if ($available->isEmpty()) {
                    $type = Transaction::TYPE_MASUK;
                } else {
                    $product = $available->random();
                    $direction = $type === Transaction::TYPE_RETUR
                        ? fake()->randomElement(['in', 'out'])
                        : null;

                    if ($direction === 'in') {
                        $type = Transaction::TYPE_RETUR;
                    }
                }
            }

            $quantity = $type === Transaction::TYPE_MASUK || $direction === 'in'
                ? fake()->numberBetween(5, 50)
                : min($balances[$product->id], fake()->numberBetween(1, 20));

            $transaction = Transaction::create([
                'type' => $type,
                'date' => fake()->dateTimeBetween('-12 months', 'now')->format('Y-m-d'),
                'user_id' => fake()->randomElement([$admin->id, $staff->id]),
                'supplier_id' => $type === Transaction::TYPE_MASUK || $direction === 'out'
                    ? $product->supplier_id
                    : null,
                'direction' => $direction,
                'notes' => fake()->optional()->sentence(6),
            ]);

            $transaction->items()->create([
                'product_id' => $product->id,
                'qty' => $quantity,
                'price' => $product->price,
            ]);

            $delta = $type === Transaction::TYPE_MASUK || $direction === 'in'
                ? $quantity
                : -$quantity;
            $balances[$product->id] += $delta;
        }
    }
}
