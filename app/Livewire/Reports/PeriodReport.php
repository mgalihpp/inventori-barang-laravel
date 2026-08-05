<?php

namespace App\Livewire\Reports;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class PeriodReport extends Component
{
    use WithPagination;

    public string $date_from = '';
    public string $date_to = '';
    public string $search = '';

    public function mount(): void
    {
        $this->date_from = now()->startOfMonth()->format('Y-m-d');
        $this->date_to = now()->format('Y-m-d');
    }

    public function render(): View
    {
        $transactions = Transaction::query()
            ->with('items.product', 'user', 'supplier')
            ->whereBetween('date', [$this->date_from, $this->date_to])
            ->when($this->search, fn ($q) => $q->where(function ($query) {
                $query->whereHas('items.product', fn ($p) => $p->where('name', 'like', "%{$this->search}%"))
                    ->orWhere('notes', 'like', "%{$this->search}%");
            }))
            ->orderByDesc('date')
            ->paginate(10);

        $summary = Transaction::query()
            ->leftJoin('transaction_items', 'transactions.id', '=', 'transaction_items.transaction_id')
            ->whereBetween('transactions.date', [$this->date_from, $this->date_to])
            ->select(
                'transactions.type',
                DB::raw('count(DISTINCT transactions.id) as total_transactions'),
                DB::raw('coalesce(sum(transaction_items.qty), 0) as total_qty')
            )
            ->groupBy('transactions.type')
            ->get()
            ->keyBy('type');

        $cards = [
            'masuk' => ['label' => 'Stok Masuk', 'count' => (int) ($summary['stok_masuk']->total_transactions ?? 0), 'qty' => (int) ($summary['stok_masuk']->total_qty ?? 0)],
            'keluar' => ['label' => 'Stok Keluar', 'count' => (int) ($summary['stok_keluar']->total_transactions ?? 0), 'qty' => (int) ($summary['stok_keluar']->total_qty ?? 0)],
            'retur' => ['label' => 'Retur', 'count' => (int) ($summary['retur']->total_transactions ?? 0), 'qty' => (int) ($summary['retur']->total_qty ?? 0)],
        ];

        return view('pages.reports.period', [
            'transactions' => $transactions,
            'cards' => $cards,
        ]);
    }
}
