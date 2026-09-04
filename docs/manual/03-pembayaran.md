# Pembayaran & Retur

Setelah pesanan dibuat, Anda dapat mencatat pembayaran pelanggan — sekaligus (lunas) atau bertahap (DP lalu cicilan). Bab ini menjelaskan cara mencatat pembayaran, memakai QRIS (termasuk QR Mayar), membaca badge status pembayaran, dan memproses retur barang.

> **Catatan:** Penjelasan tentang status pesanan, alur kerja, dan riwayat pesanan ada di bab `02-pesanan.md`.

## Membuka Formulir Pembayaran

1. Buka halaman `Pesanan`.
2. Pilih salah satu cara berikut:
   - **Ponsel:** geser baris pesanan ke kiri, lalu ketuk tombol `Bayar`.
   - **Layar lebar (desktop):** arahkan kursor ke baris pesanan, lalu ketuk tombol `Bayar` yang muncul di sisi kanan baris.
   - **Dari detail pesanan:** ketuk pesanan untuk membuka detailnya, lalu ketuk tombol `Bayar` di bagian bawah detail.
3. Formulir `Tambah Pembayaran` akan terbuka.

Formulir pembayaran juga terbuka otomatis setiap kali Anda selesai mengirim pesanan baru ke pelanggan.

> **Catatan:** Tombol `Bayar` hanya tersedia untuk pesanan berstatus `Menunggu`, `Diproses`, atau `Dikirim` yang pembayarannya belum `Lunas`.

## Menambah Pembayaran

1. Buka formulir `Tambah Pembayaran` (lihat langkah di atas).
2. Pilih `Metode`: `Cash`, `Transfer`, atau `QRIS` — cara kerja kolom `Jumlah` menyesuaikan metode yang dipilih (lihat bagian [Memilih Metode Pembayaran](#memilih-metode-pembayaran) di bawah).
3. Isi nominal pembayaran. Untuk `Cash`, Anda juga dapat mengisi `Uang Diterima` dengan uang yang diserahkan pelanggan — ketuk tombol `Uang Pas` bila uangnya pas — lalu `Kembalian` tampil otomatis sebagai pratinjau (khusus metode Cash).
4. Ketuk `Catat Pembayaran`.
5. Panel `Pembayaran Dicatat` muncul, berisi `Total`, `Dibayar`, `Sisa`, dan `Kembalian` (bila ada). Dari panel ini Anda bisa langsung `Cetak Struk`, `Unduh Invoice PDF`, menambah pembayaran lagi, atau membuat `Pesanan Baru`.

> **Tips:** Pembayaran selalu dicatat dengan tanggal hari ini secara otomatis.

> **Catatan:** Pastikan nominal sudah benar sebelum menekan `Catat Pembayaran`. Bila salah catat, hapus pembayaran tersebut lalu catat ulang (lihat bagian [Menghapus Pembayaran](#menghapus-pembayaran) di bawah).

## Memilih Metode Pembayaran

- `Cash` — pembayaran tunai langsung di kasir. Isi kolom `Jumlah`, lalu isi `Uang Diterima` dengan uang yang diserahkan pelanggan; tombol `Uang Pas` mengisinya otomatis sama dengan jumlah tagihan. Baris pratinjau `Kembalian` hanya tampil untuk metode ini.
- `Transfer` — kolom `Jumlah` otomatis terisi sisa tagihan, dan tetap bisa diubah untuk transfer sebagian. Aplikasi menampilkan kotak "Transfer ke rekening berikut" berisi `Bank`, `Atas Nama`, dan `No. Rekening` toko Anda, lengkap dengan tombol `Salin` untuk menyalin nomor rekening. Tunjukkan informasi ini ke pelanggan, lalu catat pembayarannya setelah uang benar-benar masuk.
- `QRIS` — menampilkan gambar QR untuk discan pelanggan. Pada QRIS statis, kolom `Jumlah` tetap bisa diubah dan pembayaran dicatat manual setelah uang masuk. Pada QRIS dinamis Mayar, kolom `Jumlah` terkunci persis di sisa tagihan (lihat bagian [Membayar dengan QRIS Mayar](#membayar-dengan-qris-mayar) di bawah).

> **Catatan:** Rekening bank dan gambar QRIS diatur di `Pengaturan → Pembayaran` — lihat bab `11-pengaturan-pembayaran.md`. Jika belum diatur, akan muncul tulisan "Rekening belum dikonfigurasi." atau "QRIS belum dikonfigurasi." beserta tautan `Atur di Pengaturan`.

## Pembayaran Bertahap (DP dan Cicilan)

Satu pesanan boleh memiliki lebih dari satu pembayaran.

1. Catat pembayaran pertama (DP) dengan nominal lebih kecil dari total — misalnya Rp 500.000 dari total Rp 1.500.000.
2. Badge pembayaran pesanan berubah menjadi `Sebagian`, dan baris `Sisa` menampilkan tagihan tersisa (Rp 1.000.000).
3. Saat pelanggan melunasi, buka kembali tombol `Bayar` — atau ketuk `Tambah Pembayaran` di panel `Pembayaran Dicatat`.
4. Ulangi sampai lunas; badge berubah menjadi `Lunas`.

Semua pembayaran tercantum di detail pesanan, tab `Ringkasan`, dalam kartu `Pembayaran` (metode dan nominal tiap pembayaran). Ringkasan nominalnya ada di kartu `Invoice`: `Subtotal`, `Diskon`, `PPN (…%)`, `Ongkir` (bila ada), `Total`, `Dibayar`, dan `Sisa`.

## Membayar Lebih dari Total

Aplikasi mengizinkan pembayaran melebihi total tagihan (misalnya pelanggan tidak mengambil kembalian).

- Selisihnya otomatis dihitung dan ditampilkan sebagai `Kembalian` — di formulir, di panel `Pembayaran Dicatat`, dan di detail pesanan.
- Badge pembayaran pesanan berubah menjadi `Lebih bayar`.

> **Tips:** Untuk tunai, isi `Uang Diterima` dengan uang yang diserahkan pelanggan agar `Kembalian` dihitung otomatis.

## Menghapus Pembayaran

Pembayaran yang salah catat dapat dihapus dari pesanan.

1. Buka detail pesanan (ketuk pesanan pada daftar), lalu di tab `Ringkasan` cari kartu `Pembayaran`.
2. Ketuk ikon tempat sampah di samping nominal pembayaran yang ingin dihapus.
3. Pada konfirmasi `Hapus pembayaran?`, ketuk `Hapus` untuk melanjutkan atau `Batal` untuk membatalkan.
4. Muncul notifikasi "Pembayaran dihapus". Nilai `Dibayar` dan `Sisa` pada kartu `Invoice`, beserta badge status pembayaran, diperbarui otomatis.

> **Catatan:** Tombol hapus hanya tampil untuk **Owner** atau pembuat pesanan tersebut.

> **Tips:** Gunakan dengan hati-hati — menghapus pembayaran mengubah nilai `Dibayar` dan `Sisa` pesanan, serta ikut memengaruhi angka pada laporan.

## Status Pembayaran Pesanan

Setiap pesanan membawa badge status pembayaran:

| Badge | Arti |
| --- | --- |
| `Belum bayar` | belum ada pembayaran tercatat |
| `Sebagian` | sudah dibayar sebagian, masih ada `Sisa` |
| `Lunas` | total sudah terbayar penuh |
| `Lebih bayar` | pembayaran melebihi total tagihan |

Badge ini tampil di daftar pesanan (ponsel), di kolom `Pembayaran` pada tabel (desktop), dan di detail pesanan.

## Membayar dengan QRIS Mayar

Jika QRIS dinamis Mayar sudah diaktifkan, aplikasi otomatis membuat QR khusus yang berisi nominal sisa tagihan pesanan tersebut.

1. Buka formulir `Tambah Pembayaran`.
2. Pilih metode `QRIS` — QR dibuat otomatis, bertuliskan "Scan untuk bayar Rp …".
3. Minta pelanggan memindai QR tersebut dan membayar.
4. Setelah pembayaran masuk, pembayaran QRIS tercatat otomatis pada pesanan berstatus `Menunggu`. Bila belum juga tercatat, catat manual dengan metode `QRIS`.

Jika QR tidak tampil atau ingin QR terbaru, ketuk `Muat ulang QR`.

Kolom `Jumlah` pada metode QRIS Mayar terkunci di sisa tagihan karena QR-nya memuat nominal tersebut — Anda tidak perlu (dan tidak bisa) mengisinya manual. Karena itu pembayaran sebagian (DP) tidak bisa lewat QRIS Mayar; catat dulu dengan metode `Cash` atau `Transfer`, lalu setelah sisa tagihan berubah, QR baru dibuat otomatis dengan nominal sisa terkini.

> **Catatan:** Mayar harus dikonfigurasi lebih dulu di `Pengaturan → Pembayaran` — lihat bab `11-pengaturan-pembayaran.md`. Jika toko memakai gambar QRIS statis, yang ditampilkan adalah gambar tersebut ("Scan QRIS statis di atas"), bukan QR Mayar.

## Mengembalikan Barang (Retur)

Retur hanya dapat diproses oleh pemilik toko (Owner), dan hanya untuk pesanan berstatus `Selesai`.

1. Buka `Pesanan` → ketuk pesanan berstatus `Selesai`.
2. Buka tab `Retur`.
3. Tulis `Alasan retur` (bebas, wajib diisi).
4. Isi jumlah yang diretur untuk tiap barang — tidak boleh melebihi jumlah yang dibeli.
5. Ketuk `Proses Retur`.

Yang terjadi setelah retur diproses:

- Nomor retur dibuat (berawalan `RET-`) beserta nilai refundnya, misalnya "RET-20260824-AB12 — refund Rp 150.000".
- Nilai refund dihitung dari harga barang **saat pesanan dibuat** dikali jumlah yang diretur.
- Stok barang yang diretur otomatis bertambah kembali.
- Total pesanan tidak diubah — retur tercatat sebagai dokumen terpisah dari pesanan.

> **Catatan:** Staf tidak melihat tab `Retur`; fitur ini khusus Owner. Jika pesanan belum `Selesai`, selesaikan dulu pesanannya sebelum retur.
