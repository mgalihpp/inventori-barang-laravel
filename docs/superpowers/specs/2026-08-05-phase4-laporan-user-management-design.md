# Desain Phase 4: Laporan + Kelola User & Role

> Tanggal: 2026-08-05 · Status: Draft · Sumber PRD: `prd.md` §16 Bulan 4 + §7 · Precursor: `2025-08-05-transactions-design.md`

## 1. Ikhtisar

Phase 4 mengimplementasikan **Laporan MVP** dan **Kelola User & Role** sesuai PRD. Fase ini melengkapi siklus MVP setelah master data (Phase 2) dan transaksi (Phase 3) selesai.

### Scope

**In-scope:**
- Laporan: **Kartu Stok** — riwayat transaksi + saldo berjalan per barang dalam periode.
- Laporan: **Low Stock** — daftar barang dengan stok di bawah/titik minimum.
- Laporan: **Transaksi Periode** — ringkasan semua transaksi dalam rentang waktu.
- **Kelola User & Role** — CRUD user, assign role `admin`/`staff`.
- Notifikasi **low stock** visual (session flash) setelah transaksi.
- Sidebar: group "Laporan" + "Kelola User" aktif.
- Akses: laporan admin & staff; user management hanya admin.

**Out-of-scope (fase berikutnya):**
- Export Excel/PDF (PRD §10 Fase 2).
- Notifikasi email/WhatsApp (Post-MVP).
- Audit trail, multi-warehouse, edit/hapus transaksi.
- Reset password oleh admin (user harus via lupa password sendiri).

## 2. Arsitektur

- **Frontend**: Livewire full-page, Blade + Tailwind + Flux UI (pattern Breeze 13, konsisten Phase 2-3).
- **Backend**: Laravel 13, route via `Route::livewire()`, middleware `ensure-role:admin` untuk admin-only.
- **Database**: MySQL, tidak ada migrasi baru (skema eksisting `products`, `transactions`, `transaction_items`, `users` cukup).

### File structure

```
app/Livewire/Reports/
  CardsStockReport.php     → Laporan Kartu Stok
  LowStockReport.php       → Laporan Low Stock
  PeriodReport.php         → Laporan Transaksi Periode

app/Livewire/UserManagement/
  UserIndex.php            → Daftar user (search + paginate + delete)
  UserCreate.php           → Form tambah user
  UserEdit.php             → Form edit user

resources/views/pages/reports/
  cards-stock.blade.php
  low-stock.blade.php
  period.blade.php

resources/views/pages/user-management/
  index.blade.php
  form.blade.php
```

## 3. Routes

```php
Route::middleware(['auth', 'verified'])->prefix('laporan')->group(function () {
    Route::livewire('kartu-stok', CardsStockReport::class)->name('laporan.kartu-stok');
    Route::livewire('low-stock', LowStockReport::class)->name('laporan.low-stock');
    Route::livewire('transaksi-periode', PeriodReport::class)->name('laporan.transaksi-periode');
});

Route::middleware(['auth', 'verified', 'ensure-role:admin'])->prefix('users')->group(function () {
    Route::livewire('', UserIndex::class)->name('users.index');
    Route::livewire('create', UserCreate::class)->name('users.create');
    Route::livewire('{user}/edit', UserEdit::class)->name('users.edit');
});
```

Catatan lokal: URL pakai bahasa Indonesia (`laporan/kartu-stok`, `users`), route name bahasa Inggris.

## 4. Laporan: Kartu Stok (CardsStockReport)

**Tujuan:** Riwayat transaksi per barang + saldo berjalan kronologis dalam periode.

**Query:**
- Eager load `TransactionItem` yang di-join ke `Transaction` dalam rentang `date_from`-`date_to`.
- Saldo awal dihitung **on-the-fly** (pilihan user): stok terkini minus total delta transaksi setelah periode.

```php
// Saldo awal produk p di periode [from, to]
$opening = $product->stock - DB::table('transaction_items as ti')
    ->join('transactions as t', 'ti.transaction_id', '=', 't.id')
    ->where('ti.product_id', $product->id)
    ->whereDate('t.date', '>', $this->dateTo)
    ->sum($deltaExpression); // SQL CASE berdasar t.type + t.direction
```

- Baris transaksi dihitung per item; delta pakai logika sama seperti `TransactionItem::getDelta()` (Phase 3): masuk `+qty`, keluar `-qty`, retur sesuai `direction`.
- Saldo berjalan = saldo awal + akumulasi delta per baris (urut tanggal, id).

**Properties Livewire:**
```php
public string $productSearch = '';   // pilih barang via search
public ?int $product_id = null;
public string $date_from = '';
public string $date_to = '';
```

Default `date_from` = awal bulan, `date_to` = hari ini.

**View:** header + input pilih barang (searchable select) + range tanggal + tabel:
| Tanggal | Jenis | Ref (notes) | Qty | Delta | Saldo |
Kosong jika barang belum dipilih atau tidak ada transaksi.

## 5. Laporan: Low Stock (LowStockReport)

**Tujuan:** Daftar barang dengan `stock <= min_stock`.

**Query:**
```php
Product::with('category')
    ->whereColumn('stock', '<=', 'min_stock')
    ->orderByRaw('stock - min_stock')
    ->paginate(10);
```

**Properties:** `search` (filter nama), paginasi `WithPagination`.

**View:** tabel | Barang | Kategori | Stok Saat Ini | Stok Min | Status (Menipis/Habis bila stock=0) |. Kolom status pakai badge warna (Flux).

## 6. Laporan: Transaksi Periode (PeriodReport)

**Tujuan:** Ringkasan transaksi dalam rentang tanggal.

**Query:**
```php
Transaction::with('items.product', 'user', 'supplier')
    ->whereBetween('date', [$this->date_from, $this->date_to])
    ->orderByDesc('date');
```

Ringkasan dihitung terpisah per `type`:
```php
$summary = Transaction::whereBetween('date', [$from, $to])
    ->selectRaw('type, count(*) as total_transactions, sum(item_qty) as total_qty')
    // via subquery/join transaction_items
    ->groupBy('type')->get();
```

**Properties:** `date_from`, `date_to` (default: awal & akhir bulan ini), `search`, paginasi.

**View:**
- Ringkasan kartu statistik: 3 kartu (Stok Masuk / Stok Keluar / Retur) berisi jumlah transaksi + total qty.
- Tabel detail transaksi (kolom sama seperti index transaksi Phase 3) + paginasi.

## 7. Kelola User & Role (UserIndex / UserCreate / UserEdit)

**UserIndex:**
- `WithPagination`, `search` (nama/email), tabel | Nama | Email | Role (badge) | Aksi (Edit, Hapus).
- `delete($id)`: hapus user, dengan proteksi:
  - Tidak bisa menghapus diri sendiri.
  - Tidak bisa menghapus admin terakhir (`User::where('role','admin')->count() <= 1`).
  - Error → flash error (toast), bukan exception.

**UserCreate / UserEdit:**
- Form: `name`, `email`, `password` (create: required min 8; edit: nullable, kosong = tidak diubah), `role` (select admin/staff).
- Validasi:
```php
'name' => ['required', 'string', 'max:255'],
'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$this->user?->id],
'password' => $this->isCreate ? ['required', 'string', 'min:8'] : ['nullable', 'string', 'min:8'],
'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_STAFF])],
```
- Save → redirect `users.index` dengan flash sukses.

**View:** `index.blade.php` (tabel + tombol Tambah + search) dan `form.blade.php` (dipakai create & edit, pola `*-form` Phase 2).

## 8. Notifikasi Low Stock

Tambahan dari PRD §5 (UC_Notif), belum terimplementasi di Phase 3:

```php
// app/Events/TransactionCreated.php
class TransactionCreated
{
    use Dispatchable, SerializesModels;
    public function __construct(public Transaction $transaction) {}
}

// app/Listeners/CheckLowStock.php
class CheckLowStock
{
    public function handle(TransactionCreated $event): void
    {
        $low = Product::whereColumn('stock', '<=', 'min_stock')
            ->where('stock', '>', 0)
            ->get();

        foreach ($low as $product) {
            session()->push('low_stock_alerts', [
                'product' => $product->name,
                'stock' => $product->stock,
                'min' => $product->min_stock,
            ]);
        }
    }
}
```

- Event di-dispatch dari `save()` tiap komponen Create transaksi (Phase 3): `StockInCreate`, `StockOutCreate`, `ReturCreate` setelah `DB::transaction` sukses.
- Buat `EventServiceProvider` (standard Laravel) & register listener:
```php
// app/Providers/EventServiceProvider.php
class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        TransactionCreated::class => [CheckLowStock::class],
    ];
}
```
- Event di-dispatch dari `save()` tiap komponen Create transaksi (Phase 3): `StockInCreate`, `StockOutCreate`, `ReturCreate` setelah `DB::transaction` sukses.
- Tampilan: badge/banner low stock di halaman dashboard + link ke laporan low stock.

## 9. Sidebar

Update `resources/views/layouts/app/sidebar.blade.php`:

```blade
{{-- Laporan: admin & staff --}}
<flux:sidebar.item icon="chart-bar" :href="route('laporan.kartu-stok')"
                   :current="request()->routeIs('laporan.*')" wire:navigate>
    {{ __('Laporan') }}
</flux:sidebar.item>

{{-- Kelola User: hanya admin (dalam blok @if isAdmin) --}}
<flux:sidebar.item icon="users" :href="route('users.index')"
                   :current="request()->routeIs('users.*')" wire:navigate>
    {{ __('Kelola User') }}
</flux:sidebar.item>
```

## 10. Error Handling

| Skenario | Penanganan |
|----------|-----------|
| Hapus admin terakhir | Flash error "Tidak bisa menghapus admin terakhir" |
| Hapus diri sendiri | Flash error "Tidak bisa menghapus akun sendiri" |
| Akses staff ke `/users/*` | 403 via `ensure-role:admin` |
| Barang belum dipilih di kartu stok | Tampilkan placeholder "Pilih barang untuk melihat kartu stok" |
| Tidak ada transaksi di periode | Tampilkan baris kosong + summary 0 |
| Email duplikat saat edit | Validasi `unique` ignore current |

## 11. Testing

Factories: `UserFactory` sudah ada (role default staff).

Feature tests (Pest + Livewire, pola Phase 2-3):
- **Kartu Stok**: buka dengan product_id → saldo awal + saldo berjalan benar (seeder transaksi masuk/keluar/retur); tanpa product → placeholder.
- **Low Stock**: seeder produk stock=0 dan stock<=min → muncul; produk cukup → tidak muncul; search bekerja.
- **Transaksi Periode**: filter date range benar; summary per type benar.
- **User CRUD**: create valid → redirect + user ada; email duplikat gagal; edit role berubah; password kosong di edit tidak mengganti.
- **Proteksi delete**: hapus admin terakhir ditolak; hapus diri sendiri ditolak.
- **Role**: routes `/laporan/*` → staff 200, admin 200; routes `/users/*` → staff 403, admin 200.
- **Notifikasi**: transaksi create → session `low_stock_alerts` terisi jika ada produk low.

## 12. Definisi Selesai (DoD)

- 6 komponen Livewire baru + 4 view baru berfungsi.
- Ketiga laporan menampilkan data benar (verifikasi manual + test).
- CRUD user + assign role + delete protection berjalan.
- Notifikasi low stock muncul di dashboard setelah transaksi.
- Sidebar mencakup Laporan (semua) dan Kelola User (admin).
- Semua test (Pest) passing: `./vendor/bin/pest`.
