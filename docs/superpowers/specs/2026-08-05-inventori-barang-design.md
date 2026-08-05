# Desain: Sistem Manajemen Inventori (inventori-barang)

> Tanggal: 2026-08-05 · Status: Draft · Sumber PRD: `prd.md`

## 1. Ringkasan

Aplikasi web manajemen inventori dengan Laravel 13 + Livewire (Breeze starter kit) + MySQL. Fitur inti (MVP) mengikuti PRD: master data (barang, kategori, supplier), transaksi stok (masuk/keluar/retur), laporan (kartu stok, low stock, transaksi periode), dan auth berbasis peran (admin/staff).

## 2. Status Setup (Selesai)

- Proyek dibuat: `D:\laragon\www\inventori-barang` — Laravel 13, Livewire starter kit (Tailwind + Flux).
- Environment: PHP 8.3.13, Composer (`D:\laragon\bin\composer`), Node/npm via Laragon.
- `composer install`, `npm install`, `npm run build` selesai.
- `.env`: APP_KEY digenerate, DB `inventori_barang` di MySQL (root, tanpa password, Laragon default).
- Database dibuat, `php artisan migrate --seed` sukses.
- Verifikasi: `php artisan serve` → HTTP 200.
- Git repo diinisialisasi, file scaffold sudah dikomit.

## 3. Arsitektur

- **Frontend**: Livewire komponen per halaman, Blade + Tailwind + Flux UI (dari Breeze Livewire).
- **Backend**: Laravel 13, arsitektur standar (routes/web.php, controllers, models, migrations).
- **Database**: MySQL, schema relasional (lihat ERD di PRD §12).

### Data model (dari PRD, disederhanakan untuk implementasi pertama)

- `users` — sudah ada dari Breeze; tambah kolom `role` (admin/staff).
- `categories` — id, name, description.
- `suppliers` — id, name, address, phone.
- `products` — id, category_id (fk), supplier_id (fk), name, sku, price, unit, min_stock, stock.
- `transactions` — id, type (in/out/return), date, notes, user_id (fk).
- `transaction_items` — id, transaction_id (fk), product_id (fk), qty, price.

Stok barang dihitung otomatis dari `transaction_items` (saldo = sum(in) − sum(out) − sum(return)); kolom `stock` di `products` disinkronkan setelah transaksi.

## 4. Fitur & Alur

- **Auth**: register/login Breeze (existing). Login default = admin.
- **Dashboard**: ringkasan — total barang, stok menipis (low stock), transaksi terakhir.
- **Master Data** (admin):
  - Barang: CRUD + pencarian.
  - Kategori: CRUD sederhana.
  - Supplier: CRUD sederhana.
- **Transaksi** (staff & admin):
  - Stok masuk: pilih barang, qty, tanggal, supplier → update stok.
  - Stok keluar: pilih barang, qty, tanggal → validasi stok cukup → update stok.
  - Retur: pilih barang, qty, tanggal → update stok.
- **Laporan** (admin):
  - Kartu stok: riwayat per barang per periode (masuk/keluar/retur + saldo).
  - Low stock: daftar barang stok ≤ min_stock.
  - Transaksi periode: semua transaksi dalam rentang tanggal.
- **Role-based access**: middleware — hanya admin ke master data, user, laporan.

## 5. Error Handling & Validasi

- Validasi form (required, numeric qty ≥ 1, stok keluar tidak boleh melebihi stok tersedia).
- Error tampil via Livewire/Blade (sesuai pattern Breeze).
- Akses ditolak (403) untuk staff yang membuka halaman admin.

## 6. Testing

- Minimal: factory untuk tiap model utama; feature test alur inti (login, transaksi stok keluar menolak stok tak cukup, akses role).

## 7. Out of Scope (fase berikutnya)

- Export PDF/XLSX, API public, audit trail, multi-warehouse, notifikasi email/WhatsApp, prediksi AI. (Sesuai PRD §10, §17.)
