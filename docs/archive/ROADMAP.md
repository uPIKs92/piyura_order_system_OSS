# ROADMAP — Order Tracker v1.0 + v1.1

> Disusun dari keputusan BMAD ot-bmad-1 (R10), DATABASE.md, dan ARCHITECTURE.md.
> Estimasi dalam jam kerja developer tunggal. Prioritas: P0=must have, P1=should have.

---

## 1. Executive Summary

Roadmap ini memetakan implementasi Order Tracker — aplikasi manajemen pesanan berbasis Laravel + Alpine.js + Fetch API — dalam dua rilis. **v1.0** (18 jam) mencakup fondasi: auth row-level Sanctum, CRUD produk & orders, state machine status yang tidak bisa mundur, payments DP/Lunas/Cash, PPN toggle, SoftDeletes + ActivityLog, Alpine UI dengan draft auto-save, integrasi n8n webhook, import artisan command, dan manajemen user Owner/Staff. **v1.1** (10 jam) memperluas dengan laporan multidimensi, retur/refund, auto-cancel scheduler, diskon per item, reset password staff, backup DB terjadwal via n8n, dan deployment maintenance mode. Branch_id ditunda ke rilis berikutnya. Total estimasi: **28 jam** untuk v1.0 + v1.1.

---

## 2. Fitur v1.0 — Tabel Detail

| # | Fitur | Estimasi | Dependensi | Prioritas | Catatan |
|---|-------|----------|------------|-----------|---------|
| 1 | Sanctum Auth + Policy + Row-level | 3 jam | — | **P0** | Login/logout, token abilities, per-staff policy |
| 2 | User Management: Owner CRUD staff | 2 jam | #1 | **P0** | Hanya Owner bisa CRUD staff; role gate |
| 3 | Produk CRUD | 2 jam | #1 | **P0** | Produk + Kategori; relasi belongsTo kategori |
| 4 | Orders CRUD + Optimistic Locking | 3 jam | #1, #3 | **P0** | lock_version field; throws exception saat conflict |
| 5 | Harga Snapshot di order_items | _(inklud #4)_ | #4 | **P0** | harga_jual, harga_beli snap di order_items |
| 6 | Status State Machine (gak bisa mundur) | 1 jam | #4 | **P0** | Enum: draft→confirmed→processing→completed→cancelled; validasi no-backward |
| 7 | Payments: DP/Lunas/Cash | 2 jam | #4 | **P0** | Tabel payments; polymorphic? No — dedicated table |
| 8 | PPN 11% Toggle | 1 jam | #4 | **P1** | Setting toggle per order; hitung otomatis 11% |
| 9 | SoftDeletes + ActivityLog | 1 jam | #1–#4 | **P1** | SoftDeletes semua main table, Spatie ActivityLog |
| 10 | Alpine UI: Draft Auto-save + iOS Touch | 3 jam | #5, #4 | **P1** | Order form Alpine.js, localStorage draft, iOS touch events |
| 11 | n8n Webhook Sync PII-stripped | 2 jam | #4, #1 | **P1** | Endpoint webhook, queue job, strip PII fields |
| 12 | Import Artisan Command + Queue | 1 jam | #3, #4 | **P1** | CSV/JSON import via command, dispatch queue |

---

## 3. Fitur v1.1 — Tabel Detail

| # | Fitur | Estimasi | Dependensi | Prioritas | Catatan |
|---|-------|----------|------------|-----------|---------|
| 13 | Laporan Multidimensi per Staff/Produk/Status | 2 jam | #6, #4 | P0 | Alpine.js custom components + Export (dompdf); filter range tanggal |
| 14 | Filter + Search | _(inklud #13)_ | #13 | P0 | Global search, filter by status/tanggal/staff |
| 15 | Nota Modal + PDF | 1 jam | #4, #7 | P0 | DomPDF/Barryvdh PDF; modal preview before print |
| 16 | Retur/Refund Flow | 2 jam | #4, #7 | P0 | Tabel returs + refunds; validasi jumlah retur ≤ quantity |
| 17 | Auto-cancel Order 7 Hari | 1 jam | #6 | P0 | Scheduler command; ubah draft→cancelled jika >7 hari |
| 18 | Diskon per Item | 1 jam | #4 | P0 | Kolom discount_persen di order_items |
| 19 | Staff Reset Password Flow | _(inklud #2)_ | #2 | P1 | Email reset link atau Owner reset manual |
| 20 | n8n Jadwal Backup DB | _(inklud #11)_ | #11 | P1 | n8n workflow cron dump DB; S3/local |
| 21 | Deploy Maintenance Mode | _(inklud #1)_ | #1 | P1 | `php artisan down` + whitelist IP Owner |
| 22 | Branch_id Scaffold | TUNDA | — | — | Ditunda ke rilis berikutnya; schema sudah siap |

---

## 4. Execution Phases

### Phase 1 — Foundation (Jam 0–3)
| Jam | Task | Output |
|-----|------|--------|
| 0.0–0.5 | Setup Laravel + Filament + Sanctum | `composer create-project`, install filament, sanctum config |
| 0.5–1.5 | **#1: Sanctum Auth + Policy + Row-level** | Migration users + personal_access_tokens; LoginController; middleware; Policy per resource |
| 1.5–2.0 | **#2: User Management** | Filament UserResource; Owner-only gate |
| 2.0–3.0 | **#3: Produk CRUD** | Migration produk + kategori; Filament ProductResource; relasi |

### Phase 2 — Orders Core (Jam 4–8)
| Jam | Task | Output |
|-----|------|--------|
| 4.0–4.5 | Migration orders + order_items + payments | Schema semua 3 tabel |
| 4.5–6.0 | **#4 + #5: Orders CRUD + Optimistic Locking + Harga Snapshot** | OrderResource; lock_version check; snapshot di create |
| 6.0–6.5 | **#6: Status State Machine** | Enum cast; `canTransition()`; validasi no backward |
| 6.5–7.5 | **#7: Payments DP/Lunas/Cash** | PaymentResource; validasi DP ≤ total; trigger status |
| 7.5–8.0 | **#8: PPN 11% Toggle** | Toggle di form order; kalkulasi otomatis |

### Phase 3 — UI + Quality (Jam 9–12)
| Jam | Task | Output |
|-----|------|--------|
| 9.0–9.5 | **#9: SoftDeletes + ActivityLog** | SoftDeletes trait; Spatie ActivityLog; log aktivitas di setiap model |
| 9.5–11.5 | **#10: Alpine UI — Draft Auto-save + iOS Touch** | Alpine form component; localStorage draft; iOS-friendly input |
| 11.5–12.0 | Integrasi & polish | Tes alur end-to-end; fix UX |

### Phase 4 — Integration + Import (Jam 13–16)
| Jam | Task | Output |
|-----|------|--------|
| 13.0–14.5 | **#11: n8n Webhook Sync** | Endpoint routes; webhook controller; PII stripping; queue job |
| 14.5–15.5 | **#12: Import Artisan Command** | Command class; CSV/JSON parser; dispatch ImportJob |
| 15.5–16.0 | **v1.0 QA + Bugfix** | Tes semua fitur; fix blocker |

### Phase 5 — v1.1 (Jam 17–26)
| Jam | Task | Output |
|-----|------|--------|
| 17.0–19.0 | **#13+#14: Laporan + Filter/Search** | Widget laporan; global search scoping; filter form |
| 19.0–20.0 | **#15: Nota Modal + PDF** | Nota view blade; DomPDF export; modal preview |
| 20.0–22.0 | **#16: Retur/Refund** | Migration returs+refunds; ReturResource; validasi |
| 22.0–23.0 | **#17: Auto-cancel Scheduler** | `schedule:run` command; cancelled after 7 days |
| 23.0–24.0 | **#18: Diskon per Item** | Kolom discount_persen di order_items; form input |
| 24.0–24.5 | **#19: Reset Password Staff** | Reset flow via email atau Owner |
| 24.5–25.0 | **#20: n8n Backup DB** | n8n workflow cron; `mysqldump` + upload |
| 25.0–25.5 | **#21: Deploy Maintenance Mode** | `php artisan down`; IP whitelist |
| 25.5–26.0 | **v1.1 QA + Regression** | Tes v1.1 + pastikan v1.0 tidak rusak |

---

## 5. Migration Order — Urutan Pembuatan Tabel

| Urutan | Tabel | Keterangan | Dibuat di |
|--------|-------|------------|-----------|
| 1 | `users` | Auth inti; role enum (owner/staff) | Phase 1 (#1) |
| 2 | `personal_access_tokens` | Sanctum token storage | Phase 1 (#1) |
| 3 | `categories` | Kategori produk | Phase 1 (#3) |
| 4 | `products` | Produk; FK → categories | Phase 1 (#3) |
| 5 | `orders` | Order utama; lock_version; FK → users(staff_id) | Phase 2 (#4) |
| 6 | `order_items` | Item per order; FK → orders, products; snapshot harga | Phase 2 (#4) |
| 7 | `payments` | Pembayaran DP/Lunas/Cash; FK → orders | Phase 2 (#7) |
| 8 | `activity_log` | Spatie ActivityLog | Phase 3 (#9) |
| 9 | `returs` | Retur barang; FK → order_items | Phase 5 (#16) |
| 10 | `refunds` | Pengembalian dana; FK → returs | Phase 5 (#16) |
| — | `branches` | _(Scaffold, TUNDA ke rilis berikutnya)_ | — |
| — | `cache`, `sessions`, `jobs`, `job_batches`, `failed_jobs` | Laravel boilerplate (auto) | Setup awal |

> **Catatan:** Setiap migration harus menyertakan `softDeletes()` kecuali tabel join/pivot.
> Index: `orders.staff_id`, `orders.status`, `order_items.order_id`, `order_items.product_id`, `payments.order_id`, `returs.order_item_id`.

---

## 6. Risiko & Mitigasi

| Risiko | Dampak | Kemungkinan | Mitigasi |
|--------|--------|-------------|----------|
| **Optimistic Locking conflict tinggi** | Order gagal di submit staff ramai | Sedang | Retry logic di frontend; notifikasi "pesanan diubah staff lain" |
| **Alpine draft auto-save bentrok dengan Filament** | Draft tidak tersimpan/loaded | Sedang | Gunakan event listener `x-on:filament-form-save`; test iOS Safari |
| **n8n webhook rate limit / timeout** | Sync gagal | Rendah | Queue job + retry backoff; PII stripping sebelum dispatch |
| **Staff hapus data (SoftDeletes bypass)** | Data hilang permanen | Rendah | Policy cegah force delete; log semua delete di ActivityLog |
| **PPN 11% berubah jadi 12%** | Hitungan salah | Sedang | Simpan persentase PPN di order (snapshot), bukan hardcode |
| **Status state machine terlalu rigid** | Flow bisnis berubah | Rendah | Gunakan config array, bukan enum class; tambah transition di config |
| **Import CSV gagal di baris tengah** | Sebagian data masuk | Sedang | Transaction per baris + batch validation; laporkan error per baris |
| **Branch_id schema berubah** | Migration conflict | Rendah | Scaffold sudah siap di DB; jangan aktifkan hingga rilis branch |

---

## 7. Total Estimasi

| Rilis | Jam | Fitur | Catatan |
|-------|-----|-------|---------|
| **v1.0** | **18 jam** | #1–#12 | Foundation siap production |
| **v1.1** | **10 jam** | #13–#21 | Enhancement + operational |
| **Total** | **28 jam** | 21 fitur | ≈ 4 hari kerja (7 jam/hari) |

> **Asumsi:** Developer sudah familiar Laravel + Filament + Alpine. Setup env, Docker, dan n8n sudah jalan.
> - Jika termasuk environment setup: +2 jam
> - Jika termasuk deployment ke server: +3 jam
> - Branch_id bukan bagian estimasi ini (TUNDA)

---

*Dokumen ini digenerate dari BMAD ot-bmad-1 R10, DATABASE.md (16 tabel), dan ARCHITECTURE.md (22 sections). Update jika ada perubahan scope.*
