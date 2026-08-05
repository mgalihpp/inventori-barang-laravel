<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Stok Keluar</flux:heading>
            <flux:subheading>Daftar transaksi stok keluar</flux:subheading>
        </div>
        <flux:button :href="route('transaksi.keluar.create')" wire:navigate icon="plus">Tambah</flux:button>
    </div>

    <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari barang..." icon="magnifying-glass" class="max-w-sm" />

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Tanggal</flux:table.column>
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
                    <flux:table.cell colspan="5" variant="empty">Belum ada transaksi stok keluar.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{ $transactions->links() }}
</div>
