# Pengguna

Menu `Pengguna` adalah tempat Anda mengelola akun staf toko — menambah staf baru, mengubah data, mengirim reset kata sandi, sampai menonaktifkan atau menghapus akun — sekaligus merawat daftar pelanggan toko yang tersambung dengan pesanan. Menu ini khusus pemilik usaha (Owner) — staf tidak melihat menu `Pengguna` di navigasinya.

## Membuka Daftar Pengguna

1. Masuk ke aplikasi dengan akun Owner.
2. Pilih menu `Pengguna` — di ponsel letaknya di bilah bawah, di komputer di sidebar kiri.
3. Di bagian atas halaman ada dua tab: `Staff` untuk mengelola akun staf dan owner, serta `Pelanggan` untuk direktori pelanggan toko (lihat `Mengelola Pelanggan` di bawah).
4. Pada tab `Staff`, setiap baris menampilkan nama, email, dan lencana status: `Aktif` atau `Nonaktif`.
5. Akun Anda sendiri selalu berada di urutan pertama dengan lencana `Anda`.

> **Catatan:** Aksi baris hanya aktif pada baris staf lain — baris akun Anda sendiri tidak bereaksi dan tidak bisa diubah atau dihapus dari halaman ini. Bila toko belum memiliki staf, halaman menampilkan pesan "Belum ada staff".

## Aksi pada Baris

Aksi pada tiap baris memakai gerakan langsung di barisnya alih-alih tombol titik tiga (⋯):

- Ketuk baris → formulir `Ubah` terbuka.
- Tekan-lama baris → konfirmasi `Hapus` muncul.
- Geser baris ke kiri (khusus tab `Staff`) → tombol `Reset Sandi` tampil; ketuk tombolnya untuk mengirim reset kata sandi.

Di komputer, klik-kanan pada baris setara dengan tekan-lama, dan "geser" setara dengan tahan-klik lalu geser.

> **Catatan:** Tekan-lama tidak bereaksi pada baris Owner karena akun Owner tidak dapat dihapus — reset kata sandinya tetap bisa dikirim lewat geser kiri.

## Menambah Pengguna

1. Buka `Pengguna` → tombol `Tambah` di kanan atas. Formulir `Tambah Staff` terbuka.
2. Isi data staf:
   - `Nama` — nama lengkap staf, wajib diisi.
   - `Email` — alamat email untuk masuk; harus berbeda dari akun lain di toko Anda.
   - `Password` — kata sandi awal staf, minimal 8 karakter.
   - `Role` — pilih `Staff` (pilihan bawaan) atau `Owner`.
   - `Aktif` — biarkan sakelar menyala agar staf bisa langsung masuk.
3. Tekan `Buat`. Staf baru langsung muncul di daftar.

> **Tips:** Mulailah dengan peran `Staff`. Owner dapat melihat seluruh data toko termasuk laporan dan pengaturan, jadi berikan peran `Owner` hanya kepada orang yang benar-benar dipercaya mengelola toko.

## Mengubah Data Pengguna

1. Buka `Pengguna`, lalu ketuk baris staf yang dituju. Formulir `Ubah Staff` terbuka.
2. Ubah yang diperlukan — `Nama`, `Email`, `Role`, atau sakelar `Aktif`. Label kata sandi berbunyi `Password (kosongkan jika tidak diubah)`: biarkan kosong bila tidak ingin menggantinya.
3. Tekan `Simpan`.

> **Tips:** Ingin staf menentukan kata sandinya sendiri? Gunakan menu `Reset kata sandi` di bawah, bukan mengisi kata sandi baru di sini.

## Reset Kata Sandi

Menu `Reset kata sandi` mengirim tautan pembuatan kata sandi baru ke email staf.

1. Buka `Pengguna`, lalu geser baris staf ke kiri dan ketuk tombol `Reset Sandi` yang tampil.
2. Muncul kotak konfirmasi `Reset kata sandi?` yang menampilkan email tujuan, misalnya "Kirim link reset ke sari@toko.com".
3. Tekan `Kirim`. Muncul pemberitahuan "Link reset password dikirim".
4. Minta staf membuka email tersebut dan mengikuti tautannya untuk membuat kata sandi baru.

> **Catatan:** Email staf harus aktif dan dapat menerima email. Bila email tidak sampai, Anda bisa mengganti kata sandinya langsung lewat menu `Ubah` — isi `Password` baru (minimal 8 karakter), tekan `Simpan`, lalu sampaikan kata sandi itu kepada staf.

## Menonaktifkan Pengguna

Staf yang dinonaktifkan tidak bisa masuk, tetapi akun dan riwayat pesanannya tetap tersimpan.

1. Buka `Pengguna`, lalu ketuk baris staf yang dinonaktifkan — formulir `Ubah Staff` terbuka.
2. Matikan sakelar `Aktif`, lalu tekan `Simpan`.
3. Lencana staf tersebut berubah menjadi `Nonaktif`.

> **Catatan:** Saat staf nonaktif mencoba masuk, akan muncul pesan "Akun ini telah dinonaktifkan." — akun baru bisa dipakai lagi setelah Anda mengaktifkannya kembali. Selengkapnya lihat bab [Masuk ke Aplikasi](01-masuk-ke-aplikasi.md).

## Menghapus Pengguna

1. Buka `Pengguna`, lalu tekan-lama baris staf yang dituju. Di komputer: klik-kanan barisnya.
2. Muncul konfirmasi `Hapus user?` dengan keterangan "User tidak akan bisa login lagi."
3. Tekan `Hapus`. Akun tersebut hilang permanen dari daftar dan tidak bisa digunakan lagi.

> **Catatan:** Akun Owner tidak dapat dihapus — tekan-lama tidak bereaksi pada baris Owner, tetapi reset kata sandinya tetap bisa dikirim lewat geser kiri. Staf yang masih memiliki pesanan tercatat juga tidak bisa dihapus — sistem menolaknya agar riwayat pesanan toko tetap utuh. Untuk menghentikan akses, cukup nonaktifkan staf (lihat "Menonaktifkan Pengguna" di atas).

## Mengelola Pelanggan

Tab `Pelanggan` berisi direktori pelanggan toko: nama, telepon, alamat, dan catatan. Direktori hanya memuat pelanggan yang bisa dihubungi: pelanggan dari pesanan online yang mencantumkan nomor telepon, ditambah pelanggan yang Anda tambahkan sendiri di tab ini. Pembeli di tempat yang tidak meninggalkan nomor telepon tidak otomatis tersimpan di sini. Nomor telepon adalah identitas utamanya: telepon yang sama berarti pelanggan yang sama (namanya diperbarui dari pesanan terbarunya), sedangkan nama sama dengan telepon berbeda dicatat sebagai orang yang berbeda.

> **Tips:** Daftar ini juga menjadi saran nama pelanggan saat membuat pesanan — memilih salah satu sarannya langsung mengisi telepon dan alamat pelanggan (lihat bab [Pesanan](02-pesanan.md)).

> **Catatan:** Daftar piutang pelanggan (buku bon) tidak lagi berada di tab ini — sekarang menjadi tab `Buku Bon` di menu `Laporan`. Lihat bab [Laporan](07-laporan.md).

### Menambah Pelanggan

1. Buka `Pengguna` → tab `Pelanggan` → tombol `Tambah` di kanan atas.
2. Isi data pelanggan pada formulir:
   - `Nama` — nama pelanggan, wajib diisi.
   - `Telepon` — nomor kontak pelanggan (opsional).
   - `Alamat` — alamat pelanggan (opsional).
   - `Catatan` — keterangan tambahan, misalnya preferensi pesanan atau petunjuk lokasi (opsional).
3. Tekan `Buat`. Pelanggan baru langsung muncul di daftar.

### Mengubah Pelanggan

1. Buka `Pengguna` → tab `Pelanggan`, lalu ketuk baris pelanggan yang dituju. Formulir ubah terbuka.
2. Ubah `Nama`, `Telepon`, `Alamat`, atau `Catatan` yang diperlukan, lalu tekan `Simpan`.

### Menghapus Pelanggan

1. Pada tab `Pelanggan`, tekan-lama baris pelanggan yang dituju. Di komputer: klik-kanan barisnya.
2. Tekan `Hapus` lagi pada konfirmasi yang muncul. Pelanggan hilang dari daftar.

> **Catatan:** Menghapus pelanggan tidak mengubah pesanan lama — setiap pesanan menyimpan salinan data pelanggannya sendiri. Pelanggan yang dihapus bisa tercatat kembali bila ada pesanan baru yang mencantumkan nomor teleponnya.

### Mencari Pelanggan

Ketik nama atau nomor telepon pelanggan pada kolom pencarian di atas daftar — daftar tersaring otomatis selagi Anda mengetik. Bila pelanggan lebih dari satu halaman, gunakan navigasi halaman di bagian bawah daftar.

## Peran: Owner dan Staff

- **Owner** (pemilik usaha) — melihat semua pesanan toko dan mengelola menu `Katalog`, `Laporan`, `Pengguna`, serta `Pengaturan`.
- **Staff** (staf penjualan) — hanya melihat menu `Pesanan`, dan daftar pesanannya terbatas pada pesanan yang dibuatnya sendiri (lihat bab [Pesanan](02-pesanan.md)).

Ringkasan aksesnya:

| Menu | Owner | Staff |
|---|---|---|
| `Pesanan` | Semua pesanan toko | Hanya pesanan buatannya sendiri |
| `Katalog` | Bisa | Tidak tampil |
| `Laporan` | Bisa | Tidak tampil |
| `Pengguna` | Bisa | Tidak tampil |
| `Pengaturan` | Bisa | Tidak tampil |

> **Catatan:** Informasi harga beli barang tidak ditampilkan kepada Staff — staf hanya melihat harga jual.

> **Tips:** Cukup simpan sesedikit mungkin akun `Owner` (idealnya satu) dan jadikan semua staf sebagai `Staff`. Bila seorang staf berhenti bekerja, nonaktifkan akunnya alih-alih menghapus, agar pesanan-pesanan lama tetap tertaut pada identitasnya.
