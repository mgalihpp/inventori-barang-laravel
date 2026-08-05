<div class="mx-auto max-w-3xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div>
        <flux:heading size="xl">{{ $supplier ? 'Edit Supplier' : 'Tambah Supplier' }}</flux:heading>
        <flux:subheading>Isi detail supplier</flux:subheading>
    </div>

    <flux:card>
        <form wire:submit.prevent="save" class="space-y-6">
            <flux:input label="Nama" wire:model="name" :required="true" placeholder="cth. PT Maju" />
            <flux:field>
                <flux:label>Alamat</flux:label>
                <flux:textarea wire:model="address" rows="3" placeholder="Opsional alamat" />
                <flux:error name="address" />
            </flux:field>
            <flux:input label="Telepon" wire:model="phone" placeholder="Opsional nomor telepon" />

            <div class="flex justify-end gap-3">
                <flux:button :href="route('master.supplier')" wire:navigate variant="ghost">Batal</flux:button>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:card>
</div>