<?php

namespace App\Listeners;

use App\Events\TransactionCreated;
use App\Models\Product;

class CheckLowStock
{
    public function handle(TransactionCreated $event): void
    {
        $lowStockProducts = Product::query()
            ->whereColumn('stock', '<=', 'min_stock')
            ->where('stock', '>', 0)
            ->get();

        foreach ($lowStockProducts as $product) {
            session()->push('low_stock_alerts', [
                'product' => $product->name,
                'stock' => $product->stock,
                'min' => $product->min_stock,
            ]);
        }
    }
}
