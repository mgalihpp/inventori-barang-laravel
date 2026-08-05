<div class="mx-auto max-w-3xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div>
        <flux:heading size="xl">{{ request()->routeIs('users.create') ? 'Tambah User' : 'Edit User' }}</flux:heading>
        <flux:subheading>Isi detail pengguna</flux:subheading>
    </div>

    <flux:card>
        <form wire:submit.prevent="{{ request()->routeIs('users.create') ? 'save' : 'update' }}" class="space-y-6">
            <flux:input label="Nama" wire:model="name" :required="true" placeholder="Nama lengkap" />

            <flux:input label="Email" wire:model="email" type="email" :required="true" placeholder="email@example.com" />

            <flux:input label="{{ request()->routeIs('users.create') ? 'Password' : 'Password (kosongkan jika tidak diubah)' }}" wire:model="password" type="password" placeholder="{{ request()->routeIs('users.create') ? 'Minimal 8 karakter' : 'Minimal 8 karakter' }}" />

            <flux:select label="Role" wire:model="role" placeholder="Pilih role" :required="true">
                <flux:select.option value="admin">Admin</flux:select.option>
                <flux:select.option value="staff">Staff</flux:select.option>
            </flux:select>

            <div class="flex justify-end gap-3">
                <flux:button :href="route('users.index')" wire:navigate variant="ghost">Batal</flux:button>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:card>
</div>