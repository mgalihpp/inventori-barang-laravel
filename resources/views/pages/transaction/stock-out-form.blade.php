<div class="mx-auto max-w-3xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div>
        <flux:heading size="xl">Catat Stok Keluar</flux:heading>
        <flux:subheading>Catat barang keluar gudang</flux:subheading>
    </div>

    <flux:card>
        <form wire:submit.prevent="save" class="space-y-6">
            <flux:input label="Tanggal" wire:model="date" type="date" :required="true" class="max-w-xs" />

            <flux:textarea label="Catatan" wire:model="notes" placeholder="Catatan (opsional)" />

            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="sm">Barang</flux:heading>
                    <flux:button type="button" wire:click="addItem" variant="ghost" icon="plus" size="sm">Tambah Baris</flux:button>
                </div>

                @foreach ($items as $index => $item)
                    <div class="grid grid-cols-12 gap-2 items-end" wire:key="item-{{ $index }}">
                        <div class="col-span-5">
                            <flux:select wire:model="items.{{ $index }}.product_id" placeholder="Pilih barang">
                                @foreach ($products as $product)
                                    <flux:select.option :value="$product->id">{{ $product->name }} (stok: {{ $product->stock }})</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                        <div class="col-span-3">
                            <flux:input wire:model="items.{{ $index }}.qty" type="number" min="1" />
                        </div>
                        <div class="col-span-3">
                            <flux:input wire:model="items.{{ $index }}.price" type="number" step="0.01" min="0" />
                        </div>
                        <div class="col-span-1">
                            @if (count($items) > 1)
                                <flux:button type="button" wire:click="removeItem({{ $index }})" variant="ghost" icon="trash" size="sm" />
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex justify-end gap-3">
                <flux:button :href="route('transaksi.keluar')" wire:navigate variant="ghost">Batal</flux:button>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:card>
</div>
