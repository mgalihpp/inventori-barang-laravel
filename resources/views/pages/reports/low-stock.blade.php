<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div>
        <flux:heading size="xl">Stok Menipis</flux:heading>
        <flux:subheading>Daftar barang dengan stok di bawah minimum</flux:subheading>
    </div>

    <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari barang..." icon="magnifying-glass" class="max-w-sm" />

    <flux:table>
        <flux:table.columns>
            <flux:table.column>SKU</flux:table.column>
            <flux:table.column>Barang</flux:table.column>
            <flux:table.column>Kategori</flux:table.column>
            <flux:table.column class="text-right">Stok Saat Ini</flux:table.column>
            <flux:table.column class="text-right">Stok Minimum</flux:table.column>
            <flux:table.column>Status</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($products as $product)
                <flux:table.row :key="$product->id">
                    <flux:table.cell>{{ $product->sku }}</flux:table.cell>
                    <flux:table.cell variant="strong">{{ $product->name }}</flux:table.cell>
                    <flux:table.cell>{{ $product->category?->name ?? '-' }}</flux:table.cell>
                    <flux:table.cell class="text-right font-medium {{ $product->stock === 0 ? 'text-rose-600' : 'text-amber-600' }}">{{ $product->stock }}</flux:table.cell>
                    <flux:table.cell class="text-right">{{ $product->min_stock }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($product->stock === 0)
                            <flux:badge color="red" size="sm">Habis</flux:badge>
                        @else
                            <flux:badge color="amber" size="sm">Menipis</flux:badge>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" variant="empty">Semua stok dalam kondisi aman.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{ $products->links() }}
</div>