# Phase 1: Auth & Roles — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tambah role-based access (admin/staff) di atas auth Breeze yang sudah ada, plus dashboard awal yang menampilkan ringkasan inventori.

**Architecture:** Kolom `role` ditambah ke `users` via migration baru. Middleware `EnsureUserHasRole` membatasi akses ke route admin (master data, laporan). Dashboard diganti Livewire class component (`pages::dashboard`) yang menampilkan statistik kosong dulu — siap diisi Phase 3.

**Tech Stack:** Laravel 13, Livewire, Flux UI, Pest (sudah terpasang dari Breeze starter kit).

## Global Constraints

- PHP 8.3 (Laragon `php-8.3.13-nts-Win32-vs16-x64`).
- DB: MySQL `inventori_barang`, root tanpa password.
- Nama role hanya `admin` dan `staff` (kebab-case).
- Jalankan artisan dengan PATH: `$env:PATH = "D:\laragon\bin\php\php-8.3.13-nts-Win32-vs16-x64;" + $env:PATH`
- Test: Pest (`./vendor/bin/pest`).
- View Livewire pakai prefix `pages::` dan file `resources/views/pages/*.blade.php` (pattern Breeze 13).
- Tiap task berakhir dengan commit.

---

### Task 1: Kolom `role` di tabel `users`

**Files:**
- Create: `database/migrations/2026_08_05_000001_add_role_to_users_table.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/RoleTest.php`

**Interfaces:**
- Produces: `users.role` column (string, default `staff`), `User::ROLE_ADMIN` / `User::ROLE_STAFF` constants, `User::isAdmin()` / `User::isStaff()` methods. Model attribute `$fillable` termasuk `role`.

- [ ] **Step 1: Tulis test gagal — UserFactory buat user dengan role**

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_role_attribute(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'admin',
        ]);
    }

    public function test_user_is_admin_helper(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->isStaff());
        $this->assertTrue($staff->isStaff());
        $this->assertFalse($staff->isAdmin());
    }
}
```

- [ ] **Step 2: Jalankan test — pastikan gagal**

Run: `php artisan test --filter=RoleTest`
Expected: FAIL — kolom `role` tidak ada / method `isAdmin` tidak ada.

- [ ] **Step 3: Tulis migration `add_role_to_users_table`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('staff')->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
```

- [ ] **Step 4: Update `app/Models/User.php`** — tambah constants, method helper, `role` ke `#[Fillable]`

```php
class User extends Authenticatable implements PasskeyUser
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_STAFF = 'staff';

    // ... existing use statements

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isStaff(): bool
    {
        return $this->role === self::ROLE_STAFF;
    }
}
```

`#[Fillable(['name', 'email', 'password', 'role'])]`

- [ ] **Step 5: Jalankan migration & test**

Run: `php artisan migrate` lalu `php artisan test --filter=RoleTest`
Expected: migration sukses, kedua test PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_05_000001_add_role_to_users_table.php app/Models/User.php tests/Feature/RoleTest.php
git commit -m "feat: tambah kolom role dan helper isAdmin/isStaff pada User"
```

---

### Task 2: Middleware `EnsureUserHasRole`

**Files:**
- Create: `app/Http/Middleware/EnsureUserHasRole.php`
- Modify: `bootstrap/app.php`
- Test: `tests/Feature/RoleMiddlewareTest.php`

**Interfaces:**
- Consumes: `User::isAdmin()`, `User::isStaff()` (Task 1).
- Produces: Middleware `ensure-role` (alias), args: `admin|staff`. Akses ditolak → HTTP 403 dengan view `errors/403`.

- [ ] **Step 1: Tulis test gagal — middleware blokir staff, izinkan admin**

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function defineRoutes($router): void
    {
        $router->get('/admin-only', fn () => 'ok')->middleware(['auth', 'ensure-role:admin']);
    }

    public function test_admin_can_access_admin_route(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/admin-only')
            ->assertOk();
    }

    public function test_staff_cannot_access_admin_route(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->get('/admin-only')
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin-only')->assertRedirect(route('login'));
    }
}
```

- [ ] **Step 2: Jalankan test — pastikan gagal**

Run: `php artisan test --filter=RoleMiddlewareTest`
Expected: FAIL — middleware alias `ensure-role` belum terdaftar.

- [ ] **Step 3: Buat middleware**

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            abort(403);
        }

        return $next($request);
    }
}
```

- [ ] **Step 4: Daftarkan alias di `bootstrap/app.php`**

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'ensure-role' => \App\Http\Middleware\EnsureUserHasRole::class,
    ]);
})
```

- [ ] **Step 5: Jalankan test**

Run: `php artisan test --filter=RoleMiddlewareTest`
Expected: ketiga test PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Middleware/EnsureUserHasRole.php bootstrap/app.php tests/Feature/RoleMiddlewareTest.php
git commit -m "feat: tambah middleware ensure-role untuk batasi akses admin/staff"
```

---

### Task 3: Dashboard Livewire + placeholder statistik

**Files:**
- Create: `app/Livewire/Dashboard.php`
- Create: `resources/views/pages/dashboard.blade.php`
- Modify: `routes/web.php`
- Modify: `resources/views/dashboard.blade.php` (hapus, diganti halaman Livewire)
- Test: `tests/Feature/DashboardTest.php`

**Interfaces:**
- Consumes: `User::isAdmin()` (Task 1).
- Produces: Route `dashboard` menuju `pages::dashboard`. Properti Livewire `productCount`, `categoryCount`, `supplierCount`, `lowStockCount` — semua integer, default 0 (statistik real di Phase 3).

- [ ] **Step 1: Tulis test gagal — dashboard render & tampilkan angka**

```php
<?php

namespace Tests\Feature;

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

    public function test_dashboard_shows_zero_stats(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertOk()
            ->assertSet('productCount', 0)
            ->assertSet('categoryCount', 0)
            ->assertSet('supplierCount', 0)
            ->assertSet('lowStockCount', 0);
    }
}
```

- [ ] **Step 2: Jalankan test — pastikan gagal**

Run: `php artisan test --filter=DashboardTest`
Expected: FAIL — class `App\Livewire\Dashboard` tidak ada.

- [ ] **Step 3: Buat komponen Livewire**

```php
<?php

namespace App\Livewire;

use Livewire\Component;

class Dashboard extends Component
{
    public int $productCount = 0;
    public int $categoryCount = 0;
    public int $supplierCount = 0;
    public int $lowStockCount = 0;

    public function render()
    {
        return view('pages.dashboard');
    }
}
```

- [ ] **Step 4: Buat view `pages/dashboard.blade.php`**

```blade
<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <div>
            <flux:heading size="xl">Dashboard</flux:heading>
            <flux:subheading>Ringkasan inventori</flux:subheading>
        </div>

        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <flux:card>
                <flux:card.heading>Total Barang</flux:card.heading>
                <flux:card.subheading>{{ $productCount }}</flux:card.subheading>
            </flux:card>
            <flux:card>
                <flux:card.heading>Kategori</flux:card.heading>
                <flux:card.subheading>{{ $categoryCount }}</flux:card.subheading>
            </flux:card>
            <flux:card>
                <flux:card.heading>Supplier</flux:card.heading>
                <flux:card.subheading>{{ $supplierCount }}</flux:card.subheading>
            </flux:card>
            <flux:card>
                <flux:card.heading>Stok Menipis</flux:card.heading>
                <flux:card.subheading>{{ $lowStockCount }}</flux:card.subheading>
            </flux:card>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 5: Update `routes/web.php`**

```php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
});
```

- [ ] **Step 6: Hapus `resources/views/dashboard.blade.php`**

Run: `Remove-Item resources/views/dashboard.blade.php`

- [ ] **Step 7: Jalankan test**

Run: `php artisan test --filter=DashboardTest`
Expected: ketiga test PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Livewire/Dashboard.php resources/views/pages/dashboard.blade.php routes/web.php tests/Feature/DashboardTest.php
git add -u resources/views/dashboard.blade.php
git commit -m "feat: dashboard Livewire dengan statistik placeholder"
```

---

### Task 4: Navigasi sidebar menu inventori

**Files:**
- Modify: `resources/views/layouts/app/sidebar.blade.php`
- Modify: `resources/views/flux/navlist/group.blade.php` (memperbaiki heading expandable agar teks item menu terlihat)

**Interfaces:**
- Produces: Menu sidebar dengan grup "Menu" berisi item `Dashboard`, plus placeholder menu `Master Data` dan `Transaksi` (belum aktif, `wire:navigate` ke `#`). Link Repository & Documentation diganti jadi menu internal kosong.

**Catatan:** Flux `navlist/group` dengan `expandable` memiliki bug — heading "Menu" perlu `<flux:heading>` agar teks tampil. Pattern sudah ada di sidebar Breeze (grup `Platform`). Menu `Master Data`, `Transaksi`, `Laporan` hanya placeholder (route belum ada) — gunakan `#` sementara.

- [ ] **Step 1: Tulis test visual minimal — sidebar mengandung teks menu**

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_shows_inventory_menus(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Master Data')
            ->assertSee('Transaksi')
            ->assertSee('Laporan');
    }
}
```

- [ ] **Step 2: Jalankan test — pastikan gagal**

Run: `php artisan test --filter=SidebarTest`
Expected: FAIL — teks "Master Data" tidak ada di halaman.

- [ ] **Step 3: Update sidebar**

```blade
<flux:sidebar.nav>
    <flux:sidebar.group :heading="__('Platform')" class="grid">
        <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
            {{ __('Dashboard') }}
        </flux:sidebar.item>
    </flux:sidebar.group>

    <flux:sidebar.group :heading="__('Menu')" class="grid">
        <flux:sidebar.item icon="archive-box" href="#">
            {{ __('Master Data') }}
        </flux:sidebar.item>
        <flux:sidebar.item icon="arrows-right-left" href="#">
            {{ __('Transaksi') }}
        </flux:sidebar.item>
        <flux:sidebar.item icon="chart-bar" href="#">
            {{ __('Laporan') }}
        </flux:sidebar.item>
    </flux:sidebar.group>
</flux:sidebar.nav>
```

Ganti blok Repository/Documentation (2 item bawah) dengan komentar `{{-- link eksternal dihapus — diganti menu internal --}}` atau hapus.

- [ ] **Step 4: Jalankan test**

Run: `php artisan test --filter=SidebarTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/layouts/app/sidebar.blade.php tests/Feature/SidebarTest.php
git commit -m "feat: sidebar menu inventori (dashboard + placeholder master data/transaksi/laporan)"
```

---

## Self-Review (diisi setelah plan ditulis)

**1. Spec coverage (PRD §16 Bulan 1):**
- Auth ✓ (Breeze existing)
- Role management ✓ (Task 1-2)
- Halaman login ✓ (Breeze)
- Dashboard ✓ (Task 3-4)

**2. Placeholder scan:** Tidak ada TBD/TODO. Semua kode ditulis lengkap. Menu placeholder `#` eksplisit — bukan placeholder kode.

**3. Type consistency:**
- `User::isAdmin()` dipakai Task 2 (Consumes Task 1) ✓
- `pages::dashboard` dipakai Task 3 & 4 ✓
- Konstanten `ROLE_ADMIN`/`ROLE_STAFF` konsisten ✓
- Middleware alias `ensure-role` → `EnsureUserHasRole` ✓

**Catatan:** Menu Master Data/Transaksi/Laporan sengaja placeholder — route dibuat di Phase 2-4. Test sidebar tetap pass karena cek teks, bukan link.
