<?php

namespace App\Models;

use App\Services\SkuGenerator;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $category_id
 * @property int|null $supplier_id
 * @property string $name
 * @property string $sku
 * @property string $price
 * @property string $unit
 * @property int $min_stock
 * @property int $stock
 */
#[Fillable(['category_id', 'supplier_id', 'name', 'price', 'unit', 'min_stock', 'stock'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            $product->sku = SkuGenerator::generate(
                $product->category,
                $product->category ? $product->category->products()->count() : null,
            );
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}