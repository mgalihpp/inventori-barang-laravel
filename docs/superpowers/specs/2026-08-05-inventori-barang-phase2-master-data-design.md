# Desain Phase 2: Master Data (Kategori, Supplier, Barang)

> Tanggal: 2026-08-05 · Status: Draft · Sumber PRD: `prd.md` §16 Bulan 2 · Precursor: `2026-08-05-inventori-barang-design.md`

## 1. Ringkasan

Phase 2 mengimplementasikan **Master Data** sesuai PRD: CRUD **Kategori**, **Supplier**, dan **Barang**, ditambah menghubungkan **dashboard** dengan data nyata (total barang, kategori, supplier, low stock). Fase ini hanya untuk role **admin** (middleware `ensure-role:admin`). Barang mendapat **SKU auto-generate** dan kolom stok awal.

## 2. Scope

### In-scope
- CRUD full-page Kategori (index, create, edit).
- CRUD full-page Supplier (index, create, edit).
- CRUD full-page Barang (index, create, edit) + SKU auto + stok awal.
- Pencarian (search) di halaman index tiap modul.
- Dashboard menampilkan hitungan data nyata (total barang/kategori/supplier, low stock).
- Delete protection: Kategori/Supplier yang masih punya Barang tidak bisa dihapus.
- Sidebar: submenu "Master Data" aktif (Kategori, Supplier, Barang) — hanya admin.

### Out-of-scope (fase berikutnya)
- Transaksi stok (masuk/keluar/retur) — Phase 3.
- Laporan (kartu stok, low stock, transaksi periode) — Phase 4.
- Notifikasi email/WhatsApp, export, API, audit trail.

## 3. Pendekatan

**Approach B — Halaman terpisah per form.** Tiap modul punya 3 komponen Livewire:
`Index` (tabel + search), `Create` (form tambah), `Edit` (form ubah).
Create & Edit berbagi satu view `*-form.blade.php`, dibedakan lewat properti model (null = create).
Delete memakai modal konfirmasi di view index. Alasan: URL RESTful jelas, form tidak tercampur tabel, mudah diuji, konsisten dengan jawaban kebutuhan user.

## 4. Arsitektur

- **Frontend**: Livewire full-page component, Blade + Tailwind + Flux UI (pattern Breeze 13).
- **Backend**: Laravel 13, arsitektur standar. Route via `Route::livewire()`.
- **Database**: MySQL, relasi `hasMany`/`belongsTo`.

### Data model

Migration baru: `create_categories_table`, `create_suppliers_table`, `create_products_table`.

- `categories` — id, `name` (string, unique), `description` (nullable).
- `suppliers` — id, `name`, `address` (nullable), `phone` (nullable).
- `products` — id, `category_id` (fk, nullable), `supplier_id` (fk, nullable), `name`, `sku` (string, unique), `price` (decimal 12,2, default 0), `unit` (string), `min_stock` (int, default 0), `stock` (int, default 0).

Relasi:
- `Category hasMany Product`, `Supplier hasMany Product`.
- `Product belongsTo Category` (nullable), `Product belongsTo Supplier` (nullable).

### SKU auto-generate

- Format: `KAT-0001` — prefiks 3 huruf singkatan dari nama kategori + running number 4 digit per kategori.
- Dihasilkan di event `Product::creating` (server-side, tidak user input).
- Contoh: kategori "Elektronik" → `ELE-0001`; `ELE-0002`, dst.
- Unik secara global karena prefiks+nomor kombinasi teks kategori + increment.

**Edge case SKU:**
- Kategori dengan nama < 3 huruf atau berisi spasi → ambil huruf pertama dari kata pertama (mis. kategori "AI" → `AI` prefiks, ID bisa lebih pendek dari 3). Tidak wajib bulat 3 selama unik.
- **Barang tanpa category (`category_id` null)** → pakai prefiks tetap `GEN` (generik). `GEN-0001`, dst.
- Increment per kategori dihitung dari jumlah products yang sudah memakai prefiks sama (`count` terakhir + 1), bukan max+1 → definisikan eksplisit agar deterministik & menyatu dengan nomor urut.
- Anti-kolisi: tetap jaga kolom `sku` unique, dan jika generate menghasilkan duplikat (sangat kecil), biarkan DB constraint memblokir dengan penanganan error.

## 5. Routes & Komponen

Semua route di dalam `Route::middleware(['auth','verified','ensure-role:admin'])`.

| Route name | URL | Komponen | View |
|-----------|-----|----------|------|
| `master.kategori` | `/master/kategori` | `Master\CategoryIndex` | `pages/master/categories` |
| `master.kategori.create` | `/master/kategori/create` | `Master\CategoryCreate` | `pages/master/category-form` |
| `master.kategori.edit` | `/master/kategori/{category}/edit` | `Master\CategoryEdit` | `pages/master/category-form` |
| `master.supplier` | `/master/supplier` | `Master\SupplierIndex` | `pages/master/suppliers` |
| `master.supplier.create` | `/master/supplier/create` | `Master\SupplierCreate` | `pages/master/supplier-form` |
| `master.supplier.edit` | `/master/supplier/{supplier}/edit` | `Master\SupplierEdit` | `pages/master/supplier-form` |
| `master.product` | `/master/barang` | `Master\ProductIndex` | `pages/master/products` |
| `master.product.create` | `/master/barang/create` | `Master\ProductCreate` | `pages/master/product-form` |
| `master.product.edit` | `/master/barang/{product}/edit` | `Master\ProductEdit` | `pages/master/product-form` |

Catatan lokal: judul/URL memakai kata bahasa Indonesia ("barang"), route name bahasa Inggris (`product`).

### Komponen Livewire (9)

- `CategoryIndex`: properti `$categories` (query + search via `$search`), method `delete($id)`.
- `CategoryCreate`: properti `$name`, `$description`; method `save()` → redirect ke index.
- `CategoryEdit`: properti `$category` (route-model binding), `$name`, `$description`; method `save()`.
- Pola yang sama untuk Supplier (`name`,`address`,`phone`) dan Product (`name`,`category_id`,`supplier_id`,`price`,`unit`,`min_stock`,`stock`).

### View

- `pages/master/categories`, `pages/master/suppliers`, `pages/master/products` — tabel + tombol Tambah/Edit/Hapus (modal konfirmasi hapus) + input pencarian.
- `pages/master/category-form`, `supplier-form`, `product-form` — form dipakai create & edit.
- Sidebar submenu "Master Data" (Kategori, Supplier, Barang) hanya tampil untuk admin via `auth()->user()->isAdmin()`.

## 6. Validasi

- **Kategori**: `name` required, unique (ignore current pada edit), max 100; `description` nullable.
- **Supplier**: `name` required, max 150; `address`, `phone` nullable.
- **Barang**: `name` required max 150; `category_id` nullable exists:categories; `supplier_id` nullable exists:suppliers; `price` required numeric min 0; `unit` required max 20; `min_stock` integer min 0; `stock` integer min 0.
- SKU tidak divalidasi input user (auto server-side).

## 7. Error Handling & Delete Protection

- Hapus Category/Supplier yang masih punya **products** → ditolak, tampilkan pesan error (flash/toast), bukan 500/DB error.
- Hapus Product pada Phase 2 langsung dihapus (transaksi belum ada). **Catatan**: saat Phase 3 (transaksi) tiba, hapus barang yang sudah dipakai `transaction_items` perlu dilindungi — tandai sebagai follow-up.
- Akses role: staff ke route `/master/*` → 403 (via `ensure-role:admin`).

## 8. Dashboard (integrasi data nyata)

`Dashboard` Livewire mengganti placeholder dengan query:
- `productCount = Product::count()`
- `categoryCount = Category::count()`
- `supplierCount = Supplier::count()`
- `lowStockCount = Product::whereColumn('stock','<=','min_stock')->count()`

## 9. Testing

Factory: `CategoryFactory`, `SupplierFactory`, `ProductFactory`.

Feature tests (`Livewire::test`, `RefreshDatabase`):
- CRUD Kategori: index+search, create valid, duplikat name gagal, edit, hapus, hapus kategori ber-products ditolak.
- CRUD Supplier: index, create valid/invalid, edit, hapus.
- CRUD Barang: create → SKU ter-generate, validasi price/min_stock/stock, edit, hapus.
- Role: tiap route index/create/edit → staff 403, admin 200.
- Dashboard: count dari data ter-seed benar.

Unit test SKU generator: format `KAT-0001`, increment per kategori.

## 10. Definisi Selesai (DoD)

- 9 komponen Livewire + 6 view form + 3 view index berfungsi.
- Migrasi & model dengan relasi benar.
- SKU auto-generate berjalan & unik.
- Delete protection aktif.
- Dashboard menampilkan angka nyata.
- Semua test (Pest) passing: `./vendor/bin/pest`.