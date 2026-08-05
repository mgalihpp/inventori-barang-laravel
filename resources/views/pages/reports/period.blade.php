<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div>
        <flux:heading size="xl">Transaksi Periode</flux:heading>
        <flux:subheading>Ringkasan transaksi dalam rentang waktu</flux:subheading>
    </div>

    <flux:card>
        <div class="grid gap-4 sm:grid-cols-3">
            <flux:input label="Dari Tanggal" wire:model.live="date_from" type="date" />
            <flux:input label="Sampai Tanggal" wire:model.live="date_to" type="date" />
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari barang/catatan..." icon="magnifying-glass" class="self-end" />
        </div>
    </flux:card>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        @foreach ($cards as $card)
            <flux:card>
                <flux:heading size="sm">{{ $card['label'] }}</flux:heading>
                <div class="mt-1 text-2xl font-semibold">{{ number_format($card['count']) }}</div>
                <div class="text-sm text-zinc-500">{{ number_format($card['qty']) }} unit</div>
            </flux:card>
        @endforeach
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Tanggal</flux:table.column>
            <flux:table.column>Jenis</flux:table.column>
            <flux:table.column>Supplier</flux:table.column>
            <flux:table.column>Barang</flux:table.column>
            <flux:table.column>Total Qty</flux:table.column>
            <flux:table.column>Dicatat Oleh</flux:table.column>
            <flux:table.column>Catatan</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($transactions as $transaction)
                <flux:table.row :key="$transaction->id">
                    <flux:table.cell>{{ $transaction->date->format('d/m/Y') }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($transaction->type === 'stok_masuk')
                            <flux:badge color="emerald" size="sm">Masuk</flux:badge>
                        @elseif ($transaction->type === 'stok_keluar')
                            <flux:badge color="rose" size="sm">Keluar</flux:badge>
                        @else
                            <flux:badge color="amber" size="sm">Retur</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $transaction->supplier?->name ?? '-' }}</flux:table.cell>
                    <flux:table.cell>
                        @foreach ($transaction->items as $item)
                            <div>{{ $item->product?->name }} ({{ $item->qty }})</div>
                        @endforeach
                    </flux:table.cell>
                    <flux:table.cell>{{ $transaction->items->sum('qty') }}</flux:table.cell>
                    <flux:table.cell>{{ $transaction->user?->name ?? '-' }}</flux:table.cell>
                    <flux:table.cell>{{ $transaction->notes ?? '-' }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" variant="empty">Tidak ada transaksi dalam periode ini.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{ $transactions->links() }}
</div>