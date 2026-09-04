# ARCHITECTURE.md — Order Tracker

> Versi: 1.0 — 17 Juli 2026
> Tech Lead: Atlas (BMAD), Engineer: Forge (BMAD)

---

## 1. Tech Stack

| Layer | Teknologi | Versi | Keterangan |
|-------|-----------|-------|------------|
| Backend | Laravel | 13.x | PHP 8.3+ |
| Frontend | React 19 + shadcn/ui | — | SPA with React Router, iOS HIG chrome |
| CSS | Tailwind CSS | 4.x | Utility-first + shadcn luma preset |
| Auth | Laravel Sanctum | 4.x | Bearer token auth (localStorage) |
| Database | MySQL | 8.0+ | InnoDB, utf8mb4 |
| Queue | Database | — | `jobs` table (default) |
| Scheduler | Laravel Cron | — | `php artisan schedule:run` |
| File Storage | Local (public) + Google Drive | — | spatie/laravel-backup |
| Automation | n8n | — | Workflow orchestration → Google Sheets |
| API | JSON REST | — | fetch() dari React SPA |
| Icons | Heroicons / Tabler | — | SVG inline |

**React SPA + Sanctum bearer tokens (no Inertia, no Livewire):**
Order Tracker uses a React 19 SPA mounted on a single Blade shell (`resources/views/app.blade.php`). All pages call `/api/*` via `resources/js/lib/api.ts` with Sanctum bearer tokens stored in `localStorage`. UI built with shadcn/ui (luma preset) and iOS HIG patterns (glass tab bar, bottom drawers, swipe rows).

---

## 2. Framework Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Api/                        # Endpoint JSON untuk Alpine
│   │   │   ├── AuthController.php
│   │   │   ├── ProductController.php
│   │   │   ├── CategoryController.php
│   │   │   ├── OrderController.php
│   │   │   ├── PaymentController.php
│   │   │   ├── DraftController.php
│   │   │   ├── ReportController.php
│   │   │   └── UserController.php      # Owner only
│   │   └── Web/
│   │       ├── DashboardController.php # Halaman SPA shell
│   │       └── HomeController.php       # Landing
│   ├── Middleware/
│   │   ├── RoleMiddleware.php          # owner/staff gate
│   │   └── CheckOrderVersion.php       # Optimistic locking
│   └── Requests/
│       ├── StoreOrderRequest.php
│       ├── UpdateOrderRequest.php
│       ├── StoreProductRequest.php
│       ├── PaymentRequest.php
│       └── ...
├── Models/
│   ├── User.php
│   ├── Product.php
│   ├── Category.php
│   ├── Order.php
│   ├── OrderItem.php
│   ├── Payment.php
│   ├── OrderStatusLog.php
│   ├── OrderDateHistory.php
│   ├── Return.php              # v1.1
│   ├── ReturnItem.php          # v1.1
│   ├── DraftAutosave.php
│   └── N8nSyncOutbox.php
├── Policies/
│   ├── OrderPolicy.php         # Row-level: staff lihat own, owner lihat semua
│   ├── ProductPolicy.php       # Staff read-only, owner full
│   ├── UserPolicy.php          # Owner only
│   └── PaymentPolicy.php
├── Services/
│   ├── OrderService.php        # Checkout logic, status transition, stock mutation
│   ├── PaymentService.php      # Payment calculation, PPN
│   ├── N8nSyncService.php      # Push to outbox, idempotency
│   ├── DraftService.php
│   ├── BackupService.php
│   └── ReportService.php       # Multidimensi laporan
├── Enums/
│   ├── OrderStatus.php
│   ├── PaymentMethod.php
│   └── UserRole.php
├── Console/
│   └── Commands/
│       ├── OrdersImport.php     # Import data lama dari CSV/Sheets
│       ├── OrdersAutoCancel.php # Cancel expired pending (7 hari)
│       ├── N8nDispatch.php      # Cron: kirim outbox → n8n webhook
│       └── TaxReport.php        # Export CSV laporan Pajak (v1.1)
└── Exceptions/
    ├── OrderVersionMismatchException.php
    └── InvalidStatusTransitionException.php

resources/
├── views/
│   ├── layouts/
│   │   └── app.blade.php       # SPA shell — iOS-style chrome
│   ├── components/
│   │   ├── bottom-tabs.blade.php
│   │   ├── slide-up-modal.blade.php
│   │   ├── swipe-list.blade.php
│   │   └── pull-to-refresh.blade.php
│   ├── orders/
│   │   ├── index.blade.php     # Order list
│   │   ├── create.blade.php    # Draft/checkout form
│   │   ├── show.blade.php      # Detail + timeline
│   │   └── edit.blade.php
│   ├── products/
│   ├── payments/
│   ├── users/
│   └── reports/

database/
├── migrations/
│   ├── 0001_00_00_000001_create_users_table.php
│   ├── 0001_00_00_000002_create_personal_access_tokens_table.php
│   ├── 2026_07_17_000001_create_categories_table.php
│   ├── 2026_07_17_000002_create_products_table.php
│   ├── 2026_07_17_000003_create_orders_table.php
│   ├── 2026_07_17_000004_create_order_items_table.php
│   ├── 2026_07_17_000005_create_payments_table.php
│   ├── 2026_07_17_000006_create_order_status_logs_table.php
│   ├── 2026_07_17_000007_create_order_date_histories_table.php
│   ├── 2026_07_17_000008_create_activity_log_table.php
│   ├── 2026_07_17_000009_create_returns_table.php
│   ├── 2026_07_17_000010_create_return_items_table.php
│   ├── 2026_07_17_000011_create_backups_table.php
│   ├── 2026_07_17_000012_create_draft_autosaves_table.php
│   └── 2026_07_17_000013_create_n8n_sync_outbox_table.php
└── seeders/
    ├── DatabaseSeeder.php
    ├── OwnerSeeder.php
    └── ProductSeeder.php

routes/
├── api.php         # /api/* — Alpine Fetch endpoints
└── web.php         # Halaman SPA + auth routes

config/
├── n8n.php         # Webhook URL, retry policy, idempotency
├── ppn.php         # Default 11.00, toggle aktif/nonaktif
└── backup.php      # spatie/laravel-backup config
```

---

## 3. Authentication — Laravel Sanctum

**Flow:**
1. SPA login: `POST /api/login` → Laravel sets `Set-Cookie: session` (Sanctum SPA mode).
2. API token login: `POST /api/token` → return `plainTextToken` untuk n8n/import.
3. Semua request `/api/*` dilindungi `auth:sanctum`.

**Rate limiting:**
- Login: 5 attempts per menit per IP → blok 1 menit.
- API: 60 req/min.

**Role middleware:**
```php
// app/Http/Middleware/RoleMiddleware.php
public function handle($request, $next, ...$roles) {
    if (! $request->user() || ! in_array($request->user()->role, $roles)) {
        abort(403, 'Forbidden');
    }
    return $next($request);
}
```

Route registration:
```php
Route::middleware(['auth:sanctum', 'role:owner'])->group(function () {
    Route::apiResource('users', UserController::class);
    Route::apiResource('products', ProductController::class)->except(['index', 'show']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('products', [ProductController::class, 'index']);  // staff read-only
    Route::apiResource('orders', OrderController::class);
    Route::apiResource('payments', PaymentController::class);
});
```

---

## 4. Authorization — Laravel Policies

| Model | Policy | Owner | Staff |
|-------|--------|-------|-------|
| Order | `OrderPolicy` | viewAny, view, create, update, delete, forceDelete | view + update own, create |
| Product | `ProductPolicy` | viewAny, view, create, update, delete | viewAny + view only |
| User | `UserPolicy` | full CRUD | — |
| Payment | `PaymentPolicy` | viewAny, create | view + create own scope |

**Row-level scoping** (Order):
```php
// OrderPolicy.php
public function view(User $user, Order $order): bool {
    return $user->role === 'owner' || $order->user_id === $user->id;
}

public function viewAny(User $user): Builder {
    return $user->role === 'owner'
        ? Order::query()
        : Order::where('user_id', $user->id);
}
```

---

## 5. Optimistic Locking

Setiap update `orders` memerlukan `version` yang cocok.

```php
// app/Http/Requests/UpdateOrderRequest.php
// Client mengirim `version` dari response GET terakhir.

// app/Services/OrderService.php
public function update(array $data, Order $order): Order {
    $affected = Order::where('id', $order->id)
        ->where('version', $data['version'])
        ->update([
            'status'   => $status,
            'version'  => DB::raw('version + 1'),
            // ... field lain
        ]);

    if ($affected === 0) {
        throw new OrderVersionMismatchException(
            "Order #{$order->id} telah diubah oleh pengguna lain. Muat ulang."
        );
    }

    return $order->fresh();
}
```

**Client (Alpine):**
```javascript
// Menyimpan `version` dari response terakhir, dikirim di setiap update PUT /api/orders/:id
{
    version: this.currentOrder.version,
    status: 'diproses',
    // ...
}
```

---

## 6. Status State Machine

```php
// app/Enums/OrderStatus.php
enum OrderStatus: string {
    case Draft     = 'draft';
    case Pending   = 'pending';
    case Diproses  = 'diproses';
    case Dikirim   = 'dikirim';
    case Selesai   = 'selesai';
    case Cancelled = 'cancelled';

    public function allowedTransitions(): array {
        return match($this) {
            self::Draft    => [self::Pending, self::Cancelled],
            self::Pending  => [self::Diproses, self::Cancelled],
            self::Diproses => [self::Dikirim],
            self::Dikirim  => [self::Selesai],
            self::Selesai  => [],          // Terminal
            self::Cancelled => [],         // Terminal
        };
    }
}
```

Transisi invalid → `InvalidStatusTransitionException` → HTTP 422.

---

## 7. iOS-Style UI (Mobile-First)

**Prinsip desain:**
- Mobile-first — semua halaman dioptimasi untuk viewport 375px ke atas.
- Bottom tabs navigasi yang tetap (sticky footer).
- Slide-up modal untuk form (bukan page refresh).
- Swipe gestures untuk aksi cepat (hapus/konfirmasi).
- Pull-to-refresh untuk reload data.

**Component stack:**
```
┌─────────────────────────┐
│     Status Bar          │
├─────────────────────────┤
│                         │
│    Page Content         │
│    (Alpine component)   │
│                         │
│                         │
├─────────────────────────┤
│  Bottom Tab Navigation  │
│ [Orders] [Produk] [Lap.]│
│ [Akun]                  │
└─────────────────────────┘
```

**Slide-up modal pattern (Alpine):**
```html
<div x-data="{ open: false }">
    <button @click="open = true">Tambah Order</button>
    <div x-show="open" x-cloak
         @click.away="open = false"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="translate-y-full"
         x-transition:enter-end="translate-y-0"
         class="fixed inset-0 z-50 flex items-end"
    >
        <div class="w-full bg-white rounded-t-2xl max-h-[90vh] overflow-y-auto p-4">
            <!-- Form content -->
        </div>
    </div>
</div>
```

**Swipe actions** via `@alpinejs/touch` (v1.1 enhancement):
```html
<div x-on:touchswipe.right="deleteItem(item.id)" ...>
```

---

## 8. Harga Snapshot & PPN

### Snapshot saat checkout:
1. Saat POST `/api/orders` (checkout draft → pending):
   - `order_items.price_snapshot` di-copy dari `products.price` saat itu
   - `product_name` di-copy dari `products.name`
   - `ppn_percentage` di-copy dari config saat itu
2. Perubahan harga produk setelah checkout tidak mempengaruhi order yang sudah ada.

### PPN Kalkulasi:
```php
// app/Services/OrderService.php
$ppnEnabled = config('ppn.enabled', true);
$ppnRate    = config('ppn.percentage', 11.00); // 11% untuk 2026-2027

$subtotal       = $items->sum(fn($i) => $i['price'] * $i['qty']);
$discountTotal  = $items->sum(fn($i) => $i['discount_value'] ?? 0);
$afterDiscount  = $subtotal - $discountTotal;
$ppnAmount      = $ppnEnabled ? $afterDiscount * ($ppnRate / 100) : 0;
$grandTotal     = $afterDiscount + $ppnAmount;
```

---

## 9. Payments Flow

1. GET `/api/orders/{id}` → `total_paid` komputed dari `payments()->sum('amount')`.
2. POST `/api/orders/{id}/payments` → create payment record.
   - Request: `{amount, metode, notes}`
   - Tidak perlu validasi `<= sisa` — overpayment diperbolehkan sebagai credit.
3. Status lunas: display badge jika `total_paid >= grand_total`.
4. Multi-payment: 1 order bisa punya N payments (cash + transfer, DP + lunas).

---

## 10. n8n Integration

### Architecture:
```
┌─────────────┐     ┌──────────────┐     ┌───────────┐
│ Order       │────▶│ n8n_sync_    │────▶│ Cron:     │
│ Tracker DB  │     │ outbox table │     │ dispatch  │
└─────────────┘     └──────────────┘     └───────────┘
                                              │
                                              ▼
                                         ┌──────────┐
                                         │ n8n      │
                                         │ Webhook  │
                                         └──────────┘
                                              │
                                              ▼
                                         ┌──────────┐
                                         │ Google   │
                                         │ Sheets   │
                                         └──────────┘
```

### Data flow:
1. Saat order status berubah → event `order.updated` → `N8nSyncService::pushToOutbox()`:
   - Generate UUID `idempotency_key`
   - Simpan payload PII-stripped ke `n8n_sync_outbox`
2. Cron (tiap 5 menit) → `php artisan n8n:dispatch`:
   - Ambil records `status=pending`, limit 50
   - POST ke n8n webhook URL
   - Success: `status=sent, sent_at=now`
   - Fail: `attempts++`, jika >=3 → `status=failed`, kirim notifikasi
3. n8n workflow:
   - Terima payload → validasi idempotency key → append ke Google Sheets
   - Retry 3x exponential backoff
   - Gagal → webhook ke Telegram/Email owner

### Config (`config/n8n.php`):
```php
return [
    'webhook_url' => env('N8N_WEBHOOK_URL'),
    'retry_attempts' => 3,
    'retry_delay_ms' => 1000, // base for exponential: 1s, 2s, 4s
    'batch_size' => 50,
];
```

### PII Stripping — data yang dikirim ke Sheets:
```json
{
  "order_id": 1,
  "invoice_no": "INV-001",
  "order_date": "2026-07-17",
  "status": "pending",
  "subtotal": 100000,
  "ppn_amount": 11000,
  "grand_total": 111000,
  "total_paid": 50000,
  "items": [
    {"product_name": "Nasi Goreng", "qty": 2, "price": 25000}
  ]
}
```

**Tidak dikirim:** `customer_name`, `customer_phone`, `customer_address`.

---

## 11. Queue & Scheduler

### Queue (Database):
```php
// config/queue.php — default connection=database
QUEUE_CONNECTION=database
```

### Jobs:
- `App\Jobs\N8nSyncJob` — per outbox record dispatch
- `App\Jobs\ProcessImportRow` — per baris import CSV (batch)
- `App\Jobs\BackupNotification` — notifikasi hasil backup

### Scheduler (`routes/console.php`):
```php
Schedule::command('n8n:dispatch')->everyFiveMinutes();
Schedule::command('orders:auto-cancel')->hourly();
Schedule::command('backup:run')->dailyAt('02:00');
Schedule::command('activitylog:clean')->daily(); // hapus log >90 hari
Schedule::command('queue:prune-batches')->daily();
```

---

## 12. Import Data Lama

`php artisan orders:import` — membaca CSV/XLSX dari `storage/app/imports/`.

```
Format CSV yang diharapkan:
customer_name, customer_phone, order_date, product_name, quantity, price, status

Flow:
1. Baca file → parse header
2. Mapping kolom (konfigurasi di config/import.php)
3. Validasi tiap baris (product exists? price valid? date valid?)
4. Dispatch ProcessImportRow job per baris (batch 100)
5. Output: success count + error report per baris (file JSON)
```

---

## 13. File Storage & Backup

### Storage:
```env
FILESYSTEM_DISK=public        # Produk foto (local)
BACKUP_DISKS=s3,google-drive  # Backup database
```

### Backup strategy (spatie/laravel-backup):
- Daily full backup database + storage pada 02:00.
- Retensi: 7 daily, 4 weekly, 3 monthly.
- Backup ke local + Google Drive (via spatie's Google Drive adapter).
- Notifikasi gagal → Telegram owner.

### Produk foto:
- Upload via POST `/api/products/{id}/photo`
- Simpan di `storage/app/public/products/`
- Akses via `/storage/products/` (after `php artisan storage:link`)

---

## 14. Multi-User & Staff Management

### Role hierarchy:
```
Owner (role=owner)
├── Full CRUD produk
├── Full CRUD user management
├── Lihat semua order (semua staff)
├── Laporan multidimensi
├── Export data
└── Force delete (override soft delete)

Staff (role=staff)
├── Order: create + view/update own
├── Payment: create + view own
├── Produk: read-only
├── Laporan: own orders only
└── Akun sendiri: edit profil, ganti password
```

### Staff resign:
```bash
php artisan orders:reassign --from=5 --to=1
# Reassign semua order staff_id=5 ke owner_id=1
# User staff_id=5 di-set is_active=0
```

---

## 15. Draft Auto-Save

**Client (Alpine + localStorage):**
```javascript
document.addEventListener('alpine:init', () => {
    Alpine.data('orderForm', () => ({
        init() {
            // Restore draft dari localStorage
            const saved = localStorage.getItem(`draft_${this.userId}`);
            if (saved) this.formData = JSON.parse(saved);
        },
        autoSave() {
            localStorage.setItem(`draft_${this.userId}`, JSON.stringify(this.formData));
        }
    }))
});

// Auto-save tiap 5 detik saat form aktif
setInterval(() => { if (isDirty) autoSave(); }, 5000);
```

**Server fallback:** `POST /api/drafts` → `draft_autosaves` table. Clear on checkout.

---

## 16. Auto-Cancel Order Expired

```php
// app/Console/Commands/OrdersAutoCancel.php
$orders = Order::where('status', OrderStatus::Pending)
    ->where('created_at', '<', now()->subDays(7))
    ->get();

foreach ($orders as $order) {
    DB::transaction(function () use ($order) {
        $order->update(['status' => OrderStatus::Cancelled]);
        // Kembalikan stok
        foreach ($order->items as $item) {
            $item->product->increment('stock', $item->quantity);
        }
        activity()->log("Auto-cancel: Order #{$order->invoice_no} expired 7 hari");
    });
}
```

---

## 17. Laporan Multidimensi

### Endpoints:

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | `/api/reports/daily?date=2026-07-17` | Omset harian |
| GET | `/api/reports/staff?staff_id=5&from=...&to=...` | Per staff |
| GET | `/api/reports/product?product_id=3&from=...&to=...` | Per produk |
| GET | `/api/reports/status` | Summary per status |
| GET | `/api/reports/tax?year=2026` | Rekap pajak PPN + PPh Final UMKM per masa |

### Response shape:
```json
{
  "period": { "from": "2026-07-01", "to": "2026-07-17" },
  "total_orders": 45,
  "total_revenue": 4500000.00,
  "total_ppn": 495000.00,
  "by_staff": [
    { "staff_id": 1, "name": "Ahmad", "orders": 20, "revenue": 2000000 },
    { "staff_id": 2, "name": "Siti", "orders": 25, "revenue": 2500000 }
  ],
  "by_status": {
    "pending": 5, "diproses": 3, "dikirim": 2,
    "selesai": 30, "cancelled": 5
  }
}
```

### Response `/api/reports/tax` (PPN + PPh per masa pajak):
```json
{
  "year": 2026,
  "ppn": { "enabled": true, "percentage": 11.0 },
  "pph": { "mode": "umkm_non_pkp", "total": 404.00 },
  "totals": { "orders": 3, "omzet": 80801.00, "dpp": 73100.00, "ppn": 7701.00, "faktur_count": 2 },
  "months": [
    { "month": 1, "orders": 2, "omzet": 60600.75, "faktur_count": 1, "dpp": 54000.00, "ppn": 6600.75, "pph": 303.00 },
    { "month": 2, "orders": 1, "omzet": 20200.25, "faktur_count": 1, "dpp": 19100.00, "ppn": 1100.25, "pph": 101.00 },
    { "month": 3, "orders": 0, "omzet": 0, "faktur_count": 0, "dpp": 0, "ppn": 0, "pph": 0 }
  ]
}
```
`months` selalu 12 baris (zero-filled). `omzet` = SUM `grand_total` order non-cancelled (draft ikut). `dpp`/`ppn` hanya order dengan `ppn_amount > 0`. `faktur_count` = order ber-PPN dengan status `pending|diproses|dikirim|selesai` (draft & cancelled tidak). `pph` = PPh Final UMKM (PP 55/2022 jo. PP 28/2025): non-PKP 0,5% omzet kumulatif tahunan sampai Rp500jt lalu 12% untuk kelebihannya (dihitung per masa: f(kum s.d. masa ini) − f(kum s.d. masa lalu)); mode PKP (`pajak.pph_mode` = `umkm_pkp_22`) = 2,5% × omzet masa berjalan. Ambang/tarif di `config/pajak.php`.

---

## 18. Branch ID (Multi-Toko — TUNDA)

`branch_id` column telah di-scaffold sebagai nullable di tabel berikut (untuk v1.0):
- `orders.branch_id` BIGINT UNSIGNED NULL
- `products.branch_id`
- `users.branch_id`
- `payments.branch_id`

Belum diaktifkan. Query filter tetap global untuk v1.0.
Ketika multi-toko diimplementasikan, tinggal aktifkan middleware scope.

```php
// Future: BranchScope
protected static function booted(): void {
    static::addGlobalScope(fn (Builder $q) =>
        $q->when(auth()->user()?->branch_id, fn ($q) =>
            $q->where('branch_id', auth()->user()->branch_id)
        )
    );
}
```

---

## 19. Dependency Packages

| Package | Versi | Fungsi |
|---------|-------|--------|
| laravel/framework | ^13.0 | Framework |
| laravel/sanctum | ^4.0 | Auth |
| spatie/laravel-activitylog | ^4.x | Audit trail |
| spatie/laravel-backup | ^8.x | DB backup |
| spatie/laravel-model-states | ^2.x | State machine (opsional — bisa enum native) |
| @alpinejs/touch | — | Touch/swipe gestures (v1.1) |
| maatwebsite/laravel-excel | ^3.x | Import CSV/XLSX |

---

## 20. Environment Variables

```env
# App
APP_NAME="Order Tracker"
APP_URL=https://ordertracker.example.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=order_tracker
DB_USERNAME=root
DB_PASSWORD=

# Sanctum
SANCTUM_STATEFUL_DOMAINS=ordertracker.example.com
SESSION_DRIVER=cookie

# n8n
N8N_WEBHOOK_URL=https://n8n.example.com/webhook/order-sync
N8N_RETRY_ATTEMPTS=3

# PPN
PPN_ENABLED=true
PPN_PERCENTAGE=11.00

# Backup
BACKUP_DISKS=local,google-drive
GOOGLE_DRIVE_CLIENT_ID=
GOOGLE_DRIVE_CLIENT_SECRET=
GOOGLE_DRIVE_REFRESH_TOKEN=
GOOGLE_DRIVE_FOLDER_ID=

# Mail (notifikasi)
MAIL_MAILER=log
MAIL_FROM_ADDRESS=noreply@ordertracker.example.com
```

---

## 21. Deployment Checklist (v1.0)

- [ ] Server provisioning (Linux, PHP 8.3+, MySQL 8, Composer, Node)
- [ ] Environment config (.env)
- [ ] `php artisan key:generate`
- [ ] `php artisan migrate --seed` (Owner seeder)
- [ ] `php artisan storage:link`
- [ ] Setup cron: `* * * * * cd /path && php artisan schedule:run`
- [ ] Setup queue worker: `php artisan queue:work --daemon`
- [ ] n8n webhook URL config
- [ ] Google Drive API credentials
- [ ] SSL (Let's Encrypt)
- [ ] Maintenance mode on deploy: `php artisan down --retry=60`

---

## 22. Diagram Alur Data

```
User (Mobile/Desktop)
       │
       ▼
   Browser (Alpine.js)
       │
       ├── localStorage (draft auto-save)
       │
       ▼
   fetch() ──────▶ Laravel (API)
                       │
                       ├── Sanctum Auth
                       ├── Policy (row-level)
                       ├── Request Validation
                       ├── Service Layer
                       │   ├── OrderService
                       │   ├── PaymentService
                       │   └── N8nSyncService
                       ├── Model → MySQL
                       ├── Activity Log
                       └── n8n_sync_outbox
                            │
                            ▼
                       Cron: n8n:dispatch ───▶ n8n Webhook
                                                   │
                                                   ▼
                                              ┌────────────┐
                                              │ Google      │
                                              │ Sheets      │
                                              │ (read-only  │
                                              │  report)    │
                                              └────────────┘
```

---

## 23. Invarian Skema (Single-Tenant)

Edisi single-tenant mempertahankan kolom `tenant_id` di skema sebagai invarian internal — global scope no-op yang selalu resolve ke tenant default (satu toko per instalasi). Aturan berikut tetap dicatat karena berlaku jika kode suatu saat digunakan ulang multi-tenant:

- **Child tables tanpa `tenant_id`** — `order_items`, `payments`, `product_units`, `return_items`, `order_item_batches`, `stock_receipt_lines`, `order_status_logs`, `order_date_histories` — hanya boleh di-query via parent tenant-scoped-nya (isolasi mengandalkan `tenant_id` milik parent).
- **Queued jobs / artisan commands** yang menyentuh tenant model wajib bind `currentTenantId` (atau pass `tenant_id` eksplisit) — tanpa itu `TenantScope` fail-open dan creating guard untuk model baru melempar exception.
- **API route tenant-facing baru** wajib didaftarkan di dalam middleware group `tenant` di `routes/api.php` — di-enforce oleh route-audit feature test.
- **Unique constraint baru di tabel tenant-owned** harus berupa composite tenant-scoped (include `tenant_id`), mengikuti pola existing: `users.email`, `products` slug/sku, `categories.slug`, `orders.invoice_no`, `stock_receipts.receipt_no`, `returns.return_no` (per-tenant).
