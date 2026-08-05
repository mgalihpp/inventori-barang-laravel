<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Barang</flux:heading>
            <flux:subheading>Kelola barang inventori</flux:subheading>
        </div>
        <flux:button :href="route('master.product.create')" wire:navigate icon="plus">Tambah</flux:button>
    </div>

    <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari barang..." icon="magnifying-glass" class="max-w-sm" />

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Nama</flux:table.column>
            <flux:table.column>SKU</flux:table.column>
            <flux:table.column>Kategori</flux:table.column>
            <flux:table.column>Stok</flux:table.column>
            <flux:table.column>Harga</flux:table.column>
            <flux:table.column class="text-right">Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($products as $product)
                <flux:table.row :key="$product->id">
                    <flux:table.cell variant="strong">{{ $product->name }}</flux:table.cell>
                    <flux:table.cell>{{ $product->sku }}</flux:table.cell>
                    <flux:table.cell>{{ $product->category?->name }}</flux:table.cell>
                    <flux:table.cell>{{ $product->stock }} {{ $product->unit }}</flux:table.cell>
                    <flux:table.cell>{{ number_format($product->price, 0, ',', '.') }}</flux:table.cell>
                    <flux:table.cell class="text-right">
                        <div class="flex justify-end gap-1">
                            <flux:button :href="route('master.product.edit', $product)" wire:navigate variant="ghost" icon="pencil-square" size="sm" />
                            <flux:button wire:click.prevent="delete({{ $product->id }})" variant="ghost" size="sm" icon="trash" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" variant="empty">Belum ada barang.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{ $products->links() }}
</div>