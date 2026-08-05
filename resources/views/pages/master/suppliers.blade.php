<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Supplier</flux:heading>
            <flux:subheading>Kelola supplier barang</flux:subheading>
        </div>
        <flux:button :href="route('master.supplier.create')" wire:navigate icon="plus">Tambah</flux:button>
    </div>

    <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari supplier..." icon="magnifying-glass" class="max-w-sm" />

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Nama</flux:table.column>
            <flux:table.column>Alamat</flux:table.column>
            <flux:table.column>Telepon</flux:table.column>
            <flux:table.column class="text-right">Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($suppliers as $supplier)
                <flux:table.row :key="$supplier->id">
                    <flux:table.cell variant="strong">{{ $supplier->name }}</flux:table.cell>
                    <flux:table.cell>{{ $supplier->address }}</flux:table.cell>
                    <flux:table.cell>{{ $supplier->phone }}</flux:table.cell>
                    <flux:table.cell class="text-right">
                        <div class="flex justify-end gap-1">
                            <flux:button :href="route('master.supplier.edit', $supplier)" wire:navigate variant="ghost" icon="pencil-square" size="sm" />
                            <flux:button wire:click.prevent="delete({{ $supplier->id }})" variant="ghost" size="sm" icon="trash" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4" variant="empty">Belum ada supplier.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{ $suppliers->links() }}
</div>