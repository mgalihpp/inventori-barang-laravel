<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div>
        <flux:heading size="xl">Kartu Stok</flux:heading>
        <flux:subheading>Riwayat transaksi dan saldo per barang</flux:subheading>
    </div>

    <flux:card>
        <div class="grid gap-4 sm:grid-cols-3">
            <flux:select label="Barang" wire:model.live="product_id" placeholder="Pilih barang">
                @foreach ($products as $product)
                    <flux:select.option :value="$product->id">{{ $product->name }} ({{ $product->sku }})</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input label="Dari Tanggal" wire:model.live="date_from" type="date" />
            <flux:input label="Sampai Tanggal" wire:model.live="date_to" type="date" />
        </div>
    </flux:card>

    @if ($product_id)
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Tanggal</flux:table.column>
                <flux:table.column>Jenis</flux:table.column>
                <flux:table.column>Catatan</flux:table.column>
                <flux:table.column class="text-right">Qty</flux:table.column>
                <flux:table.column class="text-right">Delta</flux:table.column>
                <flux:table.column class="text-right">Saldo</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                <flux:table.row variant="strong">
                    <flux:table.cell colspan="5">Saldo Awal</flux:table.cell>
                    <flux:table.cell class="text-right">{{ number_format($opening) }}</flux:table.cell>
                </flux:table.row>

                @forelse ($rows as $row)
                    <flux:table.row :key="$row->id">
                        <flux:table.cell>{{ \Carbon\Carbon::parse($row->date)->format('d/m/Y') }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($row->type === 'stok_masuk')
                                <flux:badge color="emerald" size="sm">Masuk</flux:badge>
                            @elseif ($row->type === 'stok_keluar')
                                <flux:badge color="rose" size="sm">Keluar</flux:badge>
                            @else
                                <flux:badge color="amber" size="sm">Retur {{ $row->direction === 'in' ? 'Masuk' : 'Keluar' }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $row->notes ?? '-' }}</flux:table.cell>
                        <flux:table.cell class="text-right">{{ $row->qty }}</flux:table.cell>
                        <flux:table.cell class="text-right {{ $row->delta > 0 ? 'text-emerald-600' : ($row->delta < 0 ? 'text-rose-600' : '') }}">
                            {{ $row->delta > 0 ? '+' : '' }}{{ $row->delta }}
                        </flux:table.cell>
                        <flux:table.cell class="text-right font-medium">{{ number_format($row->balance) }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" variant="empty">Tidak ada transaksi dalam periode ini.</flux:table.cell>
                    </flux:table.row>
                @endforelse

                <flux:table.row variant="strong">
                    <flux:table.cell colspan="5">Saldo Akhir</flux:table.cell>
                    <flux:table.cell class="text-right">{{ number_format($runningBalance) }}</flux:table.cell>
                </flux:table.row>
            </flux:table.rows>
        </flux:table>
    @else
        <flux:card>
            <div class="py-8 text-center text-sm text-zinc-500">Pilih barang untuk melihat kartu stok.</div>
        </flux:card>
    @endif
</div>