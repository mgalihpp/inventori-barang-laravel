<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Kelola User</flux:heading>
            <flux:subheading>Kelola akun pengguna dan peran</flux:subheading>
        </div>
        <flux:button :href="route('users.create')" wire:navigate icon="plus">Tambah</flux:button>
    </div>

    <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari nama/email..." icon="magnifying-glass" class="max-w-sm" />

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Nama</flux:table.column>
            <flux:table.column>Email</flux:table.column>
            <flux:table.column>Role</flux:table.column>
            <flux:table.column class="text-right">Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($users as $user)
                <flux:table.row :key="$user->id">
                    <flux:table.cell variant="strong">{{ $user->name }}</flux:table.cell>
                    <flux:table.cell>{{ $user->email }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($user->role === 'admin')
                            <flux:badge color="violet" size="sm">Admin</flux:badge>
                        @else
                            <flux:badge color="blue" size="sm">Staff</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="text-right">
                        <div class="flex justify-end gap-1">
                            <flux:button :href="route('users.edit', $user)" wire:navigate variant="ghost" icon="pencil-square" size="sm" />
                            <flux:button wire:click.prevent="delete({{ $user->id }})" variant="ghost" size="sm" icon="trash" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4" variant="empty">Belum ada user.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{ $users->links() }}
</div>