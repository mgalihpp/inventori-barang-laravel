<?php

namespace App\Models;

use Database\Factories\TransactionItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $transaction_id
 * @property int $product_id
 * @property int $qty
 * @property string $price
 */
#[Fillable(['transaction_id', 'product_id', 'qty', 'price'])]
class TransactionItem extends Model
{
    /** @use HasFactory<TransactionItemFactory> */
    use HasFactory;

    /** @return BelongsTo<Transaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getDelta(): int
    {
        $type = $this->transaction?->type;
        $direction = $this->transaction?->direction;

        if ($type === Transaction::TYPE_MASUK) {
            return $this->qty;
        }

        if ($type === Transaction::TYPE_KELUAR) {
            return -$this->qty;
        }

        return $direction === 'in' ? $this->qty : -$this->qty;
    }

    protected static function booted(): void
    {
        static::created(function (TransactionItem $item) {
            $delta = $item->getDelta();

            if ($delta > 0) {
                $item->product()->increment('stock', $delta);
            } else {
                $item->product()->decrement('stock', abs($delta));
            }
        });
    }
}
