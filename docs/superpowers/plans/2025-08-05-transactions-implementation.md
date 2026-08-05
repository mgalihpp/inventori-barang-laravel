# Transaksi (Stok Masuk, Stok Keluar, Retur) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement Stok Masuk, Stok Keluar, and Retur transaction features matching PRD Phase 3 requirements

**Architecture:** Follow existing Laravel + Livewire patterns using Flux UI components. Database: MySQL with `transactions` table and `transaction_items` junction table. Each transaction type has its own Livewire component (Index + Create).

**Tech Stack:** Laravel 13, Livewire 4, Flux UI, MySQL, PHPUnit, Livewire Testing

## Global Constraints

- Follow existing naming conventions and patterns from `app/Livewire/Master/`
- Use existing validation rules and error handling patterns
- Adhere to existing test structure in `tests/Feature/Master/`
- Role-based access: admin & staff can manage transactions
- Follow existing database migration naming (`2025_08_14_170933_add_two_factor_columns_to_users_table.php`)
- Use existing test factories patterns
- MySQL DB with SQLite `:memory:` for testing

---

## File Structure

### Files to Create

```
database/migrations/
  2025_08_25_000000_create_transactions_table.php
  2025_08_25_000001_create_transaction_items_table.php

app/Models/
  Transaction.php
  TransactionItem.php

app/Livewire/Transaction/
  StockInIndex.php
  StockInCreate.php
  StockOutIndex.php
  StockOutCreate.php
  ReturIndex.php
  ReturCreate.php

resources/views/pages/transaction/
  stock-in-index.blade.php
  stock-in-form.blade.php
  stock-out-index.blade.php
  stock-out-form.blade.php
  retur-index.blade.php
  retur-form.blade.php

tests/Feature/Transaction/
  StockInTransactionTest.php
  StockOutTransactionTest.php
  ReturTransactionTest.php
```

### Files to Modify

```
routes/web.php (add transaction routes)
resources/views/layouts/app/sidebar.blade.php (add Transaksi group)
app/Providers/EventServiceProvider.php (register TransactionCreated event)
```

---

## Task 1: Database Migrations

**Files:**
- Create: `database/migrations/2025_08_25_000000_create_transactions_table.php`
- Create: `database/migrations/2025_08_25_000001_create_transaction_items_table.php`

**Interfaces:**
- None (foundational schema)

- [ ] **Step 1: Write the failing test**
```php
<?php
// tests/Feature/Transaction/MigrationTest.php
namespace Tests\Feature\Transaction;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_transactions_table_has_required_columns(): void
    {
        \$this->artisan('migrate');

        \$this->assertTrue(\\Schema::hasTable('transactions'));
        \$this->assertTrue(\\Schema::hasColumn('transactions', 'type'));
        \$this->assertTrue(\\Schema::hasColumn('transactions', 'date'));
        \$this->assertTrue(\\Schema::hasColumn('transactions', 'notes'));
        \$this->assertTrue(\\Schema::hasColumn('transactions', 'user_id'));
        \$this->assertTrue(\\Schema::hasColumn('transactions', 'direction'));
        \$this->assertTrue(\\Schema::hasColumn('transactions', 'supplier_id'));
    }

    public function test_transaction_items_table_has_required_columns(): void
    {
        \$this->artisan('migrate');

        \$this->assertTrue(\\Schema::hasTable('transaction_items'));
        \$this->assertTrue(\\Schema::hasColumn('transaction_items', 'transaction_id'));
        \$this->assertTrue(\\Schema::hasColumn('transaction_items', 'product_id'));
        \$this->assertTrue(\\Schema::hasColumn('transaction_items', 'qty'));
        \$this->assertTrue(\\Schema::hasColumn('transaction_items', 'price'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**
```bash
php artisan test --filter=MigrationTest
```
Expected: FAIL (tables don't exist yet)

- [ ] **Step 3: Write minimal implementation**

**transactions_table migration:**
```php
<?php
namespace Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->date('date')->useCurrent();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 3)->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
```

**transaction_items_table migration:**
```php
<?php
namespace Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('qty');
            $table->decimal('price', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_items');
    }
};
```

- [ ] **Step 4: Run test to verify it passes**
```bash
php artisan test --filter=MigrationTest
```
Expected: PASS

- [ ] **Step 5: Commit**
```bash
git add database/migrations/
git commit -m "feat: add transactions and transaction_items migration tables"
```

---

## Task 2: Models (Transaction, TransactionItem)

**Files:**
- Create: `app/Models/Transaction.php`
- Create: `app/Models/TransactionItem.php`

**Interfaces:**
- Consumes: User model, Product model, Supplier model
- Produces: Transaction, TransactionItem models with relationships

- [ ] **Step 1: Write the failing test**
```php
<?php
// tests/Feature/Transaction/ModelTest.php
namespace Tests\Feature\Transaction;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_relationships(): void
    {
        \$user = User::factory()->create();
        \$supplier = Supplier::factory()->create();
        \$product = Product::factory()->create();

        \$transaction = Transaction::create([
            'type' => Transaction::TYPE_MASUK,
            'date' => now(),
            'user_id' => \$user->id,
            'supplier_id' => \$supplier->id,
        ]);

        \$item = \$transaction->items()->create([
            'product_id' => \$product->id,
            'qty' => 5,
            'price' => 100000,
        ]);

        \$this->assertNotNull(\$transaction);
        \$this->assertDatabaseHas('transactions', ['id' => \$transaction->id]);
        \$this->assertDatabaseHas('transaction_items', [
            'transaction_id' => \$transaction->id,
            'product_id' => \$product->id,
        ]);
    }

    public function test_stock_increases_on_stock_in(): void
    {
        \$product = Product::factory()->create(['stock' => 10]);
        \$user = User::factory()->create();

        \$transaction = Transaction::create([
            'type' => Transaction::TYPE_MASUK,
            'date' => now(),
            'user_id' => \$user->id,
        ]);

        \$transaction->items()->create([
            'product_id' => \$product->id,
            'qty' => 5,
            'price' => 100000,
        ]);

        \$product->refresh();
        \$this->assertEquals(15, \$product->stock);
    }

    public function test_stock_decreases_on_stock_out(): void
    {
        \$product = Product::factory()->create(['stock' => 10]);
        \$user = User::factory()->create();

        \$transaction = Transaction::create([
            'type' => Transaction::TYPE_KELUAR,
            'date' => now(),
            'user_id' => \$user->id,
        ]);

        \$transaction->items()->create([
            'product_id' => \$product->id,
            'qty' => 5,
            'price' => 100000,
        ]);

        \$product->refresh();
        \$this->assertEquals(5, \$product->stock);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**
```bash
php artisan test --filter=ModelTest
```
Expected: FAIL (models don't exist)

- [ ] **Step 3: Write minimal implementation**

**Transaction.php:**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    use HasFactory;

    public const TYPE_MASUK = 'stok_masuk';
    public const TYPE_KELUAR = 'stok_keluar';
    public const TYPE_RETUR = 'retur';

    protected \$fillable = [
        'type',
        'date',
        'notes',
        'user_id',
        'direction',
        'supplier_id',
    ];

    protected \$casts = [
        'date' => 'date',
    ];

    public function items(): HasMany
    {
        return \$this->hasMany(TransactionItem::class);
    }

    public function user(): BelongsTo
    {
        return \$this->belongsTo(User::class);
    }

    public function supplier(): BelongsTo
    {
        return \$this->belongsTo(Supplier::class);
    }
}
```

**TransactionItem.php:**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionItem extends Model
{
    use HasFactory;

    protected \$fillable = [
        'transaction_id',
        'product_id',
        'qty',
        'price',
    ];

    public function transaction(): BelongsTo
    {
        return \$this->belongsTo(Transaction::class);
    }

    public function product(): BelongsTo
    {
        return \$this->belongsTo(Product::class);
    }

    protected static function boot(): void
    {
        static::created(function (TransactionItem \$item) {
            \$product = \$item->product;
            \$delta = \$item->getDelta();

            if (\$delta > 0) {
                \$product->increment('stock', \$delta);
            } else {
                \$product->decrement('stock', abs(\$delta));
            }
        });
    }

    public function getDelta(): int
    {
        \$type = \$this->transaction->type;
        \$direction = \$this->transaction->direction;

        if (\$type === Transaction::TYPE_MASUK) {
            return \$this->qty;
        }

        if (\$type === Transaction::TYPE_KELUAR) {
            return -\$this->qty;
        }

        // Retur
        return \$direction === 'in' ? \$this->qty : -\$this->qty;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**
```bash
php artisan test --filter=ModelTest
```
Expected: PASS

- [ ] **Step 5: Commit**
```bash
git add app/Models/Transaction.php app/Models/TransactionItem.php
git commit -m "feat: add Transaction and TransactionItem models with stock update logic"
```

---

## Task 3: Routes & Navigation

**Files:**
- Modify: `routes/web.php:18-31` (add transaction routes)
- Modify: `resources/views/layouts/app/sidebar.blade.php` (add Transaksi group)

**Interfaces:**
- Consumes: StockInIndex, StockOutIndex, ReturIndex Livewire components
- Produces: route('transaksi.masuk'), route('transaksi.keluar'), route('transaksi.retur')

- [ ] **Step 1: Write the failing test**
```php
<?php
// tests/Feature/Transaction/RouteTest.php
namespace Tests\Feature\Transaction;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteTest extends TestCase
{
    use RefreshDatabase;

    protected function staff(): User
    {
        return User::factory()->create(['role' => 'staff']);
    }

    public function test_stock_in_index_accessible_by_staff(): void
    {
        \$this->actingAs(\$this->staff())\n            ->get(route('transaksi.masuk'))\n            ->assertOk();\n    }

    public function test_stock_out_index_accessible_by_staff(): void\n    {\n        \$this->actingAs(\$this->staff())\n            ->get(route('transaksi.keluar'))\n            ->assertOk();\n    }

    public function test_retur_index_accessible_by_staff(): void\n    {\n        \$this->actingAs(\$this->staff())\n            ->get(route('transaksi.retur'))\n            ->assertOk();\n    }
}
```

- [ ] **Step 2: Run test to verify it fails**
```bash
php artisan test --filter=RouteTest
```
Expected: FAIL (routes don't exist)

- [ ] **Step 3: Write minimal implementation**

**Add to routes/web.php (after master routes):**
```php
// routes/web.php - add after line 30
Route::middleware(['auth', 'verified'])->prefix('transaksi')->group(function () {
    Route::livewire('masuk', \\App\\Livewire\\Transaction\\StockInIndex::class)
        ->name('transaksi.masuk');
    Route::livewire('masuk/create', \\App\\Livewire\\Transaction\\StockInCreate::class)
        ->name('transaksi.masuk.create');

    Route::livewire('keluar', \\App\\Livewire\\Transaction\\StockOutIndex::class)
        ->name('transaksi.keluar');
    Route::livewire('keluar/create', \\App\\Livewire\\Transaction\\StockOutCreate::class)
        ->name('transaksi.keluar.create');

    Route::livewire('retur', \\App\\Livewire\\Transaction\\ReturIndex::class)
        ->name('transaksi.retur');
    Route::livewire('retur/create', \\App\\Livewire\\Transaction\\ReturCreate::class)
        ->name('transaksi.retur.create');
});
```

**Update sidebar.blade.php (after Barang menu item):**
```blade
// resources/views/layouts/app/sidebar.blade.php - replace after line 48
<flux:sidebar.group expandable heading=\"Transaksi\" class=\"grid\">
    <flux:sidebar.item icon=\"arrow-down\" :href=\"route('transaksi.masuk')\"\n                       :current=\"request()->routeIs('transaksi.masuk*')\" wire:navigate>
        Stok Masuk
    </flux:sidebar.item>
    <flux:sidebar.item icon=\"arrow-up\" :href=\"route('transaksi.keluar')\"\n                       :current=\"request()->routeIs('transaksi.keluar*')\" wire:navigate>
        Stok Keluar
    </flux:sidebar.item>
    <flux:sidebar.item icon=\"arrow-uturn-left\" :href=\"route('transaksi.retur')\"\n                       :current=\"request()->routeIs('transaksi.retur*')\" wire:navigate>
        Retur
    </flux:sidebar.item>
</flux:sidebar.group>
```\n\n- [ ] **Step 4: Run test to verify it passes**
```bash\nphp artisan test --filter=RouteTest\n```\nExpected: PASS\n\n- [ ] **Step 5: Commit**
```bash\ngit add routes/web.php resources/views/layouts/app/sidebar.blade.php\ngit commit -m \"feat: add transaction routes and sidebar navigation\"\n```\n\n---\n\n## Task 4: Stock In Components\n\n**Files:**\n- Create: \`app/Livewire/Transaction/StockInIndex.php\`\n- Create: \`app/Livewire/Transaction/StockInCreate.php\`\n- Create: \`resources/views/pages/transaction/stock-in-index.blade.php\`\n- Create: \`resources/views/pages/transaction/stock-in-form.blade.php\`\n\n**Interfaces:**\n- Consumes: Transaction, TransactionItem, Product, Supplier models\n- Produces: route(\'transaksi.masuk\'), route(\'transaksi.masuk.create\')\n\n- [ ] **Step 1: Write the failing test**
```php\n<?php\n// tests/Feature/Transaction/StockInTransactionTest.php\nnamespace Tests\\Feature\\Transaction;\n\nuse App\\Livewire\\Transaction\\StockInCreate;\nuse App\\Livewire\\Transaction\\StockInIndex;\nuse App\\Models\\Product;\nuse App\\Models\\Supplier;\nuse App\\Models\\User;\nuse Illuminate\\Foundation\\Testing\\RefreshDatabase;\nuse Livewire\\Livewire;\nuse Tests\\TestCase;\n\nclass StockInTransactionTest extends TestCase\n{\n    use RefreshDatabase;\n\n    protected function staff(): User\n    {\n        return User::factory()->create(['role' => 'staff']);\n    }\n\n    public function test_index_lists_transactions(): void\n    {\n        \$this->actingAs(\$this->staff())\n            ->get(route('transaksi.masuk'))\n            ->assertOk();\n    }\n\n    public function test_create_transaction_increases_stock(): void\n    {\n        \$product = Product::factory()->create(['stock' => 10]);\n        \$supplier = Supplier::factory()->create();\n\n        Livewire::actingAs(\$this->staff())\n            ->test(StockInCreate::class)\n            ->set(\'date\', now()->format(\'Y-m-d\'))\n            ->set(\'supplier_id\', \$supplier->id)\n            ->set(\'items\', [[\'product_id\' => \$product->id, \'qty\' => 5, \'price\' => 100000]])\n            ->call(\'save\')\n            ->assertRedirect(route(\'transaksi.masuk\'));\n\n        \$product->refresh();\n        \$this->assertEquals(15, \$product->stock);\n    }\n\n    public function test_validation_fails_without_supplier(): void\n    {\n        Livewire::actingAs(\$this->staff())\n            ->test(StockInCreate::class)\n            ->set(\'date\', now()->format(\'Y-m-d\'))\n            ->set(\'items\', [])\n            ->call(\'save\')\n            ->assertHasErrors([\'supplier_id\', \'items\']);\n    }\n}\n```\n\n- [ ] **Step 2: Run test to verify it fails**\n```bash\nphp artisan test --filter=StockInTransactionTest\n```\nExpected: FAIL (components don't exist)\n\n- [ ] **Step 3: Write minimal implementation**\n\n**StockInIndex.php:**\n```php\n<?php\n\nnamespace App\\Livewire\\Transaction;\n\nuse App\\Models\\Transaction;\nuse Livewire\\Component;\nuse Livewire\\WithPagination;\n\nclass StockInIndex extends Component\n{\n    use WithPagination;\n\n    public string \$search = '';\n\n    public function render()\n    {\n        \$transactions = Transaction::query()\n            ->where(\'type\', Transaction::TYPE_MASUK)\n            ->with(\'items.product\', \'user\')\n            ->when(\$this->search, fn (\$q) => \$q\n                ->whereHas(\'items.product\', fn (\$p) => \$p->where(\'name\', \'like\', \"\%{$this->search}%\"))\n                ->orWhere(\'notes\', \'like\', \"\%{$this->search}%\"))\n            ->latest(\'date\')\n            ->paginate(10);\n\n        return view(\'pages.transaction.stock-in-index\', [\'transactions\' => \$transactions]);\n    }\n}\n```\n\n**StockInCreate.php:**\n```php\n<?php\n\nnamespace App\\Livewire\\Transaction;\n\nuse App\\Models\\Product;\nuse App\\Models\\Supplier;\nuse App\\Models\\Transaction;\nuse Illuminate\\Support\\Facades\\DB;\nuse Livewire\\Component;\n\nclass StockInCreate extends Component\n{\n    public string \$date = \'\';\n    public ?int \$supplier_id = null;\n    public ?string \$notes = null;\n    public array \$items = [];\n\n    protected array \$rules = [\n        \'date\' => [\'required\', \'date\'],\n        \'supplier_id\' => [\'required\', \'exists:suppliers,id\'],\n        \'notes\' => [\'nullable\', \'string\'],\n        \'items\' => [\'required\', \'array\', \'min:1\'],\n        \'items.*.product_id\' => [\'required\', \'exists:products,id\'],\n        \'items.*.qty\' => [\'required\', \'integer\', \'min:1\'],\n        \'items.*.price\' => [\'required\', \'numeric\', \'min:0\'],\n    ];\n\n    protected array \$messages = [\n        \'supplier_id.required\' => \'Supplier wajib dipilih untuk stok masuk\',\n        \'items.required\' => \'Minimal harus ada 1 barang\',\n    ];\n\n    public function mount(): void\n    {\n        \$this->date = now()->format(\'Y-m-d\');\n        \$this->addItem();\n    }\n\n    public function addItem(): void\n    {\n        \$this->items[] = [\'product_id\' => null, \'qty\' => 1, \'price\' => \'\'];\n    }\n\n    public function removeItem(int \$index): void\n    {\n        if (count(\$this->items) > 1) {\n            unset(\$this->items[\$index]);\n            \$this->items = array_values(\$this->items);\n        }\n    }\n\n    public function updatedItemsProductId(int \$index): void\n    {\n        \$productId = \$this->items[\$index][\'product_id\'] ?? null;\n        if (\$productId) {\n            \$product = Product::find(\$productId);\n            if (\$product) {\n                \$this->items[\$index][\'price\'] = (string) \$product->price;\n            }\n        }\n    }\n\n    public function save(): void\n    {\n        \$this->validate();\n\n        DB::transaction(function () {\n            \$transaction = Transaction::create([\n                \'type\' => Transaction::TYPE_MASUK,\n                \'date\' => \$this->date,\n                \'supplier_id\' => \$this->supplier_id,\n                \'notes\' => \$this->notes,\n            ]);\n\n            foreach (\$this->items as \$item) {\n                \$transaction->items()->create(\$item);\n            }\n        });\n\n        \$this->redirect(route(\'transaksi.masuk\'));\n    }\n\n    public function render()\n    {\n        return view(\'pages.transaction.stock-in-form\', [\n            \'suppliers\' => Supplier::orderBy(\'name\')->get(),\n            \'products\' => Product::orderBy(\'name\')->get(),\n        ]);\n    }\n}\n```\n\n**stock-in-index.blade.php:**\n```blade\n<div class=\"mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8\">\n    <div class=\"flex items-center justify-between\">\n        <div>\n            <flux:heading size=\"xl\">Stok Masuk</flux:heading>\n            <flux:subheading>Daftar transaksi stok masuk</flux:subheading>\n        </div>\n        <flux:button :href=\"route(\'transaksi.masuk.create\')\" wire:navigate icon=\"plus\">Tambah</flux:button>\n    </div>\n\n    <flux:input wire:model.live.debounce.300ms=\"search\" placeholder=\"Cari barang...\" icon=\"magnifying-glass\" class=\"max-w-sm\" />\n\n    <flux:table>\n        <flux:table.columns>\n            <flux:table.column>Tanggal</flux:table.column>\n            <flux:table.column>Supplier</flux:table.column>\n            <flux:table.column>Barang</flux:table.column>\n            <flux:table.column>Total Qty</flux:table.column>\n            <flux:table.column>Dicatat Oleh</flux:table.column>\n            <flux:table.column>Catatan</flux:table.column>\n        </flux:table.columns>\n\n        <flux:table.rows>\n            @forelse (\$transactions as \$transaction)\n                <flux:table.row :key=\"\$transaction->id\">\n                    <flux:table.cell>{!! \$transaction->date->format(\'d/m/Y\') !!}</flux:table.cell>\n                    <flux:table.cell>{!! \$transaction->supplier?->name ?? \'-\' !!}</flux:table.cell>\n                    <flux:table.cell>\n                        @foreach (\$transaction->items as \$item)\n                            <div>{!! \$item->product?->name !!} ({!! \$item->qty !!})</div>\n                        @endforeach\n                    </flux:table.cell>\n                    <flux:table.cell>{!! \$transaction->items->sum(\'qty\') !!}</flux:table.cell>\n                    <flux:table.cell>{!! \$transaction->user?->name ?? \'-\' !!}</flux:table.cell>\n                    <flux:table.cell>{!! \$transaction->notes ?? \'-\' !!}</flux:table.cell>\n                </flux:table.row>\n            @empty\n                <flux:table.row>\n                    <flux:table.cell colspan=\"6\" variant=\"empty\">Belum ada transaksi stok masuk.</flux:table.cell>\n                </flux:table.row>\n            @endforelse\n        </flux:table.rows>\n    </flux:table>\n\n    {{ \$transactions->links() }}\n</div>\n```\n\n**stock-in-form.blade.php:**\n```blade\n<div class=\"mx-auto max-w-3xl space-y-6 px-4 py-6 sm:px-6 lg:px-8\">\n    <div>\n        <flux:heading size=\"xl\">Catat Stok Masuk</flux:heading>\n        <flux:subheading>Tambah barang masuk dari supplier</flux:subheading>\n    </div>\n\n    <flux:card>\n        <form wire:submit.prevent=\"save\" class=\"space-y-6\">\n            <div class=\"grid gap-4 sm:grid-cols-2\">\n                <flux:input label=\"Tanggal\" wire:model=\"date\" type=\"date\" :required=\"true\" />\n                <flux:select label=\"Supplier\" wire:model=\"supplier_id\" placeholder=\"Pilih supplier\" :required=\"true\">\n                    @foreach (\$suppliers as \$supplier)\n                        <flux:select.option :value=\"\$supplier->id\">{!! \$supplier->name !!}</flux:select.option>\n                    @endforeach\n                </flux:select>\n            </div>\n\n            <flux:textarea label=\"Catatan\" wire:model=\"notes\" placeholder=\"Catatan (opsional)\" />\n\n            <div class=\"space-y-4\">\n                <div class=\"flex items-center justify-between\">\n                    <flux:heading size=\"sm\">Barang</flux:heading>\n                    <flux:button type=\"button\" wire:click=\"addItem\" variant=\"ghost\" icon=\"plus\" size=\"sm\">Tambah Baris</flux:button>\n                </div>\n\n                @foreach (\$items as \$index => \$item)\n                    <div class=\"grid grid-cols-12 gap-2 items-end\" wire:key=\"item-{\$index}\">\n                        <div class=\"col-span-5\">\n                            <flux:select wire:model=\"items.{\$index}.product_id\" placeholder=\"Pilih barang\">\n                                @foreach (\$products as \$product)\n                                    <flux:select.option :value=\"\$product->id\">{!! \$product->name !!}</flux:select.option>\n                                @endforeach\n                            </flux:select>\n                        </div>\n                        <div class=\"col-span-3\">\n                            <flux:input wire:model=\"items.{\$index}.qty\" type=\"number\" min=\"1\" />\n                        </div>\n                        <div class=\"col-span-3\">\n                            <flux:input wire:model=\"items.{\$index}.price\" type=\"number\" step=\"0.01\" min=\"0\" />\n                        </div>\n                        <div class=\"col-span-1\">\n                            @if (count(\$items) > 1)\n                                <flux:button type=\"button\" wire:click=\"removeItem({$index})\" variant=\"ghost\" icon=\"trash\" size=\"sm\" />\n                            @endif\n                        </div>\n                    </div>\n                @endforeach\n            </div>\n\n            <div class=\"flex justify-end gap-3\">\n                <flux:button :href=\"route(\'transaksi.masuk\')\" wire:navigate variant=\"ghost\">Batal</flux:button>\n                <flux:button type=\"submit\" variant=\"primary\">Simpan</flux:button>\n            </div>\n        </form>\n    </flux:card>\n</div>\n```\n\n- [ ] **Step 4: Run test to verify it passes**\n```bash\nphp artisan test --filter=StockInTransactionTest\n```\nExpected: PASS\n\n- [ ] **Step 5: Commit**\n```bash\ngit add app/Livewire/Transaction/ resources/views/pages/transaction/\ngit commit -m \"feat: add StockIn Livewire components and views\"\n```\n\n---\n\n## Task 5: Stock Out Components\n\n**Files:**\n- Create: \`app/Livewire/Transaction/StockOutIndex.php\`\n- Create: \`app/Livewire/Transaction/StockOutCreate.php\`\n- Create: \`resources/views/pages/transaction/stock-out-index.blade.php\`\n- Create: \`resources/views/pages/transaction/stock-out-form.blade.php\`\n\n**Interfaces:**\n- Consumes: Transaction, TransactionItem, Product models (no supplier)\n- Produces: route(\'transaksi.keluar\'), route(\'transaksi.keluar.create\')\n\n- [ ] **Step 1: Write the failing test**
```php\n<?php\n// tests/Feature/Transaction/StockOutTransactionTest.php\nnamespace Tests\\Feature\\Transaction;\n\nuse App\\Livewire\\Transaction\\StockOutCreate;\nuse App\\Livewire\\Transaction\\StockOutIndex;\nuse App\\Models\\Product;\nuse App\\Models\\User;\nuse Illuminate\\Foundation\\Testing\\RefreshDatabase;\nuse Livewire\\Livewire;\nuse Tests\\TestCase;\n\nclass StockOutTransactionTest extends TestCase\n{\n    use RefreshDatabase;\n\n    protected function staff(): User\n    {\n        return User::factory()->create(['role' => 'staff']);\n    }\n\n    public function test_index_lists_transactions(): void\n    {\n        \$this->actingAs(\$this->staff())\n            ->get(route(\'transaksi.keluar\'))\n            ->assertOk();\n    }\n\n    public function test_create_transaction_decreases_stock(): void\n    {\n        \$product = Product::factory()->create(['stock' => 10]);\n\n        Livewire::actingAs(\$this->staff())\n            ->test(StockOutCreate::class)\n            ->set(\'date\', now()->format(\'Y-m-d\'))\n            ->set(\'items\', [[\'product_id\' => \$product->id, \'qty\' => 5, \'price\' => 100000]])\n            ->call(\'save\')\n            ->assertRedirect(route(\'transaksi.keluar\'));\n\n        \$product->refresh();\n        \$this->assertEquals(5, \$product->stock);\n    }\n\n    public function test_fails_when_stock_insufficient(): void\n    {\n        \$product = Product::factory()->create(['stock' => 3]);\n\n        Livewire::actingAs(\$this->staff())\n            ->test(StockOutCreate::class)\n            ->set(\'date\', now()->format(\'Y-m-d\'))\n            ->set(\'items\', [[\'product_id\' => \$product->id, \'qty\' => 5, \'price\' => 100000]])\n            ->call(\'save\')\n            ->assertSessionHasErrors([\'items.0.qty\']);\n    }\n}\n```\n\n- [ ] **Step 2: Run test to verify it fails**\n```bash\nphp artisan test --filter=StockOutTransactionTest\n```\nExpected: FAIL (components don't exist)\n\n- [ ] **Step 3: Write minimal implementation**\n\n**StockOutIndex.php:** (similar to StockInIndex with TYPE_KELUAR)\n**StockOutCreate.php:** (similar to StockInCreate but no supplier, validates stock)\n**stock-out-index.blade.php:** (similar structure to stock-in-index)\n**stock-out-form.blade.php:** (similar structure but without supplier select)\n\nKey differences:\n- No `supplier_id` field\n- Validation: check stock sufficient before save\n- Validation in rules: `items.*.qty` must be <= product stock\n\nAdd validation in `StockOutCreate::validateStock()`:\n```php\nprotected function validateStock(): void\n{\n    foreach (\$this->items as \$index => \$item) {\n        \$product = Product::find(\$item[\'product_id\'] ?? null);\n        if (\$product && \$item[\'qty\'] > \$product->stock) {\n            \$this->addError(\"items.{\$index}.qty\", \"Stok tidak cukup. Stok saat ini: {\$product->stock}\");\n        }\n    }\n}\n```\n\n- [ ] **Step 4: Run test to verify it passes**\n```bash\nphp artisan test --filter=StockOutTransactionTest\n```\nExpected: PASS\n\n- [ ] **Step 5: Commit**\n```bash\ngit add app/Livewire/Transaction/ resources/views/pages/transaction/\ngit commit -m \"feat: add StockOut Livewire components and views\"\n```\n\n---\n\n## Task 6: Retur Components\n\n**Files:**\n- Create: \`app/Livewire/Transaction/ReturIndex.php\`\n- Create: \`app/Livewire/Transaction/ReturCreate.php\`\n- Create: \`resources/views/pages/transaction/retur-index.blade.php\`\n- Create: \`resources/views/pages/transaction/retur-form.blade.php\`\n\n**Interfaces:**\n- Consumes: Transaction, TransactionItem, Product, Supplier models\n- Produces: route(\'transaksi.retur\'), route(\'transaksi.retur.create\')\n\n- [ ] **Step 1: Write the failing test**
```php\n<?php\n// tests/Feature/Transaction/ReturTransactionTest.php\nnamespace Tests\\Feature\\Transaction;\n\nuse App\\Livewire\\Transaction\\ReturCreate;\nuse App\\Livewire\\Transaction\\ReturIndex;\nuse App\\Models\\Product;\nuse App\\Models\\Supplier;\nuse App\\Models\\User;\nuse Illuminate\\Foundation\\Testing\\RefreshDatabase;\nuse Livewire\\Livewire;\nuse Tests\\TestCase;\n\nclass ReturTransactionTest extends TestCase\n{\n    use RefreshDatabase;\n\n    protected function staff(): User\n    {\n        return User::factory()->create(['role' => 'staff']);\n    }\n\n    public function test_index_lists_transactions(): void\n    {\n        \$this->actingAs(\$this->staff())\n            ->get(route(\'transaksi.retur\'))\n            ->assertOk();\n    }\n\n    public function test_retur_in_increases_stock(): void\n    {\n        \$product = Product::factory()->create(['stock' => 10]);\n        \$supplier = Supplier::factory()->create();\n\n        Livewire::actingAs(\$this->staff())\n            ->test(ReturCreate::class)\n            ->set(\'date\', now()->format(\'Y-m-d\'))\n            ->set(\'direction\', \'in\')\n            ->set(\'supplier_id\', \$supplier->id)\n            ->set(\'items\', [[\'product_id\' => \$product->id, \'qty\' => 5, \'price\' => 100000]])\n            ->call(\'save\')\n            ->assertRedirect(route(\'transaksi.retur\'));\n\n        \$product->refresh();\n        \$this->assertEquals(15, \$product->stock);\n    }\n\n    public function test_retur_out_decreases_stock(): void\n    {\n        \$product = Product::factory()->create(['stock' => 10]);\n        \$supplier = Supplier::factory()->create();\n\n        Livewire::actingAs(\$this->staff())\n            ->test(ReturCreate::class)\n            ->set(\'date\', now()->format(\'Y-m-d\'))\n            ->set(\'direction\', \'out\')\n            ->set(\'supplier_id\', \$supplier->id)\n            ->set(\'items\', [[\'product_id\' => \$product->id, \'qty\' => 5, \'price\' => 100000]])\n            ->call(\'save\')\n            ->assertRedirect(route(\'transaksi.retur\'));\n\n        \$product->refresh();\n        \$this->assertEquals(5, \$product->stock);\n    }\n\n    public function test_retur_out_fails_when_stock_insufficient(): void\n    {\n        \$product = Product::factory()->create(['stock' => 3]);\n        \$supplier = Supplier::factory()->create();\n\n        Livewire::actingAs(\$this->staff())\n            ->test(ReturCreate::class)\n            ->set(\'date\', now()->format(\'Y-m-d\'))\n            ->set(\'direction\', \'out\')\n            ->set(\'supplier_id\', \$supplier->id)\n            ->set(\'items\', [[\'product_id\' => \$product->id, \'qty\' => 5, \'price\' => 100000]])\n            ->call(\'save\')\n            ->assertSessionHasErrors([\'items.0.qty\']);\n    }\n}\n```\n\n- [ ] **Step 2: Run test to verify it fails**\n```bash\nphp artisan test --filter=ReturTransactionTest\n```\nExpected: FAIL (components don't exist)\n\n- [ ] **Step 3: Write minimal implementation**\n\n**ReturIndex.php:** (similar to StockInIndex with TYPE_RETUR)\n**ReturCreate.php:** (similar to StockInCreate but with direction dropdown)\n**retur-index.blade.php:** (similar structure, shows direction column)\n**retur-form.blade.php:** (similar structure with direction select, conditional supplier validation)\n\nKey differences:\n- `direction` field: dropdown \'in\'/\'out\'\n- Supplier: required when direction=\'out\', optional when direction=\'in\'\n- Stock validation: only when direction=\'out\'\n\n- [ ] **Step 4: Run test to verify it passes**\n```bash\nphp artisan test --filter=ReturTransactionTest\n```\nExpected: PASS\n\n- [ ] **Step 5: Commit**\n```bash\ngit add app/Livewire/Transaction/ resources/views/pages/transaction/\ngit commit -m \"feat: add Retur Livewire components and views\"\n```\n\n---\n\n## Task 7: Unit Tests\n\n**Files:**\n- Create: `tests/Unit/TransactionTest.php`\n\n**Interfaces:**\n- Consumes: Transaction, TransactionItem models\n- Produces: delta calculation tests\n\n- [ ] **Step 1: Write the failing test**\n```php\n<?php\n// tests/Unit/TransactionTest.php\nnamespace Tests\\Unit;\n\nuse App\\Models\\Transaction;\nuse App\\Models\\TransactionItem;\nuse Tests\\TestCase;\n\nclass TransactionTest extends TestCase\n{\n    public function test_delta_calculation_stock_in(): void\n    {\n        \$transaction = new Transaction(['type' => Transaction::TYPE_MASUK]);\n        \$item = new TransactionItem(['qty' => 5]);\n        \$item->setRelation('transaction', \$transaction);\n\n        \$this->assertEquals(5, \$item->getDelta());\n    }\n\n    public function test_delta_calculation_stock_out(): void\n    {\n        \$transaction = new Transaction(['type' => Transaction::TYPE_KELUAR]);\n        \$item = new TransactionItem(['qty' => 5]);\n        \$item->setRelation('transaction', \$transaction);\n\n        \$this->assertEquals(-5, \$item->getDelta());\n    }\n\n    public function test_delta_calculation_retur_in(): void\n    {\n        \$transaction = new Transaction(['type' => Transaction::TYPE_RETUR, 'direction' => 'in']);\n        \$item = new TransactionItem(['qty' => 5]);\n        \$item->setRelation('transaction', \$transaction);\n\n        \$this->assertEquals(5, \$item->getDelta());\n    }\n\n    public function test_delta_calculation_retur_out(): void\n    {\n        \$transaction = new Transaction(['type' => Transaction::TYPE_RETUR, 'direction' => 'out']);\n        \$item = new TransactionItem(['qty' => 5]);\n        \$item->setRelation('transaction', \$transaction);\n\n        \$this->assertEquals(-5, \$item->getDelta());\n    }\n}\n```\n\n- [ ] **Step 2: Run test to verify it fails**\n```bash\nphp artisan test --filter=TransactionTest\n```\nExpected: FAIL (delta calculation not working)\n\n- [ ] **Step 3: Implement**\nEnsure TransactionItem \`getDelta()\` method correctly calculates based on type and direction.\n\n- [ ] **Step 4: Run test to verify it passes**\n```bash\nphp artisan test --filter=TransactionTest\n```\nExpected: PASS\n\n- [ ] **Step 5: Commit**\n```bash\ngit add tests/Unit/TransactionTest.php\ngit commit -m \"test: add unit tests for TransactionItem delta calculation\"\n```\n\n---\n\n## Task 8: Run Full Test Suite\n\n**Files:** None (verification only)\n\n- [ ] **Step 1: Run all tests**\n```bash\nphp artisan test\n```\nExpected: ALL PASS\n\n- [ ] **Step 2: Run lint**\n```bash\ncomposer lint\n```\nExpected: PASS\n\n- [ ] **Step 3: Run type check**\n```bash\ncomposer types:check\n```\nExpected: PASS\n\n- [ ] **Step 4: Final commit if any fixes needed**\n```bash\ngit add .\ngit commit -m \"fix: resolve any linting or test issues\"\n```\n\n---\n\n## Execution Handoff\n\n**Plan complete and saved to \`docs/superpowers/plans/2025-08-05-transactions-implementation.md\`. Two execution options:**\n\n**1. Subagent-Driven (recommended)** - I dispatch a fresh subagent per task, review between tasks, fast iteration\n\n**2. Inline Execution** - Execute tasks in this session using executing-plans, batch execution with checkpoints\n\n**Which approach?**."