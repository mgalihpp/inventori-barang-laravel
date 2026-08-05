<?php

use App\Livewire\Dashboard;
use App\Livewire\Master\CategoryCreate;
use App\Livewire\Master\CategoryEdit;
use App\Livewire\Master\CategoryIndex;
use App\Livewire\Master\ProductCreate;
use App\Livewire\Master\ProductEdit;
use App\Livewire\Master\ProductIndex;
use App\Livewire\Master\SupplierCreate;
use App\Livewire\Master\SupplierEdit;
use App\Livewire\Master\SupplierIndex;
use App\Livewire\Transaction\ReturCreate;
use App\Livewire\Transaction\ReturIndex;
use App\Livewire\Transaction\StockInCreate;
use App\Livewire\Transaction\StockInIndex;
use App\Livewire\Transaction\StockOutCreate;
use App\Livewire\Transaction\StockOutIndex;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', Dashboard::class)->name('dashboard');
});

Route::middleware(['auth', 'verified', 'ensure-role:admin'])->prefix('master')->group(function () {
    Route::livewire('kategori', CategoryIndex::class)->name('master.kategori');
    Route::livewire('kategori/create', CategoryCreate::class)->name('master.kategori.create');
    Route::livewire('kategori/{category}/edit', CategoryEdit::class)->name('master.kategori.edit');

    Route::livewire('supplier', SupplierIndex::class)->name('master.supplier');
    Route::livewire('supplier/create', SupplierCreate::class)->name('master.supplier.create');
    Route::livewire('supplier/{supplier}/edit', SupplierEdit::class)->name('master.supplier.edit');

    Route::livewire('barang', ProductIndex::class)->name('master.product');
    Route::livewire('barang/create', ProductCreate::class)->name('master.product.create');
    Route::livewire('barang/{product}/edit', ProductEdit::class)->name('master.product.edit');
});

Route::middleware(['auth', 'verified'])->prefix('transaksi')->group(function () {
    Route::livewire('masuk', StockInIndex::class)->name('transaksi.masuk');
    Route::livewire('masuk/create', StockInCreate::class)->name('transaksi.masuk.create');

    Route::livewire('keluar', StockOutIndex::class)->name('transaksi.keluar');
    Route::livewire('keluar/create', StockOutCreate::class)->name('transaksi.keluar.create');

    Route::livewire('retur', ReturIndex::class)->name('transaksi.retur');
    Route::livewire('retur/create', ReturCreate::class)->name('transaksi.retur.create');
});

require __DIR__.'/settings.php';
