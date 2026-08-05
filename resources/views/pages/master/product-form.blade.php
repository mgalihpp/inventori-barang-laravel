<div class="mx-auto max-w-3xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div>
        <flux:heading size="xl">{{ $product ? 'Edit Barang' : 'Tambah Barang' }}</flux:heading>
        <flux:subheading>Isi detail barang</flux:subheading>
    </div>

    <flux:card>
        <form wire:submit.prevent="save" class="space-y-6">
            <flux:input label="Nama" wire:model="name" :required="true" placeholder="cth. Laptop X" />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select label="Kategori" wire:model="category_id" placeholder="Pilih kategori (opsional)">
                    @foreach ($categories as $category)
                        <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select label="Supplier" wire:model="supplier_id" placeholder="Pilih supplier (opsional)">
                    @foreach ($suppliers as $supplier)
                        <flux:select.option :value="$supplier->id">{{ $supplier->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input label="Harga" wire:model="price" type="number" step="0.01" min="0" :required="true" />
                <flux:input label="Satuan" wire:model="unit" :required="true" placeholder="cth. pcs" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input label="Stok Minimum" wire:model="min_stock" type="number" min="0" />
                <flux:input label="Stok Awal" wire:model="stock" type="number" min="0" />
            </div>

            <div class="flex justify-end gap-3">
                <flux:button :href="route('master.product')" wire:navigate variant="ghost">Batal</flux:button>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:card>
</div>