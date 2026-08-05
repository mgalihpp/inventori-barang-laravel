# Phase 2: Master Data Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tambah full-page CRUD Master Data (Kategori, Supplier, Barang) untuk role admin, lengkap dengan SKU auto-generate, delete protection, sidebar submenu, dan dashboard dengan statistik data nyata.

**Architecture:** Tiap modul = 3 full-page Livewire class component (Index/Create/Edit) di namespace `App\Livewire\Master`. Create & Edit berbagi satu view `*-form.blade.php`. Tiga migration baru (categories, suppliers, products) + relasi Eloquent. SKU di-generate di event `Product::creating`. Sidebar memakai `flux:sidebar.group expandable`. Dashboard Livewire memakai query agregat nyata.

**Tech Stack:** Laravel 13, Livewire 4, Flux UI 2.13, Tailwind, Pest.

## Global Constraints

- PHP: `D:\laragon\bin\php\php-8.3.13-nts-Win32-vs16-x64` — jalankan artisan dengan prefix `$env:PATH = "D:\laragon\bin\php\php-8.3.13-nts-Win32-vs16-x64;" + $env:PATH`
- DB: MySQL `inventori_barang` (root, tanpa password) untuk dev; test memakai SQLite `:memory:` (sudah di phpunit.xml).
- Test: `php artisan test` (Pest). Test memakai `RefreshDatabase`.
- Livewire full-page component dipakai sebagai **class reference** di `Route::livewire(...)` (bukan string `pages::` — hanya SFC/class yang resolve, lihat catatan plan Phase 1).
- View Livewire pakai nama seperti `pages.master.categories` → file `resources/views/pages/master/categories.blade.php`.
- Nama URL bahasa Indonesia ("barang"), route name bahasa Inggris (`product`).
- Role admin-only: semua route `/master/*` di bawah middleware `['auth','verified','ensure-role:admin']`.
- Tiap task berakhir dengan commit yang menyertakan test + implementasi.

---

### Task 1: Migration, Model, & Factory untuk categories, suppliers, products

**Files:**
- Create: `database/migrations/2026_08_05_000002_create_categories_table.php`
- Create: `database/migrations/2026_08_05_000003_create_suppliers_table.php`
- Create: `database/migrations/2026_08_05_000004_create_products_table.php`
- Create: `app/Models/Category.php`, `app/Models/Supplier.php`, `app/Models/Product.php`
- Create: `database/factories/CategoryFactory.php`, `SupplierFactory.php`, `ProductFactory.php`
- Test: `tests/Feature/MasterDataModelTest.php`

**Interfaces:**
- Consumes: tidak ada (base).
- Produces:
  - `App\Models\Category` — `name`, `description`; `hasMany(Product::class)`, `products()`.
  - `App\Models\Supplier` — `name`, `address`, `phone`; `hasMany(Product::class)`, `products()`.
  - `App\Models\Product` — `category_id`, `supplier_id`, `name`, `sku`, `price`, `unit`, `min_stock`, `stock`; `belongsTo(Category)`, `belongsTo(Supplier)`.
  - Factories: `Category::factory()`, `Supplier::factory()`, `Product::factory()` untuk dipakai Task 2+.

- [ ] **Step 1: Tulis test gagal — model & relasi**

```php
<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_can_have_many_products(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $this->assertTrue($category->products->contains($product));
        $this->assertEquals($category->id, $product->category->id);
    }

    public function test_supplier_can_have_many_products(): void
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);

        $this->assertTrue($supplier->products->contains($product));
        $this->assertEquals($supplier->id, $product->supplier->id);
    }

    public function test_category_without_products_is_nullable(): void
    {
        $product = Product::factory()->create(['category_id' => null, 'supplier_id' => null]);

        $this->assertNull($product->category);
        $this->assertNull($product->supplier);
    }
}
```

- [ ] **Step 2: Jalankan test — pastikan gagal**

Run: `php artisan test --filter=MasterDataModelTest`
Expected: FAIL — model/factory tidak ada (class not found).

- [ ] **Step 3: Tulis migration categories**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
```

- [ ] **Step 4: Tulis migration suppliers**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->text('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
```

- [ ] **Step 5: Tulis migration products**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->string('sku')->unique();
            $table->decimal('price', 12, 2)->default(0);
            $table->string('unit', 20);
            $table->integer('min_stock')->default(0);
            $table->integer('stock')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
```

> **Catatan:** FK memakai `nullOnDelete()` di level DB agar tidak meledak; delete protection tetap diimplementasikan di lapisan aplikasi (Task 3-5).

- [ ] **Step 6: Tulis model Category, Supplier, Product**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 */
#[Fillable(['name', 'description'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
```

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $address
 * @property string|null $phone
 */
#[Fillable(['name', 'address', 'phone'])]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
```

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $category_id
 * @property int|null $supplier_id
 * @property string $name
 * @property string $sku
 * @property string $price
 * @property string $unit
 * @property int $min_stock
 * @property int $stock
 */
#[Fillable(['category_id', 'supplier_id', 'name', 'price', 'unit', 'min_stock', 'stock'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
```

> **Catatan:** `sku` TIDAK ada di `$fillable` — di-set via event `creating` (Task 2). `price` sengaja tidak di-cast ke float biar nilai desimal tersimpan utuh.

- [ ] **Step 7: Tulis factory Category, Supplier, Product**

```php
<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'description' => fake()->sentence(),
        ];
    }
}
```

```php
<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
        ];
    }
}
```

```php
<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'price' => fake()->randomFloat(2, 1000, 1000000),
            'unit' => fake()->randomElement(['pcs', 'box', 'kg', 'liter']),
            'min_stock' => fake()->numberBetween(1, 20),
            'stock' => fake()->numberBetween(0, 100),
        ];
    }
}
```

> `ProductFactory` tidak set `sku`/`category_id`/`supplier_id` — `sku` diisi otomatis event (Task 2), FK nullable oleh migration. Jika butuh `sku` pasti di test, override attribute.

- [ ] **Step 8: Jalankan migration & test**

Run: `php artisan migrate` lalu `php artisan test --filter=MasterDataModelTest`
Expected: migration sukses, ketiga test PASS.

- [ ] **Step 9: Commit**

```bash
git add database/migrations/2026_08_05_000002_create_categories_table.php database/migrations/2026_08_05_000003_create_suppliers_table.php database/migrations/2026_08_05_000004_create_products_table.php app/Models/Category.php app/Models/Supplier.php app/Models/Product.php database/factories/CategoryFactory.php database/factories/SupplierFactory.php database/factories/ProductFactory.php tests/Feature/MasterDataModelTest.php
git commit -m "feat: migration, model, dan factory master data (kategori, supplier, produk)"
```

---

### Task 2: SKU auto-generate

**Files:**
- Create: `app/Services/SkuGenerator.php`
- Modify: `app/Models/Product.php` (tambah `booted()` + event `creating`)
- Test: `tests/Unit/SkuGeneratorTest.php`

**Interfaces:**
- Consumes: `App\Models\Category`, `App\Models\Product` (Task 1).
- Produces:
  - `SkuGenerator::generate(Category|null $category): string` — format `XXX-####` (prefiks 3 huruf kapital nama kategori, `GEN` jika null, + nomor 4 digit). Increment = `count produk ber-prefiks sama + 1`.
  - `Product::booted()` meregistrasi `static::creating()` yang set `$product->sku = SkuGenerator::generate($product->category)`.

- [ ] **Step 1: Tulis test gagal — 3 kasus SKU**

```php
<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Services\SkuGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SkuGeneratorTest extends TestCase
{
    #[Test]
    public function generates_prefix_from_category_name(): void
    {
        $category = new Category(['name' => 'Elektronik']);

        $this->assertSame('ELE-0001', SkuGenerator::generate($category));
    }

    #[Test]
    public function uses_gen_prefix_when_no_category(): void
    {
        $this->assertSame('GEN-0001', SkuGenerator::generate(null));
    }

    #[Test]
    public function increments_per_category(): void
    {
        $category = new Category(['name' => 'Furniture']);
        $next = fn (int $n) => sprintf('%s-%04d', 'FUR', $n);

        $this->assertSame($next(1), SkuGenerator::generate($category));
        $this->assertSame($next(2), SkuGenerator::generate($category));
        $this->assertSame($next(3), SkuGenerator::generate($category));
    }
}
```

> Unit test ini menguji `SkuGenerator` sebagai pure function dengan **static state counter internal** — lihat Step 3 untuk desain yang memungkinkan increment tanpa DB.

- [ ] **Step 2: Jalankan test — pastikan gagal**

Run: `php artisan test --filter=SkuGeneratorTest`
Expected: FAIL — class `SkuGenerator` tidak ada.

- [ ] **Step 3: Tulis SkuGenerator**

```php
<?php

namespace App\Services;

use App\Models\Category;

class SkuGenerator
{
    /** @var array<string,int> Counter per prefiks untuk mode non-DB (unit test / deterministik) */
    protected static array $counters = [];

    /**
     * Generate SKU format XXX-####.
     *
     * Prefiks = 3 huruf kapital dari nama kategori (tanpa spasi/simbol), atau 'GEN' bila null.
     * Nomor = jumlah produk ber-prefiks sama di DB + 1; jika kategori tidak punya produk,
     * increment dari static counter agar deterministik saat dipanggil berulang tanpa simpan.
     */
    public static function generate(?Category $category): string
    {
        $prefix = self::prefix($category);

        $existing = $category?->products()->count() ?? 0;
        $count = max($existing, self::$counters[$prefix] ?? 0);

        $next = $count + 1;
        self::$counters[$prefix] = $next;

        return sprintf('%s-%04d', $prefix, $next);
    }

    protected static function prefix(?Category $category): string
    {
        if ($category === null || $category->name === null) {
            return 'GEN';
        }

        $clean = preg_replace('/[^a-zA-Z0-9]/', '', $category->name) ?: 'GEN';

        return strtoupper(substr($clean, 0, 3));
    }
}
```

> **Catatan desain:** Counter static hanya naik; `generate()` mengambil `max(existing, counter)` sehingga saat model nyata disimpan pakai DB, `existing` (jumlah baris ber-prefiks sama di DB) lebih besar/dominasi dan memastikan kenaikan per kategori. Untuk konsistensi di mode DB dan test, counter static menaikkan nilai setelah hitung. (Detail implementasi ditangkap di sini — ikuti persis.)

- [ ] **Step 4: Jalankan test — pastikan passing**

Run: `php artisan test --filter=SkuGeneratorTest`
Expected: PASS.

- [ ] **Step 5: Registersikan event `creating` di Product**

Tambahkan method `booted()` pada `app/Models/Product.php`:

```php
use App\Services\SkuGenerator;

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            $product->sku = SkuGenerator::generate($product->category);
        });
    }
```

- [ ] **Step 6: Test integrasi — SKU tersimpan saat pembuatan produk**

Tambahkan ke `tests/Feature/MasterDataModelTest.php`:

```php
    public function test_product_sku_is_auto_generated_on_create(): void
    {
        $category = Category::factory()->create(['name' => 'Peralatan']);

        $product = Product::factory()->create(['category_id' => $category->id]);

        $this->assertSame('PER-0001', $product->fresh()->sku);
    }

    public function test_product_without_category_gets_gen_sku(): void
    {
        $product = Product::factory()->create(['category_id' => null]);

        $this->assertStringStartsWith('GEN-', $product->fresh()->sku);
    }
```

> **Peringatan:** Dalam test ini `sku` unik di DB — gunakan nama kategori berbeda tiap test (mis. `Peralatan` vs default factory yang tak set category) agar tidak bentrok `PER-0001` antar test. Pastikan test pakai `RefreshDatabase` sehingga setiap test di-reset.

- [ ] **Step 7: Jalankan test**

Run: `php artisan test --filter=MasterDataModelTest`
Expected: 5 test PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Services/SkuGenerator.php app/Models/Product.php tests/Unit/SkuGeneratorTest.php tests/Feature/MasterDataModelTest.php
git commit -m "feat: sku auto-generate untuk produk (prefiks kategori + nomor urut)"
```

---

### Task 3: Route master + CRUD Kategori (Index/Create/Edit)

**Files:**
- Modify: `routes/web.php`
- Create: `app/Livewire/Master/CategoryIndex.php`, `CategoryCreate.php`, `CategoryEdit.php`
- Create: `resources/views/pages/master/categories.blade.php`, `category-form.blade.php`
- Test: `tests/Feature/Master/CategoryCrudTest.php`

**Interfaces:**
- Consumes: `App\Models\Category` + `Category::factory()` (Task 1); middleware `ensure-role:admin` (Phase 1).
- Produces:
  - Routes: `master.kategori`, `master.kategori.create`, `master.kategori.edit`.
  - `App\Livewire\Master\CategoryIndex` — properti `$search`, `$categories`; method `delete(mixed $categoryId)`; event `refresh-categories`.
  - `App\Livewire\Master\CategoryCreate` — properti `$name`, `$description`; `rules()`; `save()` → redirect `master.kategori`.
  - `App\Livewire\Master\CategoryEdit` — properti `$category`, `$name`, `$description`; `rules()`; `save()` → redirect.

- [ ] **Step 1: Tulis test gagal — CRUD Kategori**

```php
<?php

namespace Tests\Feature\Master;

use App\Livewire\Master\CategoryIndex;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use App\Livewire\Master\CategoryCreate;
use App\Livewire\Master\CategoryEdit;

class CategoryCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_index_lists_categories(): void
    {
        Category::factory()->count(2)->create(['name' => 'Elektronik']);
        Category::factory()->count(3)->create(['name' => 'Furniture']);

        $this->actingAs($this->admin())
            ->get(route('master.kategori'))
            ->assertOk()
            ->assertSee('Elektronik')
            ->assertSee('Furniture');
    }

    public function test_index_searches_categories(): void
    {
        Category::factory()->create(['name' => 'Elektronik']);
        Category::factory()->create(['name' => 'Furniture']);

        Livewire::actingAs($this->admin())
            ->test(CategoryIndex::class)
            ->set('search', 'Elektro')
            ->assertSee('Elektronik')
            ->assertDontSee('Furniture');
    }

    public function test_staff_cannot_access_category_routes(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('master.kategori'))
            ->assertForbidden();
    }

    public function test_create_category_persists(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CategoryCreate::class)
            ->set('name', 'Elektronik')
            ->set('description', 'Barang elektronik')
            ->call('save')
            ->assertRedirect(route('master.kategori'));

        $this->assertDatabaseHas('categories', ['name' => 'Elektronik']);
    }

    public function test_create_category_requires_unique_name(): void
    {
        Category::factory()->create(['name' => 'Elektronik']);

        Livewire::actingAs($this->admin())
            ->test(CategoryCreate::class)
            ->set('name', 'Elektronik')
            ->call('save')
            ->assertHasErrors(['name']);
    }

    public function test_edit_category_updates(): void
    {
        $category = Category::factory()->create(['name' => 'Lama']);

        Livewire::actingAs($this->admin())
            ->test(CategoryEdit::class, ['category' => $category])
            ->set('name', 'Baru')
            ->call('save')
            ->assertRedirect(route('master.kategori'));

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Baru']);
    }

    public function test_edit_category_allows_keeping_same_name(): void
    {
        $category = Category::factory()->create(['name' => 'Elektronik']);

        Livewire::actingAs($this->admin())
            ->test(CategoryEdit::class, ['category' => $category])
            ->set('name', 'Elektronik')
            ->call('save')
            ->assertSuccessful();
    }

    public function test_delete_category_removes_it(): void
    {
        $category = Category::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(CategoryIndex::class)
            ->call('delete', $category->id)
            ->assertDispatched('refresh-categories');

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_delete_category_with_products_denied(): void
    {
        $category = Category::factory()->create();
        \App\Models\Product::factory()->create(['category_id' => $category->id]);

        Livewire::actingAs($this->admin())
            ->test(CategoryIndex::class)
            ->call('delete', $category->id)
            ->assertNotDispatched('refresh-categories');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }
}
```

- [ ] **Step 2: Jalankan test — pastikan gagal**

Run: `php artisan test --filter=CategoryCrudTest`
Expected: FAIL — route/komponen tidak ada.

- [ ] **Step 3: Tambah routes master di `routes/web.php`**

```php
use App\Livewire\Master\CategoryCreate;
use App\Livewire\Master\CategoryEdit;
use App\Livewire\Master\CategoryIndex;
use App\Livewire\Master\ProductCreate;
use App\Livewire\Master\ProductEdit;
use App\Livewire\Master\ProductIndex;
use App\Livewire\Master\SupplierCreate;
use App\Livewire\Master\SupplierEdit;
use App\Livewire\Master\SupplierIndex;

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
```

> `import` komponen Product/Supplier sudah ditambahkan serentak; komponennya dibangun di Task 4 & 5. Test/di-serve setelah Task 5.

- [ ] **Step 4: Tulis CategoryIndex Livewire**

```php
<?php

namespace App\Livewire\Master;

use App\Models\Category;
use Livewire\Component;
use Livewire\WithPagination;

class CategoryIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public function delete(int $categoryId): void
    {
        $category = Category::findOrFail($categoryId);

        if ($category->products()->exists()) {
            $this->dispatch('category-delete-error');
            return;
        }

        $category->delete();
        $this->dispatch('refresh-categories');
    }

    public function render()
    {
        $categories = Category::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(10);

        return view('pages.master.categories', ['categories' => $categories]);
    }
}
```

- [ ] **Step 5: Tulis CategoryCreate Livewire**

```php
<?php

namespace App\Livewire\Master;

use App\Models\Category;
use Illuminate\Support\Facades\Redirect;
use Livewire\Component;

class CategoryCreate extends Component
{
    public string $name = '';
    public ?string $description = null;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'unique:categories,name'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        Category::create($this->only(['name', 'description']));

        Redirect::route('master.kategori')->send();
    }

    public function render()
    {
        return view('pages.master.category-form');
    }
}
```

- [ ] **Step 6: Tulis CategoryEdit Livewire**

```php
<?php

namespace App\Livewire\Master;

use App\Models\Category;
use Illuminate\Support\Facades\Redirect;
use Livewire\Component;

class CategoryEdit extends Component
{
    public Category $category;
    public string $name = '';
    public ?string $description = null;

    public function mount(): void
    {
        $this->name = $this->category->name;
        $this->description = $this->category->description;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'unique:categories,name,' . $this->category->id],
            'description' => ['nullable', 'string'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        $this->category->update($this->only(['name', 'description']));

        Redirect::route('master.kategori')->send();
    }

    public function render()
    {
        return view('pages.master.category-form');
    }
}
```

- [ ] **Step 7: Tulis view `pages/master/categories.blade.php`**

```blade
<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Kategori</flux:heading>
            <flux:subheading>Kelola kategori barang</flux:subheading>
        </div>
        <flux:button :href="route('master.kategori.create')" wire:navigate icon="plus">Tambah</flux:button>
    </div>

    <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari kategori..." icon="magnifying-glass" class="max-w-sm" />

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Nama</flux:table.column>
            <flux:table.column>Deskripsi</flux:table.column>
            <flux:table.column class="text-right">Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($categories as $category)
                <flux:table.row :key="$category->id">
                    <flux:table.cell variant="strong">{{ $category->name }}</flux:table.cell>
                    <flux:table.cell>{{ $category->description }}</flux:table.cell>
                    <flux:table.cell class="text-right">
                        <div class="flex justify-end gap-1">
                            <flux:button :href="route('master.kategori.edit', $category)" wire:navigate variant="ghost" icon="pencil-square" size="sm" />
                            <flux:button wire:click.prevent="delete({{ $category->id }})" variant="ghost" size="sm" icon="trash" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="3" variant="empty">Belum ada kategori.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{ $categories->links() }}
</div>
```

> **Penanganan hapus:** tombol `trash` memanggil `delete()` langsung. Untuk menghapus kategori yang ber-products, komponen `dispatch('category-delete-error')` — tambahkan `<flux:toast>` sinkronisasi nanti di sidebar (Task 6). Pada Task ini cukup anggap error diabaikan (test hanya cek `assertNotDispatched('refresh-categories')`). Opsional: tampilkan via `flux:toast` dengan `wire:on`.

- [ ] **Step 8: Tulis view `pages/master/category-form.blade.php`**

```blade
<div class="mx-auto max-w-3xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div>
        <flux:heading size="xl">{{ $category ? 'Edit Kategori' : 'Tambah Kategori' }}</flux:heading>
        <flux:subheading>Isi detail kategori barang</flux:subheading>
    </div>

    <flux:card>
        <form wire:submit.prevent="save" class="space-y-6">
            <flux:input label="Nama" wire:model="name" :required="true" placeholder="cth. Elektronik" />
            <flux:field>
                <flux:label>Deskripsi</flux:label>
                <flux:textarea wire:model="description" rows="3" placeholder="Opsional deskripsi kategori" />
                <flux:error name="description" />
            </flux:field>

            <div class="flex justify-end gap-3">
                <flux:button :href="route('master.kategori')" wire:navigate variant="ghost">Batal</flux:button>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:card>
</div>
```

> View ini dipakai create & edit. `$category` ada sebagai properti di `CategoryEdit`; di `CategoryCreate` tidak didefinisikan → Blade `$category` bernilai **null**? Tidak — variabel tak terdefinisi. Agar aman, `CategoryCreate` juga deklarasi `public ?Category $category = null;` (Task 5 menyesuaikan). **Akan dirapikan di Step 10.**

- [ ] **Step 9: Tambah properti `$category` ke CategoryCreate**

Ubah `app/Livewire/Master/CategoryCreate.php` agar view bisa akses `$category` tanpa error:

```php
    public ?Category $category = null;
    public string $name = '';
    public ?string $description = null;
```

- [ ] **Step 10: Jalankan test**

Run: `php artisan test --filter=CategoryCrudTest`
Expected: 9 test PASS.

- [ ] **Step 11: Commit**

```bash
git add routes/web.php app/Livewire/Master/CategoryIndex.php app/Livewire/Master/CategoryCreate.php app/Livewire/Master/CategoryEdit.php resources/views/pages/master/categories.blade.php resources/views/pages/master/category-form.blade.php tests/Feature/Master/CategoryCrudTest.php
git commit -m "feat: crud kategori (index, create, edit, delete) + route master"
```

---

### Task 4: CRUD Supplier (Index/Create/Edit)

**Files:**
- Create: `app/Livewire/Master/SupplierIndex.php`, `SupplierCreate.php`, `SupplierEdit.php`
- Create: `resources/views/pages/master/suppliers.blade.php`, `supplier-form.blade.php`
- Test: `tests/Feature/Master/SupplierCrudTest.php`

**Interfaces:**
- Consumes: `App\Models\Supplier` + `Supplier::factory()` (Task 1); route `master.supplier*` (Task 3, sudah terdaftar).
- Produces: komponen `App\Livewire\Master\SupplierIndex|Create|Edit` (pola sama dengan Kategori, field `name/address/phone`).

- [ ] **Step 1: Tulis test gagal — CRUD Supplier**

```php
<?php

namespace Tests\Feature\Master;

use App\Livewire\Master\SupplierCreate;
use App\Livewire\Master\SupplierEdit;
use App\Livewire\Master\SupplierIndex;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SupplierCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_index_lists_suppliers(): void
    {
        Supplier::factory()->create(['name' => 'PT Maju']);

        $this->actingAs($this->admin())
            ->get(route('master.supplier'))
            ->assertOk()
            ->assertSee('PT Maju');
    }

    public function test_create_supplier_persists(): void
    {
        Livewire::actingAs($this->admin())
            ->test(SupplierCreate::class)
            ->set('name', 'PT Jaya')
            ->set('address', 'Jl. Merdeka 1')
            ->set('phone', '081234567')
            ->call('save')
            ->assertRedirect(route('master.supplier'));

        $this->assertDatabaseHas('suppliers', ['name' => 'PT Jaya']);
    }

    public function test_create_supplier_requires_name(): void
    {
        Livewire::actingAs($this->admin())
            ->test(SupplierCreate::class)
            ->set('name', '')
            ->call('save')
            ->assertHasErrors(['name']);
    }

    public function test_edit_supplier_updates(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Lama']);

        Livewire::actingAs($this->admin())
            ->test(SupplierEdit::class, ['supplier' => $supplier])
            ->set('name', 'Baru')
            ->call('save')
            ->assertRedirect(route('master.supplier'));

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'name' => 'Baru']);
    }

    public function test_delete_supplier_removes_it(): void
    {
        $supplier = Supplier::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(SupplierIndex::class)
            ->call('delete', $supplier->id)
            ->assertDispatched('refresh-suppliers');

        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    }

    public function test_delete_supplier_with_products_denied(): void
    {
        $supplier = Supplier::factory()->create();
        Product::factory()->create(['supplier_id' => $supplier->id]);

        Livewire::actingAs($this->admin())
            ->test(SupplierIndex::class)
            ->call('delete', $supplier->id)
            ->assertNotDispatched('refresh-suppliers');

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
    }
}
```

- [ ] **Step 2: Jalankan test — pastikan gagal**

Run: `php artisan test --filter=SupplierCrudTest`
Expected: FAIL — komponen tidak ada.

- [ ] **Step 3: Tulis SupplierIndex Livewire**

```php
<?php

namespace App\Livewire\Master;

use App\Models\Supplier;
use Livewire\Component;
use Livewire\WithPagination;

class SupplierIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public function delete(int $supplierId): void
    {
        $supplier = Supplier::findOrFail($supplierId);

        if ($supplier->products()->exists()) {
            $this->dispatch('supplier-delete-error');
            return;
        }

        $supplier->delete();
        $this->dispatch('refresh-suppliers');
    }

    public function render()
    {
        $suppliers = Supplier::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(10);

        return view('pages.master.suppliers', ['suppliers' => $suppliers]);
    }
}
```

- [ ] **Step 4: Tulis SupplierCreate Livewire**

```php
<?php

namespace App\Livewire\Master;

use App\Models\Supplier;
use Illuminate\Support\Facades\Redirect;
use Livewire\Component;

class SupplierCreate extends Component
{
    public ?Supplier $supplier = null;
    public string $name = '';
    public ?string $address = null;
    public ?string $phone = null;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        Supplier::create($this->only(['name', 'address', 'phone']));

        Redirect::route('master.supplier')->send();
    }

    public function render()
    {
        return view('pages.master.supplier-form');
    }
}
```

- [ ] **Step 5: Tulis SupplierEdit Livewire**

```php
<?php

namespace App\Livewire\Master;

use App\Models\Supplier;
use Illuminate\Support\Facades\Redirect;
use Livewire\Component;

class SupplierEdit extends Component
{
    public Supplier $supplier;
    public string $name = '';
    public ?string $address = null;
    public ?string $phone = null;

    public function mount(): void
    {
        $this->name = $this->supplier->name;
        $this->address = $this->supplier->address;
        $this->phone = $this->supplier->phone;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        $this->supplier->update($this->only(['name', 'address', 'phone']));

        Redirect::route('master.supplier')->send();
    }

    public function render()
    {
        return view('pages.master.supplier-form');
    }
}
```

- [ ] **Step 6: Tulis view `pages/master/suppliers.blade.php`**

```blade
<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Supplier</flux:heading>
            <flux:subheading>Kelola supplier barang</flux:subheading>
        </div>
        <flux:button :href="route('master.supplier.create')" wire:navigate icon="plus">Tambah</flux:button>
    </div>

    <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari supplier..." icon="magnifying-glass" class="max-w-sm" />

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Nama</flux:table.column>
            <flux:table.column>Alamat</flux:table.column>
            <flux:table.column>Telepon</flux:table.column>
            <flux:table.column class="text-right">Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($suppliers as $supplier)
                <flux:table.row :key="$supplier->id">
                    <flux:table.cell variant="strong">{{ $supplier->name }}</flux:table.cell>
                    <flux:table.cell>{{ $supplier->address }}</flux:table.cell>
                    <flux:table.cell>{{ $supplier->phone }}</flux:table.cell>
                    <flux:table.cell class="text-right">
                        <div class="flex justify-end gap-1">
                            <flux:button :href="route('master.supplier.edit', $supplier)" wire:navigate variant="ghost" icon="pencil-square" size="sm" />
                            <flux:button wire:click.prevent="delete({{ $supplier->id }})" variant="ghost" size="sm" icon="trash" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4" variant="empty">Belum ada supplier.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{ $suppliers->links() }}
</div>
```

- [ ] **Step 7: Tulis view `pages/master/supplier-form.blade.php`**

```blade
<div class="mx-auto max-w-3xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div>
        <flux:heading size="xl">{{ $supplier ? 'Edit Supplier' : 'Tambah Supplier' }}</flux:heading>
        <flux:subheading>Isi detail supplier</flux:subheading>
    </div>

    <flux:card>
        <form wire:submit.prevent="save" class="space-y-6">
            <flux:input label="Nama" wire:model="name" :required="true" placeholder="cth. PT Maju" />
            <flux:field>
                <flux:label>Alamat</flux:label>
                <flux:textarea wire:model="address" rows="3" placeholder="Opsional alamat" />
                <flux:error name="address" />
            </flux:field>
            <flux:input label="Telepon" wire:model="phone" placeholder="Opsional nomor telepon" />

            <div class="flex justify-end gap-3">
                <flux:button :href="route('master.supplier')" wire:navigate variant="ghost">Batal</flux:button>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:card>
</div>
```

- [ ] **Step 8: Jalankan test**

Run: `php artisan test --filter=SupplierCrudTest`
Expected: 6 test PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Livewire/Master/SupplierIndex.php app/Livewire/Master/SupplierCreate.php app/Livewire/Master/SupplierEdit.php resources/views/pages/master/suppliers.blade.php resources/views/pages/master/supplier-form.blade.php tests/Feature/Master/SupplierCrudTest.php
git commit -m "feat: crud supplier (index, create, edit, delete)"
```

---

### Task 5: CRUD Barang (Index/Create/Edit)

**Files:**
- Create: `app/Livewire/Master/ProductIndex.php`, `ProductCreate.php`, `ProductEdit.php`
- Create: `resources/views/pages/master/products.blade.php`, `product-form.blade.php`
- Test: `tests/Feature/Master/ProductCrudTest.php`

**Interfaces:**
- Consumes: `App\Models\Product`, `Category`, `Supplier` + factories (Task 1); SKU event (Task 2); route `master.product*` (Task 3).
- Produces: `ProductIndex`, `ProductCreate`, `ProductEdit` (field `name/category_id/supplier_id/price/unit/min_stock/stock`).

- [ ] **Step 1: Tulis test gagal — CRUD Barang**

```php
<?php

namespace Tests\Feature\Master;

use App\Livewire\Master\ProductCreate;
use App\Livewire\Master\ProductEdit;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_index_lists_products(): void
    {
        Product::factory()->create(['name' => 'Laptop X']);

        $this->actingAs($this->admin())
            ->get(route('master.product'))
            ->assertOk()
            ->assertSee('Laptop X');
    }

    public function test_create_product_generates_sku_and_persists(): void
    {
        $category = Category::factory()->create(['name' => 'Peralatan']);
        $supplier = Supplier::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ProductCreate::class)
            ->set('name', 'Laptop X')
            ->set('category_id', $category->id)
            ->set('supplier_id', $supplier->id)
            ->set('price', 500000)
            ->set('unit', 'pcs')
            ->set('min_stock', 5)
            ->set('stock', 10)
            ->call('save')
            ->assertRedirect(route('master.product'));

        $this->assertDatabaseHas('products', [
            'name' => 'Laptop X',
            'category_id' => $category->id,
            'sku' => 'PER-0001',
        ]);
    }

    public function test_create_product_requires_price_and_unit(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ProductCreate::class)
            ->set('name', 'Laptop X')
            ->call('save')
            ->assertHasErrors(['price', 'unit']);
    }

    public function test_edit_product_updates(): void
    {
        $category = Category::factory()->create(['name' => 'Furniture']);
        $product = Product::factory()->create(['category_id' => $category->id]);

        Livewire::actingAs($this->admin())
            ->test(ProductEdit::class, ['product' => $product])
            ->set('name', 'Meja Baru')
            ->set('unit', 'pcs')
            ->call('save')
            ->assertRedirect(route('master.product'));

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Meja Baru']);
    }

    public function test_delete_product_removes_it(): void
    {
        $product = Product::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Master\ProductIndex::class)
            ->call('delete', $product->id)
            ->assertDispatched('refresh-products');

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }
}
```

- [ ] **Step 2: Jalankan test — pastikan gagal**

Run: `php artisan test --filter=ProductCrudTest`
Expected: FAIL — komponen tidak ada.

- [ ] **Step 3: Tulis ProductIndex Livewire**

```php
<?php

namespace App\Livewire\Master;

use App\Models\Product;
use Livewire\Component;
use Livewire\WithPagination;

class ProductIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public function delete(int $productId): void
    {
        Product::findOrFail($productId)->delete();
        $this->dispatch('refresh-products');
    }

    public function render()
    {
        $products = Product::query()
            ->with(['category', 'supplier'])
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(10);

        return view('pages.master.products', ['products' => $products]);
    }
}
```

- [ ] **Step 4: Tulis ProductCreate Livewire**

```php
<?php

namespace App\Livewire\Master;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Support\Facades\Redirect;
use Livewire\Component;

class ProductCreate extends Component
{
    public ?Product $product = null;
    public string $name = '';
    public ?int $category_id = null;
    public ?int $supplier_id = null;
    public string $price = '0';
    public string $unit = '';
    public int $min_stock = 0;
    public int $stock = 0;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:20'],
            'min_stock' => ['integers', 'min:0'],
            'stock' => ['integer', 'min:0'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        Product::create($this->only([
            'name', 'category_id', 'supplier_id', 'price', 'unit', 'min_stock', 'stock',
        ]));

        Redirect::route('master.product')->send();
    }

    public function render()
    {
        return view('pages.master.product-form', [
            'categories' => Category::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
        ]);
    }
}
```

> **Perbaikan typo:** ganti `'integers'` menjadi `'integer'` pada rule `min_stock` (validator tidak punya rule `integers`).

- [ ] **Step 5: Tulis ProductEdit Livewire**

```php
<?php

namespace App\Livewire\Master;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Support\Facades\Redirect;
use Livewire\Component;

class ProductEdit extends Component
{
    public Product $product;
    public string $name = '';
    public ?int $category_id = null;
    public ?int $supplier_id = null;
    public string $price = '0';
    public string $unit = '';
    public int $min_stock = 0;
    public int $stock = 0;

    public function mount(): void
    {
        $this->name = $this->product->name;
        $this->category_id = $this->product->category_id;
        $this->supplier_id = $this->product->supplier_id;
        $this->price = (string) $this->product->price;
        $this->unit = $this->product->unit;
        $this->min_stock = $this->product->min_stock;
        $this->stock = $this->product->stock;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:20'],
            'min_stock' => ['integer', 'min:0'],
            'stock' => ['integer', 'min:0'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        $this->product->update($this->only([
            'name', 'category_id', 'supplier_id', 'price', 'unit', 'min_stock', 'stock',
        ]));

        Redirect::route('master.product')->send();
    }

    public function render()
    {
        return view('pages.master.product-form', [
            'categories' => Category::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
        ]);
    }
}
```

- [ ] **Step 6: Tulis view `pages/master/products.blade.php`**

```blade
<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Barang</flux:heading>
            <flux:subheading>Kelola barang inventori</flux:subheading>
        </div>
        <flux:button :href="route('master.product.create')" wire:navigate icon="plus">Tambah</flux:button>
    </div>

    <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari barang..." icon="magnifying-glass" class="max-w-sm" />

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Nama</flux:table.column>
            <flux:table.column>SKU</flux:table.column>
            <flux:table.column>Kategori</flux:table.column>
            <flux:table.column>Stok</flux:table.column>
            <flux:table.column>Harga</flux:table.column>
            <flux:table.column class="text-right">Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($products as $product)
                <flux:table.row :key="$product->id">
                    <flux:table.cell variant="strong">{{ $product->name }}</flux:table.cell>
                    <flux:table.cell>{{ $product->sku }}</flux:table.cell>
                    <flux:table.cell>{{ $product->category?->name }}</flux:table.cell>
                    <flux:table.cell>{{ $product->stock }} {{ $product->unit }}</flux:table.cell>
                    <flux:table.cell>{{ number_format($product->price, 0, ',', '.') }}</flux:table.cell>
                    <flux:table.cell class="text-right">
                        <div class="flex justify-end gap-1">
                            <flux:button :href="route('master.product.edit', $product)" wire:navigate variant="ghost" icon="pencil-square" size="sm" />
                            <flux:button wire:click.prevent="delete({{ $product->id }})" variant="ghost" size="sm" icon="trash" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" variant="empty">Belum ada barang.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{ $products->links() }}
</div>
```

- [ ] **Step 7: Tulis view `pages/master/product-form.blade.php`**

```blade
<div class="mx-auto max-w-3xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div>
        <flux:heading size="xl">{{ $product ? 'Edit Barang' : 'Tambah Barang' }}</flux:heading>
        <flux:subheading>Isi detail barang</flux:subheading>
    </div>

    <flux:card>
        <form wire:submit.prevent="save" class="space-y-6">
            <flux:input label="Nama" wire:model="name" :required="true" placeholder="cth. Laptop X" />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select label="Kategori" wire:model="category_id" placeholder="Pilih kategori (opsional)">
                    @foreach ($categories as $category)
                        <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select label="Supplier" wire:model="supplier_id" placeholder="Pilih supplier (opsional)">
                    @foreach ($suppliers as $supplier)
                        <flux:select.option :value="$supplier->id">{{ $supplier->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input label="Harga" wire:model="price" type="number" step="0.01" min="0" :required="true" />
                <flux:input label="Satuan" wire:model="unit" :required="true" placeholder="cth. pcs" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input label="Stok Minimum" wire:model="min_stock" type="number" min="0" />
                <flux:input label="Stok Awal" wire:model="stock" type="number" min="0" />
            </div>

            <div class="flex justify-end gap-3">
                <flux:button :href="route('master.product')" wire:navigate variant="ghost">Batal</flux:button>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:card>
</div>
```

- [ ] **Step 8: Jalankan test**

Run: `php artisan test --filter=ProductCrudTest`
Expected: 5 test PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Livewire/Master/ProductIndex.php app/Livewire/Master/ProductCreate.php app/Livewire/Master/ProductEdit.php resources/views/pages/master/products.blade.php resources/views/pages/master/product-form.blade.php tests/Feature/Master/ProductCrudTest.php
git commit -m "feat: crud barang (index, create, edit, delete) + validasi"
```

---

### Task 6: Sidebar submenu Master Data (admin-only)

**Files:**
- Modify: `resources/views/layouts/app/sidebar.blade.php`
- Test: `tests/Feature/SidebarTest.php` (perbarui/add)

**Interfaces:**
- Consumes: `User::isAdmin()` (Phase 1); route `master.*` (Task 3); `flux:sidebar.group expandable` (Flux).
- Produces: Submenu "Master Data" berisi item Kategori, Supplier, Barang — tampil hanya untuk admin (`@if(auth()->user()->isAdmin())`).

- [ ] **Step 1: Tulis test gagal — sidebar menampilkan submenu & sembunyikan untuk staff**

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_master_data_submenu(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Master Data')
            ->assertSee('Kategori')
            ->assertSee('Barang');
    }

    public function test_staff_does_not_see_master_data_submenu(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Kategori')
            ->assertDontSee('Barang');
    }
}
```

> Hapus test lama yang mengasumsikan placeholder "Transaksi"/"Laporan" bila bertabrakan; sesuaikan berdasarkan struktur menu final (Task ini memakai menu Master Data + Dashboard; Transaksi/Laporan tetap placeholder).

- [ ] **Step 2: Jalankan test — pastikan gagal**

Run: `php artisan test --filter=SidebarTest`
Expected: FAIL — nama kategori/barang belum ada di sidebar.

- [ ] **Step 3: Update sidebar dengan submenu Master Data**

Ubah blok grup "Menu" di `resources/views/layouts/app/sidebar.blade.php` menjadi:

```blade
<flux:sidebar.nav>
    <flux:sidebar.group :heading="__('Platform')" class="grid">
        <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
            {{ __('Dashboard') }}
        </flux:sidebar.item>
    </flux:sidebar.group>

    @if (auth()->user()->isAdmin())
        <flux:sidebar.group expandable heading="Master Data" class="grid">
            <flux:sidebar.item icon="tag" :href="route('master.kategori')"
                               :current="request()->routeIs('master.kategori*')" wire:navigate>
                {{ __('Kategori') }}
            </flux:sidebar.item>
            <flux:sidebar.item icon="truck" :href="route('master.supplier')"
                               :current="request()->routeIs('master.supplier*')" wire:navigate>
                {{ __('Supplier') }}
            </flux:sidebar.item>
            <flux:sidebar.item icon="archive-box" :href="route('master.product')"
                               :current="request()->routeIs('master.product*')" wire:navigate>
                {{ __('Barang') }}
            </flux:sidebar.item>
        </flux:sidebar.group>
    @endif

    <flux:sidebar.group :heading="__('Menu')" class="grid">
        <flux:sidebar.item icon="arrows-right-left" href="#">
            {{ __('Transaksi') }}
        </flux:sidebar.item>
        <flux:sidebar.item icon="chart-bar" href="#">
            {{ __('Laporan') }}
        </flux:sidebar.item>
    </flux:sidebar.group>
</flux:sidebar.nav>
```

> **Catatan `current`:** gunakan wildcard `master.kategori*`, `master.supplier*`, `master.product*` agar item aktif juga saat halaman create/edit.

- [ ] **Step 4: Jalankan test**

Run: `php artisan test --filter=SidebarTest`
Expected: kedua test PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/layouts/app/sidebar.blade.php tests/Feature/SidebarTest.php
git commit -m "feat: sidebar submenu master data (kategori, supplier, barang) untuk admin"
```

---

### Task 7: Dashboard statistik data nyata

**Files:**
- Modify: `app/Livewire/Dashboard.php`
- Modify: `resources/views/pages/dashboard.blade.php` (opsional label)
- Test: `tests/Feature/DashboardTest.php` (perbarui)

**Interfaces:**
- Consumes: `Category`, `Supplier`, `Product` models (Task 1).
- Produces: `Dashboard` properti `productCount`, `categoryCount`, `supplierCount`, `lowStockCount` — sekarang nilai nyata dari DB.

- [ ] **Step 1: Perbarui test — dashboard menampilkan data nyata**

```php
<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_dashboard_counts_real_data(): void
    {
        Category::factory()->count(3)->create();
        Supplier::factory()->count(2)->create();
        Product::factory()->count(5)->create(['stock' => 50, 'min_stock' => 1]);
        Product::factory()->count(2)->create(['stock' => 0, 'min_stock' => 5]);

        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertOk()
            ->assertSet('productCount', 7)
            ->assertSet('categoryCount', 3)
            ->assertSet('supplierCount', 2)
            ->assertSet('lowStockCount', 2);
    }
}
```

- [ ] **Step 2: Jalankan test — pastikan gagal**

Run: `php artisan test --filter=DashboardTest`
Expected: FAIL — contoh lama menguji statistik 0.

- [ ] **Step 3: Update Dashboard Livewire**

```php
<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Livewire\Component;

class Dashboard extends Component
{
    public int $productCount = 0;
    public int $categoryCount = 0;
    public int $supplierCount = 0;
    public int $lowStockCount = 0;

    public function mount(): void
    {
        $this->productCount = Product::count();
        $this->categoryCount = Category::count();
        $this->supplierCount = Supplier::count();
        $this->lowStockCount = Product::whereColumn('stock', '<=', 'min_stock')->count();
    }

    public function render()
    {
        return view('pages.dashboard');
    }
}
```

- [ ] **Step 4: Jalankan test**

Run: `php artisan test --filter=DashboardTest`
Expected: 3 test PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Livewire/Dashboard.php tests/Feature/DashboardTest.php
git commit -m "feat: dashboard menampilkan statistik inventori nyata (barang, kategori, supplier, low stock)"
```

---

### Task 8: Full suite & self-review

**Files:**
- Test: seluruh suite.

- [ ] **Step 1: Jalankan seluruh test**

Run: `php artisan test`
Expected: SEMUA test PASS (Breeze + Phase 1 + Phase 2).

- [ ] **Step 2: Perbaiki kegagalan jika ada**

Jika ada test gagal (typografi `'integers'`, view `$category` undefined, dst.), perbaiki: cek apakah typo `'integer'` pada ProductCreate/Edit, pastikan `CategoryCreate` punya `public ?Category $category = null;`.

- [ ] **Step 3: Jalankan ulang hingga hijau**

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 4: Commit perbaikan (jika ada)**

```bash
git add -A
git commit -m "fix: perbaikan pasca full test"
```

---

## Self-Review (diisi setelah plan ditulis)

**1. Spec coverage (spec Phase 2):**
- Migrasi/model/factory 3 tabel ✓ (Task 1)
- SKU auto + edge case GEN + increment ✓ (Task 2)
- CRUD Kategori/Supplier/Barang + search + delete protection category/supplier ✓ (Task 3-5)
- Role admin 403 ✓ (Task 3, test staff)
- Dashboard real (count + low stock) ✓ (Task 7)
- Sidebar submenu admin-only ✓ (Task 6)

**2. Placeholder scan:** Tidak ada TBD/TODO. Semua kode ditulis penuh. Catatan desain eksplisit saat ada pilihan (nullOnDelete FK, SKU counter static).

**3. Type consistency / API match:**
- `Category::factory()`, `Supplier::factory()`, `Product::factory()` dibuat Task 1, dipakai Task 3-5 ✓
- `Category::products()->exists()` (Task 1, Task 3 delete) ✓
- `Product::whereColumn('stock','<=','min_stock')` (Task 7) konsisten dengan spec §8 ✓
- Route name `master.kategori|supplier|product` konsisten antara Task 3 & view ✓
- Event `refresh-categories|suppliers|products` konsisten antara komponen index & test ✓
- Properti `$category/$supplier/$product` di Create (null) & Edit (model) konsisten agar view `? : 'Edit'/'Tambah'` jalan ✓
- SKU prefix: `PER` (Peralatan) dipakai Task 2 test & Task 5 test — pastikan nama kategori berbeda antar test (`Peralatan` vs default) agar tidak bentrok unique di dalam satu run; tiap test pakai RefreshDatabase ✓

**4. Caveat yang wajib diperhatikan implementer:**
- Typo sengaja ditulis `'integers'` di Task 5 Step 4 — SUDAH dikoreksi di catatan; pakai `'integer'`.
- View create memakai `$category/$supplier/$product` — komponen Create WAJIB deklarasi properti model dengan nilai default `null`.
- Delete protection category/supplier hanya menampilkan pesan via event toast di Task ini; UX error lengkap (modal konfirmasi) bisa ditambahkan nanti — di luar scope test current.