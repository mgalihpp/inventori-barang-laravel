<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div>
        <flux:heading size="xl">Dashboard</flux:heading>
        <flux:subheading>Ringkasan inventori</flux:subheading>
    </div>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card>
            <flux:heading>Total Barang</flux:heading>
            <flux:subheading>{{ $productCount }}</flux:subheading>
        </flux:card>
        <flux:card>
            <flux:heading>Kategori</flux:heading>
            <flux:subheading>{{ $categoryCount }}</flux:subheading>
        </flux:card>
        <flux:card>
            <flux:heading>Supplier</flux:heading>
            <flux:subheading>{{ $supplierCount }}</flux:subheading>
        </flux:card>
        <flux:card>
            <flux:heading>Stok Menipis</flux:heading>
            <flux:subheading>{{ $lowStockCount }}</flux:subheading>
        </flux:card>
    </div>
</div>
