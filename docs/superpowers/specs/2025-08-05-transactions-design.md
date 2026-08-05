# Spesifikasi: Transaksi (Stok Masuk, Stok Keluar, Retur)

**Versi:** 1.0 · **Status:** Draft · **Tanggal:** 2025-08-05

---

## 1. Ikhtisar

Fitur Transaksi mengimplementasikan pencatatan stok masuk, stok keluar, dan retur barang (PRD Phase 3). Modul ini melengkapi data master yang sudah ada dan mengubah stok produk secara otomatis.

**Navigasi sidebar:** 3 entitas terpisah (expandable group):
- `/transaksi/masuk` — Stok Masuk
- `/transaksi/keluar` — Stok Keluar
- `/transaksi/retur` — Retur

Setiap entitas: Index + Create. Tidak ada Edit/Detail di MVP (transaksi bersifat immutable).

---

## 2. Persyaratan

### Stok Masuk
- Staff login → pilih Stok Masuk → pilih barang, jumlah, supplier → simpan → stok bertambah
- Validasi: barang ada, jumlah > 0

### Stok Keluar
- Staff login → pilih Stok Keluar → pilih barang, jumlah → simpan → stok berkurang
- Validasi: barang ada, jumlah > 0, stok cukup (`products.stock >= qty`)
- Tidak ada pilihan supplier di MVP

### Retur (dua arah)
- `direction` = `'in'` (customer return → stok bertambah) atau `'out'` (ke supplier → stok berkurang)
- Direction `'in'`: supplier opsional
- Direction `'out'`: supplier wajib, stok harus cukup

---

## 3. Database

### Migration: `transactions`

| Kolom | Tipe | Notes |
|-------|------|-------|
| id | bigIncrements | PK |
| type | string(20) | `stok_masuk`, `stok_keluar`, `retur` |
| date | date | default: today |
| notes | text | nullable |
| user_id | foreignId | → users, cascade |
| direction | string(3) | `in`/`out`, nullable (hanya untuk retur) |
| supplier_id | foreignId | → suppliers, nullable, setNull |
| timestamps | | |

### Migration: `transaction_items`

| Kolom | Tipe | Notes |
|-------|------|-------|
| id | bigIncrements | PK |
| transaction_id | foreignId | → transactions, cascade |
| product_id | foreignId | → products, restrict |
| qty | unsignedInteger | > 0 |
| price | decimal(12,2) | harga per unit |
| timestamps | | |

### Relasi
```
Transaction hasMany TransactionItem
Transaction belongsTo User
Transaction belongsTo Supplier (nullable)
TransactionItem belongsTo Product
```

---

## 4. Model

### Transaction.php

```php
class Transaction extends Model
{
    public const TYPE_MASUK = 'stok_masuk';
    public const TYPE_KELUAR = 'stok_keluar';
    public const TYPE_RETUR = 'retur';

    protected $fillable = [
        'type', 'date', 'notes', 'user_id', 'direction', 'supplier_id',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function items(): HasMany { ... }
    public function user(): BelongsTo { ... }
    public function supplier(): BelongsTo { ... }

    protected static function boot(): void
    {
        static::creating(function (Transaction $tx) {
            $tx->user_id ??= auth()->id();

            if ($tx->type === self::TYPE_RETUR && ! in_array($tx->direction, ['in', 'out'])) {
                throw new InvalidArgumentException('Retur harus memiliki direction in/out');
            }
        });
    }
}
```

### TransactionItem.php

```php
class TransactionItem extends Model
{
    protected $fillable = ['transaction_id', 'product_id', 'qty', 'price'];

    public function transaction(): BelongsTo { ... }
    public function product(): BelongsTo { ... }

    protected static function boot(): void
    {
        static::created(function (TransactionItem $item) {
            $item->product->increment('stock', $item->delta());
        });
    }

    public function delta(): int
    {
        $type = $this->transaction->type;
        $direction = $this->transaction->direction;

        if ($type === Transaction::TYPE_MASUK) return $item->qty;
        if ($type === Transaction::TYPE_KELUAR) return -$item->qty;
        // retur
        return $direction === 'in' ? $item->qty : -$item->qty;
    }
}
```

**Pendekatan:** `delta()` menghitung perubahan stok berdasarkan tipe + direction. Satu method `increment('stock', delta())` menggantikan if/else panjang.

---

## 5. Livewire Components

### Struktur file

```
app/Livewire/Transaction/
  StockInIndex.php        → Index stok masuk
  StockInCreate.php       → Form tambah stok masuk
  StockOutIndex.php       → Index stok keluar
  StockOutCreate.php      → Form tambah stok keluar
  ReturIndex.php          → Index retur
  ReturCreate.php         → Form tambah retur
```

### Pola Index (contoh: StockInIndex)

```php
class StockInIndex extends Component
{
    use WithPagination;
    public string $search = '';

    public function render()
    {
        $transactions = Transaction::query()
            ->where('type', Transaction::TYPE_MASUK)
            ->with('items.product', 'user')
            ->when($this->search, fn ($q) => $q
                ->whereHas('items.product', fn ($p) => $p->where('name', 'like', "%{$this->search}%"))
                ->orWhere('notes', 'like', "%{$this->search}%"))
            ->latest('date')
            ->paginate(10);

        return view('pages.transaction.stock-in-index', ['transactions' => $transactions]);
    }
}
```

### Pola Create (contoh: StockInCreate)

```php
class StockInCreate extends Component
{
    public string $date = '';
    public ?int $supplier_id = null;
    public ?string $notes = null;
    public array $items = [];       // [{product_id, qty, price}]

    public function mount(): void
    {
        $this->date = now()->format('Y-m-d');
        $this->addItem(); // minimal 1 baris
    }

    public function addItem(): void
    {
        $this->items[] = ['product_id' => null, 'qty' => 1, 'price' => ''];
    }

    public function removeItem(int $index): void
    {
        if (count($this->items) > 1) {
            unset($this->items[$index]);
            $this->items = array_values($this->items);
        }
    }

    public function updatedItemsProductId(int $index): void
    {
        // Auto-fill harga dari produk
        $productId = $this->items[$index]['product_id'] ?? null;
        if ($productId) {
            $product = Product::find($productId);
            $this->items[$index]['price'] = (string) $product?->price;
        }
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'supplier_id' => ['required', 'exists:suppliers,id'], // stok masuk: supplier wajib
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        DB::transaction(function () {
            $transaction = Transaction::create([
                'type' => Transaction::TYPE_MASUK,
                'date' => $this->date,
                'supplier_id' => $this->supplier_id,
                'notes' => $this->notes,
            ]);

            foreach ($this->items as $item) {
                $transaction->items()->create($item);
                // Stok otomatis terupdate via model boot
            }
        });

        $this->redirect(route('transaksi.masuk'));
    }
}
```

### Perbedaan per tipe

| Aspek | Stok Masuk | Stok Keluar | Retur |
|-------|-----------|------------|-------|
| Supplier | wajib | tidak ada | `direction=in` opsional, `direction=out` wajib |
| Direction | tidak ada | tidak ada | dropdown `in`/`out` |
| Validasi stok | tidak perlu | stok >= qty | stok >= qty (hanya `direction=out`) |
| Route prefix | `/transaksi/masuk` | `/transaksi/keluar` | `/transaksi/retur` |

---

## 6. Routes

```php
Route::middleware(['auth', 'verified'])->prefix('transaksi')->group(function () {
    Route::livewire('masuk', StockInIndex::class)->name('transaksi.masuk');
    Route::livewire('masuk/create', StockInCreate::class)->name('transaksi.masuk.create');

    Route::livewire('keluar', StockOutIndex::class)->name('transaksi.keluar');
    Route::livewire('keluar/create', StockOutCreate::class)->name('transaksi.keluar.create');

    Route::livewire('retur', ReturIndex::class)->name('transaksi.retur');
    Route::livewire('retur/create', ReturCreate::class)->name('transaksi.retur.create');
});
```

---

## 7. Views

### Index page pattern (contoh: stock-in-index.blade.php)

- Header: "Stok Masuk" + tombol "Tambah"
- Search input
- Table kolom: Tanggal, Supplier, Barang (loop items), Total Qty, Dicatat Oleh, Catatan
- Pagination

### Form page pattern (contoh: stock-in-form.blade.php)

- Header: "Catat Stok Masuk"
- Field: Tanggal (date), Supplier (select), Catatan (textarea)
- Dynamic items section:
  - Setiap baris: Barang (select), Qty (number), Harga Satuan (number, auto-fill)
  - Tombol "Tambah Baris" / "Hapus Baris"
- Tombol: Batal / Simpan

### Views location

```
resources/views/pages/transaction/
  stock-in-index.blade.php
  stock-in-form.blade.php
  stock-out-index.blade.php
  stock-out-form.blade.php
  retur-index.blade.php
  retur-form.blade.php
```

---

## 8. Sidebar Update

Tambahkan expandable group Transaksi di sidebar (untuk admin DAN staff):

```blade
<flux:sidebar.group expandable heading="Transaksi" class="grid">
    <flux:sidebar.item icon="arrow-down" :href="route('transaksi.masuk')"
                       :current="request()->routeIs('transaksi.masuk*')" wire:navigate>
        Stok Masuk
    </flux:sidebar.item>
    <flux:sidebar.item icon="arrow-up" :href="route('transaksi.keluar')"
                       :current="request()->routeIs('transaksi.keluar*')" wire:navigate>
        Stok Keluar
    </flux:sidebar.item>
    <flux:sidebar.item icon="arrow-uturn-left" :href="route('transaksi.retur')"
                       :current="request()->routeIs('transaksi.retur*')" wire:navigate>
        Retur
    </flux:sidebar.item>
</flux:sidebar.group>
```

**Akses:** Admin DAN staff (tanpa role middleware, atau gunakan `ensure-role:admin,staff`).

---

## 9. Validasi & Error Handling

| Skenario | Error message |
|----------|--------------|
| Stok keluar tapi qty > stock | "Stok tidak cukup. Stok saat ini: X" |
| Retur keluar tapi qty > stock | "Stok tidak cukup. Stok saat ini: X" |
| Supplier kosong (stok masuk) | Laravel default: "The supplier id field is required." |
| Item kosong | "Minimal harus ada 1 barang" |
| Produk tidak ditemukan | Laravel default: validation error |

---

## 10. Event: Low Stock Notification

```php
// app/Events/TransactionCreated.php
class TransactionCreated
{
    use Dispatchable, SerializesModels;
    public function __construct(public Transaction $transaction) {}
}
```

Listener:
```php
// app/Listeners/CheckLowStock.php
class CheckLowStock
{
    public function handle(TransactionCreated $event): void
    {
        $lowStockProducts = Product::whereColumn('stock', '<=', 'min_stock')
            ->where('stock', '>', 0) // stok rendah, belum habis
            ->get();

        foreach ($lowStockProducts as $product) {
            // Flash message di session untuk notifikasi visual
            session()->push('low_stock_alerts', [
                'product' => $product->name,
                'stock' => $product->stock,
                'min' => $product->min_stock,
            ]);
        }
    }
}
```

EventServiceProvider:
```php
protected $listen = [
    TransactionCreated::class => [CheckLowStock::class],
];
```

---

## 11. Skema Transaksi

| Fitur | Komponen | File |
|-------|----------|------|
| Migration | transactions + transaction_items | database/migrations/ |
| Model | Transaction, TransactionItem | app/Models/ |
| Livewire | 6 komponen (Index+Create x3) | app/Livewire/Transaction/ |
| Views | 6 view (index+form x3) | resources/views/pages/transaction/ |
| Routes | 6 routes | routes/web.php |
| Sidebar | Update navigasi | resources/views/layouts/app/sidebar.blade.php |
| Event | TransactionCreated | app/Events/ |
| Listener | CheckLowStock | app/Listeners/ |
| Provider | Update EventServiceProvider | app/Providers/ |

---

## 12. Out-of-Scope (Fase Ini)

- Edit/hapus transaksi (audit trail di fase berikutnya)
- Export laporan (Fase 4)
- Notifikasi email/WhatsApp (Post-MVP)
- Kartu stok (Fase 4)
- Multi-gudang (Fase 3)

---

## 13. Acceptance Criteria

| Skenario | Expected |
|----------|----------|
| Staff catat stok masuk | Stok produk bertambah sesuai qty |
| Staff catat stok keluar | Stok produk berkurang sesuai qty |
| Staff catat stok keluar qty > stock | Error: "Stok tidak cukup" |
| Admin catat retur masuk | Stok produk bertambah |
| Staff catat retur keluar qty > stock | Error: "Stok tidak cukup" |
| Dashboard menampilkan low stock | Muncul notifikasi jika stok <= min_stock |
| Sidebar transaksi muncul untuk staff | Transaksi group visible untuk admin & staff |
