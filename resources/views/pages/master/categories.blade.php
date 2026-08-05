<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Kategori</flux:heading>
            <flux:subheading>Kelola kategori barang</flux:subheading>
        </div>
        <flux:button :href="route('master.kategori.create')" wire:navigate icon="plus">Tambah</flux:button>
    </div>

    <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari kategori..." icon="magnifying-glass" class="max-w-sm" />

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Nama</flux:table.column>
            <flux:table.column>Deskripsi</flux:table.column>
            <flux:table.column class="text-right">Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($categories as $category)
                <flux:table.row :key="$category->id">
                    <flux:table.cell variant="strong">{{ $category->name }}</flux:table.cell>
                    <flux:table.cell>{{ $category->description }}</flux:table.cell>
                    <flux:table.cell class="text-right">
                        <div class="flex justify-end gap-1">
                            <flux:button :href="route('master.kategori.edit', $category)" wire:navigate variant="ghost" icon="pencil-square" size="sm" />
                            <flux:button wire:click.prevent="delete({{ $category->id }})" variant="ghost" size="sm" icon="trash" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="3" variant="empty">Belum ada kategori.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{ $categories->links() }}
</div>