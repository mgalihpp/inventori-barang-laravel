<?php

namespace App\Livewire\Reports;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;

class CardsStockReport extends Component
{
    public ?int $product_id = null;

    public string $date_from = '';

    public string $date_to = '';

    public function mount(): void
    {
        $this->date_from = now()->startOfMonth()->format('Y-m-d');
        $this->date_to = now()->format('Y-m-d');
    }

    public function render(): View
    {
        $rows = collect();
        $opening = 0;
        $runningBalance = 0;

        if ($this->product_id) {
            $product = Product::query()->find($this->product_id);

            if ($product) {
                $deltaExpr = "CASE
                    WHEN t.type = 'stok_masuk' THEN ti.qty
                    WHEN t.type = 'stok_keluar' THEN -ti.qty
                    WHEN t.type = 'retur' THEN
                        CASE WHEN t.direction = 'in' THEN ti.qty ELSE -ti.qty END
                    ELSE 0
                END";

                $deltasAfter = DB::table('transaction_items as ti')
                    ->join('transactions as t', 'ti.transaction_id', '=', 't.id')
                    ->where('ti.product_id', $this->product_id)
                    ->whereDate('t.date', '>=', $this->date_from)
                    ->sum(DB::raw($deltaExpr));

                $opening = $product->stock - $deltasAfter;

                $rawRows = DB::table('transaction_items as ti')
                    ->join('transactions as t', 'ti.transaction_id', '=', 't.id')
                    ->select(
                        'ti.id',
                        't.date',
                        't.type',
                        't.direction',
                        't.notes',
                        'ti.qty',
                        DB::raw("({$deltaExpr}) as delta")
                    )
                    ->where('ti.product_id', $this->product_id)
                    ->whereDate('t.date', '>=', $this->date_from)
                    ->whereDate('t.date', '<=', $this->date_to)
                    ->orderBy('t.date')
                    ->orderBy('ti.id')
                    ->get();

                $runningBalance = $opening;
                $rows = $rawRows->map(function ($row) use (&$runningBalance) {
                    $runningBalance += $row->delta;
                    $row->balance = $runningBalance;

                    return $row;
                });
            }
        }

        return view('pages.reports.cards-stock', [
            'products' => Product::orderBy('name')->get(),
            'rows' => $rows,
            'opening' => $opening,
            'runningBalance' => $runningBalance,
        ]);
    }
}
