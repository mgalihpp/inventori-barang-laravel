<div class="mx-auto max-w-3xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div>
        <flux:heading size="xl">{{ $category ? 'Edit Kategori' : 'Tambah Kategori' }}</flux:heading>
        <flux:subheading>Isi detail kategori barang</flux:subheading>
    </div>

    <flux:card>
        <form wire:submit.prevent="save" class="space-y-6">
            <flux:input label="Nama" wire:model="name" :required="true" placeholder="cth. Elektronik" />
            <flux:field>
                <flux:label>Deskripsi</flux:label>
                <flux:textarea wire:model="description" rows="3" placeholder="Opsional deskripsi kategori" />
                <flux:error name="description" />
            </flux:field>

            <div class="flex justify-end gap-3">
                <flux:button :href="route('master.kategori')" wire:navigate variant="ghost">Batal</flux:button>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:card>
</div>