# Manual Pengguna — Order Tracker

Order Tracker adalah aplikasi manajemen pesanan, stok, dan laporan yang dirancang khusus untuk usaha kecil dan menengah (UMKM). Aplikasi ini membantu Anda mencatat pesanan pelanggan, memantau pembayaran dan stok barang, mencetak faktur, hingga melihat laporan penjualan dan pajak — semuanya dari satu tempat, baik di ponsel maupun komputer.

Manual ini ditujukan untuk pengguna toko: **Owner** (pemilik toko) dan **Staff** (staf penjualan). Anda tidak perlu latar belakang teknis untuk mengikuti panduan ini — cukup ikuti langkah-langkahnya sesuai peran Anda.

## Cara Membaca Manual Ini

- **Alur klik** ditulis dengan tanda panah dan label dalam kurung siku kode, contoh: buka `Pesanan` → tombol `+` di kanan atas. Label yang ditulis dengan format seperti ini sama persis dengan label yang tampil di aplikasi.
- **Catatan peran** ditandai dengan tulisan **Owner** atau **Staff** pada bagian yang hanya bisa diakses peran tertentu. Jika sebuah menu tidak muncul di akun Anda, kemungkinan menu tersebut khusus Owner.
- **Tips dan catatan** berisi saran praktis atau hal penting yang perlu Anda perhatikan sebelum melakukan sesuatu.
- Semua nilai uang ditulis dengan format Indonesia, contoh: Rp 1.500.000.

## Daftar Isi

| Bab | Isi | Peran |
|---|---|---|
| [01 — Masuk ke Aplikasi](01-masuk-ke-aplikasi.md) | Memasang aplikasi (PWA), memilih toko, login, dan logout | Owner & Staff |
| [02 — Pesanan](02-pesanan.md) | Membuat & mengelola pesanan, status pesanan, draft, riwayat | Owner & Staff¹ |
| [03 — Pembayaran](03-pembayaran.md) | Pembayaran bertahap, status bayar, retur, tautan pembayaran | Owner & Staff² |
| [04 — Faktur dan Cetak](04-faktur-dan-cetak.md) | Pratinjau faktur, cetak faktur & struk | Owner & Staff |
| [05 — Produk dan Kategori](05-produk-dan-kategori.md) | Menambah/mengubah produk, foto, kategori, impor Excel | Owner |
| [06 — Stok](06-stok.md) | Terima stok, riwayat stok, batch & kedaluwarsa, stok opname, pemasok | Owner |
| [07 — Laporan](07-laporan.md) | Laporan harian/tren/staf/produk/pajak, catatan pengeluaran & laba bersih, e-Faktur, cetak | Owner |
| [08 — Pengguna](08-pengguna.md) | Manajemen pengguna Owner/Staff, reset kata sandi, buku bon pelanggan | Owner |
| [09 — Pengaturan: Profil & Tampilan](09-pengaturan-profil-tampilan.md) | Profil toko, logo, tema tampilan | Owner |
| [10 — Pengaturan: Operasi](10-pengaturan-operasi.md) | PPN, pajak (NPWP/PPh), kedaluwarsa, Google Sheets, impor & ekspor data | Owner |
| [11 — Pengaturan: Pembayaran](11-pengaturan-pembayaran.md) | Rekening bank, QRIS, Mayar | Owner |
| [12 — Masalah dan Solusi](12-masalah-dan-solusi.md) | Pertanyaan umum & pemecahan masalah | Owner & Staff |

¹ Staff hanya melihat pesanan yang dibuat oleh Staff sendiri.
² Fitur retur hanya dapat digunakan oleh Owner.

## Matriks Akses per Peran

Menu utama aplikasi berbeda antara Owner dan Staff. Berikut menu yang tampil untuk masing-masing peran:

| Menu | Owner | Staff |
|---|---|---|
| `Pesanan` | ✅ | ✅ |
| `Katalog` | ✅ | ❌ |
| `Laporan` | ✅ | ❌ |
| `Pengguna` | ✅ | ❌ |
| `Pengaturan` | ✅ | ❌ |
| `Keluar` (tombol logout) | ✅ | ✅ |

Catatan tambahan:

- **Staff hanya melihat pesanan sendiri.** Meskipun Staff bisa membuka menu `Pesanan`, daftar pesanan yang tampil dibatasi hanya pada pesanan yang dibuat oleh Staff tersebut. Owner melihat semua pesanan toko.
- Menu `Pengaturan` memiliki empat sub-menu: `Profil`, `Tampilan`, `Operasi`, dan `Pembayaran` — semuanya khusus Owner.
- Menu `Katalog` memiliki lima tab: `Produk`, `Kategori`, `Stok`, `Pemasok`, dan `Opname` — semuanya khusus Owner.

## Glosarium Singkat

| Istilah | Arti |
|---|---|
| Pesanan | Catatan transaksi pesanan pelanggan, dari dibuat hingga selesai atau dibatalkan. |
| Retur | Pengembalian barang dari pesanan yang sudah selesai, dicatat beserta alasannya. |
| Stok Opname | Pengecekan fisik jumlah stok agar cocok dengan catatan di aplikasi (tab `Opname`). |
| Kartu Stok | Riwayat pergerakan stok masuk dan keluar untuk setiap produk. |
| Batch | Nomor kelompok produksi barang; dipakai untuk melacak kedaluwarsa per kelompok. |
| PPN | Pajak Pertambahan Nilai yang dikenakan pada penjualan (diatur di `Pengaturan` → `Operasi`). |
| PPh | Pajak Penghasilan final untuk usaha kecil yang dihitung dari omzet (laporan pajak). |
| e-Faktur | Berkas CSV hasil ekspor laporan pajak siap diunggah ke sistem DJP. |
| QRIS | Gambar kode pembayaran untuk diterima pembayaran; diunggah di `Pengaturan` → `Pembayaran`. |
| Mayar | Layanan tautan pembayaran yang dapat dihubungkan ke pesanan Anda. |
| Sinkronisasi Google Sheets | Pengiriman data penjualan ke Google Sheets secara otomatis. |
| PWA | Progressive Web App — aplikasi web yang dapat dipasang di ponsel seperti aplikasi biasa. |

---

Terakhir diperbarui: Agustus 2026. Dokumen untuk pengembang (teknis) tersedia di folder `docs/`.
