# Pengaturan: Pembayaran

Sub-menu `Pembayaran` mengatur cara toko Anda menerima uang: rekening bank untuk transfer manual, gambar QRIS statis, dan layanan Mayar untuk QRIS dinamis. Ketiganya dipakai otomatis oleh formulir pembayaran di menu `Pesanan` — jadi cukup diatur sekali di sini, dan setiap pencatatan pembayaran selanjutnya langsung menampilkan data yang benar.

Halaman ini terbagi menjadi tiga kartu: `Detail Bank`, `QRIS Statis`, dan `Mayar (QRIS Dinamis)`. Ketiga kartu tampil terlipat secara bawaan — ketuk judul kartu untuk membukanya, dan ketuk lagi untuk melipatnya. Bab ini menjelaskan cara mengisi ketiganya, lalu bagaimana aplikasi memilih QR mana yang tampil saat pelanggan membayar.

> **Catatan:** Seluruh data pembayaran di halaman ini — rekening, QRIS, dan API key Mayar — hanya dapat dilihat dan diatur oleh **Owner**. Staff dapat memakainya saat mencatat pembayaran, tetapi tidak dapat mengubah pengaturannya. Bila menu `Pengaturan` tidak muncul di akun Anda, loginlah sebagai Owner.

## Membuka Halaman

1. Login sebagai Owner.
2. Buka `Pengaturan` → `Pembayaran`.
3. Ketiga kartu pengaturan tampil dalam keadaan terlipat: `Detail Bank`, `QRIS Statis`, dan `Mayar (QRIS Dinamis)`.

## Mengisi Rekening Bank

Rekening ini tampil otomatis di formulir pembayaran setiap kali metode `Transfer` dipilih, sehingga pelanggan tahu ke mana harus mengirim uang.

1. Buka `Pengaturan` → `Pembayaran` → kartu `Detail Bank`.
2. Isi kolom-kolom berikut:
   - `Nama bank` — nama bank toko Anda, contoh isian yang disediakan: `BCA / Mandiri / BRI`.
   - `Atas nama` — nama pemilik rekening, contoh isian: `Nama pemilik rekening`.
   - `Nomor rekening` — nomor rekening toko.
3. Ketuk `Simpan`.
4. Muncul notifikasi "Detail bank disimpan".

Untuk mengganti rekening di kemudian hari, cukup isi ulang kolom yang diubah lalu ketuk `Simpan` lagi.

> **Tips:** Periksa kembali nomor rekening sebelum menyimpan. Satu angka yang salah bisa membuat pelanggan mentransfer ke rekening keliru.

> **Catatan:** Informasi ini tampil pada kotak transfer di formulir pembayaran beserta tombol `Salin` untuk menyalin nomor — selengkapnya di bab `03-pembayaran.md`. Bila belum diisi, formulir menampilkan tulisan "Rekening belum dikonfigurasi." beserta tautan `Atur di Pengaturan`.

## Mengunggah Gambar QRIS Statis

QRIS statis adalah gambar kode QR penerimaan Anda sendiri (misalnya hasil unduhan dari aplikasi mobile banking). Bila ada, gambar inilah yang dipakai — gratis — dan QR dinamis Mayar otomatis dinonaktifkan.

1. Buka `Pengaturan` → `Pembayaran` → kartu `QRIS Statis`.
2. Ketuk `Unggah gambar QRIS`.
3. Pilih file gambar QR Anda. Format yang didukung: PNG, JPG, atau WebP, dengan ukuran maksimal 2 MB.
4. Muncul notifikasi "Gambar QRIS diperbarui" dan gambar QR kini tampil di kartu ini.

Untuk menghapus gambar QRIS:

1. Pada kartu `QRIS Statis`, ketuk `Hapus QRIS` di bawah gambar.
2. Muncul notifikasi "Gambar QRIS dihapus" dan tombol `Unggah gambar QRIS` kembali tampil.

> **Catatan:** Gambar QRIS tampil pada formulir pembayaran di bawah tulisan "Scan QRIS statis di atas" setiap kali metode `QRIS` dipilih — lihat bab `03-pembayaran.md`.

> **Tips:** Pastikan gambar yang diunggah jelas dan tidak terpotong agar mudah discan pelanggan.

## Menghubungkan Mayar (QRIS Dinamis)

Mayar membuat kode QR baru secara otomatis untuk setiap pesanan dengan nominal yang pas — pelanggan tinggal scan tanpa perlu Anda mengonfirmasi nominalnya. Mayar aktif bila API key terisi **dan** tidak ada gambar QRIS statis.

1. Buka `Pengaturan` → `Pembayaran` → kartu `Mayar (QRIS Dinamis)`.
2. Tempel kunci dari akun Mayar Anda pada kolom `API Key Mayar` (contoh isian: "Tempel API key dari dashboard Mayar").
3. Ketuk `Simpan API Key`. Muncul notifikasi "API key Mayar disimpan".
4. Ketuk `Tes Koneksi` untuk memastikan hubungan dengan Mayar berjalan. Bila lancar, muncul notifikasi "Koneksi Mayar berhasil.".
5. Periksa baris status di bagian bawah kartu:
   - `Status: Aktif` — QR dinamis siap dipakai.
   - `Status: Nonaktif` — kunci belum tersimpan atau belum berlaku.

> **Catatan:** Demi keamanan, kunci API tidak pernah tampil lagi setelah disimpan — kolomnya menampilkan "terisi, biarkan kosong untuk tetap". Biarkan kosong saat menyimpan pengaturan lain bila Anda tidak ingin mengganti kunci.

> **Tips:** Tombol `Tes Koneksi` baru bisa ditekan setelah Mayar aktif. Bila muncul "Gagal terhubung ke Mayar. Periksa API key.", salin ulang kuncinya dari dashboard Mayar, simpan, lalu tes kembali.

## Memilih antara QRIS Statis dan Mayar

Anda cukup memahami satu aturan sederhana: gambar QRIS statis selalu diutamakan.

1. Bila ada gambar QRIS statis, gambar itulah yang dipakai dan Mayar dinonaktifkan — status Mayar tertulis "Aktif (QRIS statis dipakai)".
2. Bila Anda ingin memakai QR dinamis Mayar, hapus gambar QRIS statis terlebih dahulu.
3. Bila keduanya kosong, metode `QRIS` menampilkan tulisan "QRIS belum dikonfigurasi." beserta tautan `Atur di Pengaturan`.

> **Tips:** QRIS statis cocok bila Anda ingin tanpa biaya layanan pihak ketiga. Mayar lebih praktis karena nominal per pesanan sudah pas otomatis — pilih salah satu yang paling sesuai dengan cara Anda bekerja.

## QR Mana yang Dipakai di Formulir Pembayaran?

Aplikasi menentukan sendiri saat metode `QRIS` dipilih pada formulir pembayaran:

1. Bila ada gambar QRIS statis, gambar itulah yang tampil.
2. Bila tidak ada QRIS statis dan Mayar aktif, aplikasi menampilkan "Membuat QR Mayar…" lalu QR baru per pesanan dengan tulisan "Scan untuk bayar Rp …" sesuai sisa tagihan. Tombol `Muat ulang QR` tersedia bila QR perlu dibuat ulang.
3. Bila keduanya belum diatur, tampil tulisan "QRIS belum dikonfigurasi." beserta tautan `Atur di Pengaturan`.

> **Catatan:** Alur lengkap mencatat pembayaran tunai, transfer, dan QRIS — termasuk pembayaran bertahap — dijelaskan di bab `03-pembayaran.md`.
