# Produk & Kategori

Katalog adalah tempat Anda mengelola daftar produk, harga, satuan, dan kategori toko. Menu ini hanya tersedia untuk pemilik usaha (owner) — staf tidak melihat menu `Katalog`. Di sini Anda akan menambah produk baru, memperbarui harga, mengatur kategori, serta mengimpor data dari Excel/CSV.

## Membuka Katalog

1. Masuk ke aplikasi dengan akun owner.
2. Pilih menu `Katalog` — di ponsel letaknya di bilah bawah, di komputer di sidebar kiri.
3. Di bagian atas halaman ada lima tab: `Produk`, `Kategori`, `Stok`, `Pemasok`, dan `Opname`. Tab `Stok`, `Pemasok`, dan `Opname` dibahas pada bab [Stok & Pemasok](06-stok.md).

> **Tips:** Tombol `Tambah` di kanan atas halaman berfungsi sebagai jalan pintas — saat Anda berada di tab `Produk` tombol ini membuka formulir produk baru, di tab `Kategori` membuka formulir kategori baru.

## Menambah Produk Baru

1. Buka `Katalog` → tab `Produk` → tombol `Tambah` di kanan atas.
2. Isi data produk pada formulir `Tambah Produk`:
   - `Nama` — wajib diisi, misalnya "Telur Omega Negeri".
   - `SKU` dan `Barcode` — kode identitas produk, boleh dikosongkan.
   - `Kategori` — pilih kategori, atau biarkan `Tanpa kategori`.
   - `Deskripsi` — keterangan tambahan, sifatnya opsional.
3. Untuk foto: pada bagian `Foto Produk`, tekan tombol `Unggah Foto` lalu pilih file foto. Setelah ada foto, tombol berubah menjadi `Ganti Foto`, dan di bawahnya muncul tombol `Hapus Foto`.
4. Lengkapi bagian `Satuan & Harga` (lihat penjelasan satuan di bawah).
5. Pastikan sakelar `Aktif` menyala agar produk langsung bisa dipakai.
6. Tekan tombol `Buat`. Produk baru langsung muncul di daftar.

> **Catatan:** Foto harus berformat PNG, JPG, atau WebP dengan ukuran maksimal 2 MB. Satu produk hanya memiliki satu foto.

## Memahami Satuan & Harga

Satu produk bisa punya beberapa satuan sekaligus — misalnya `pcs` dan `lusinan` — masing-masing dengan harga dan stok sendiri. Cara mengaturnya:

1. Pada formulir produk, setiap satuan tampil sebagai kartu berisi kolom `Satuan`, `Stok awal`, `Notifikasi Min. Stok`, `Harga Jual`, dan `Harga Beli`.
2. Tekan `Tambah Satuan` untuk menambah satuan lain, atau tombol `Hapus` pada kartu satuan untuk menghapusnya.
3. Pilih satuan utama dengan menandai pilihan `Default` pada salah satu kartu. Satuan default dipakai aplikasi saat restok cepat lewat tekan-lama produk dan saat scan barcode.

> **Tips:** `Notifikasi Min. Stok` menentukan kapan satuan ditandai stok rendah — misalnya diisi 5, maka Anda dinotifikasi saat stok menyentuh 5 atau kurang.

> **Catatan:** Kolom `Stok awal` hanya bisa diisi saat membuat produk. Setelah produk ada, stok hanya berubah lewat restok, penerimaan, retur, atau opname — bukan lewat formulir produk.

## Mengubah Produk

1. Buka `Katalog` → tab `Produk`.
2. Ketuk produk yang ingin diubah (pada tampilan kotak maupun daftar). Formulir `Ubah Produk` terbuka.
3. Perubahan apa pun — nama, harga, satuan, foto — cukup diedit langsung, lalu tekan `Simpan`.

> **Tips:** Tekan-lama (atau klik kanan pada komputer) sebuah produk untuk membuka `Restok cepat` tanpa harus masuk ke formulir.

## Menghapus atau Menonaktifkan Produk

Ada dua cara mengeluarkan produk dari peredaran: menghapusnya atau menonaktifkannya.

**Menghapus produk** — produk hilang dari daftar katalog:

1. Buka `Katalog` → tab `Produk`, lalu ketuk produk yang ingin dihapus. Formulir `Ubah Produk` terbuka.
2. Di bagian paling bawah formulir, ketuk tombol `Hapus Produk`.
3. Pada konfirmasi `Hapus produk?` — "Tindakan ini tidak dapat dibatalkan." — ketuk `Hapus` untuk melanjutkan atau `Batal` untuk membatalkan.
4. Muncul notifikasi "Produk dihapus" dan produk hilang dari daftar.

**Menonaktifkan produk** — matikan sakelar `Aktif` pada formulir `Ubah Produk`, lalu tekan `Simpan` — produk tidak lagi dihitung dalam peringatan stok.

> **Catatan:** Nama dan harga produk disimpan secara permanen pada setiap pesanan saat pesanan dibuat. Karena itu riwayat pesanan lama tetap menampilkan nama dan harga yang benar walau produk diubah, dinonaktifkan, atau dihapus.

> **Tips:** Pakai `Hapus Produk` bila produk benar-benar tidak dipakai lagi dan Anda ingin daftar katalog bersih; cukup matikan `Aktif` bila produk hanya berhenti sementara dan mungkin dipakai lagi nanti.

## Mencari dan Menyaring Produk

1. Buka `Katalog` → tab `Produk`.
2. Gunakan kotak pencarian berplaceholder `Cari nama, SKU, barcode...` di bagian atas untuk mencari produk.
3. Saring berdasarkan kondisi stok lewat tab `Semua`, `Stok rendah`, atau `Habis`.
4. Saring berdasarkan kategori lewat tombol kategori di samping kotak pencarian — tombolnya menampilkan `Semua`, dan setelah dipilih menampilkan nama kategori; di dalamnya ada pilihan `Semua Kategori` dan daftar kategori Anda.
5. Tombol ikon paling kanan mengganti tampilan antara kotak dan daftar.

> **Tips:** Pada tampilan daftar, setiap baris menampilkan chip stok per satuan — hijau aman, kuning stok rendah, merah habis.

## Menambah dan Mengubah Kategori

1. Buka `Katalog` → tab `Kategori` → tombol `Tambah` di kanan atas.
2. Isi `Nama` kategori (wajib), `Deskripsi` (opsional), lalu pastikan `Aktif` menyala.
3. Tekan `Buat`. Kategori baru muncul sebagai panel lipat.
4. Untuk mengubah: tekan ikon pensil di header kategori (atau tekan-lama header), edit isinya, lalu tekan `Simpan`.

## Menghapus Kategori

1. Buka `Katalog` → tab `Kategori`.
2. Tekan ikon silang (X) di header kategori yang ingin dihapus.
3. Akan muncul konfirmasi `Hapus kategori?` — produk dalam kategori ini akan dipindahkan ke `Tanpa kategori`. Tekan `Hapus` untuk lanjut atau `Batal` untuk membatalkan.

> **Catatan:** Menghapus kategori tidak menghapus produk. Produknya otomatis pindah ke bagian `Tanpa kategori`.

## Mengelola Isi Kategori

- **Memasukkan produk:** buka kategori dengan mengetuk headernya → tekan `Tambah Produk` di bagian bawah → pilih satu atau beberapa produk (hanya produk `Tanpa kategori` yang tampil) → tekan `Tambah`.
- **Mengeluarkan produk:** di dalam kategori, tekan ikon silang pada baris produk untuk menandainya, lalu tekan `Keluarkan (N)` pada bilah konfirmasi bawah. Produk kembali ke `Tanpa kategori`.
- **Memberi kategori dari Tanpa kategori:** ketuk produk di bagian `Tanpa kategori` → pilih kategori tujuan pada panel `Pilih Kategori` → tekan `Pindahkan`.

> **Tips:** Angka pada header setiap kategori menunjukkan jumlah produk di dalamnya.

## Impor Produk dari Excel/CSV

Jika data produk dan pesanan Anda sudah ada di Excel atau Google Sheets, Anda tidak perlu mengetik satu per satu. Fitur impor berada di `Pengaturan` → tab `Operasi` → kartu `Import dari Excel` (detail lengkapnya di bab [Pengaturan Operasi](10-pengaturan-operasi.md)).

1. Unduh contoh format dengan menekan `Unduh template Excel` (file bernama `orders-import-template.xlsx`).
2. Isi template dengan dua tab: tab `Products` berisi kolom `Product Name`, `Unit`, `COGS`, dan `Selling Price` — satu baris per satuan produk, dan baris tanpa `Selling Price` dilewati. Tab `Orders` (opsional) berisi kolom `Date`, `Customer Name`, `Product Name`, `Qty`, `Unit`, `Status`, dan `Delivery`.
3. Simpan file sebagai `.xlsx` atau `.csv`.
4. Kembali ke kartu `Import dari Excel`, pilih file pada kolom `File import`, lalu tekan `Upload & Import`.
5. Hasil impor tampil di bawah tombol: `Total`, `Berhasil`, dan `Gagal`. Untuk file besar (lebih dari 100 baris), impor dijadwalkan dan diproses di latar belakang.

> **Catatan:** Produk hasil impor otomatis dikelompokkan ke kategori `Import`. Nama kolom bahasa Indonesia seperti `Nama Produk`, `Satuan`, `Harga Beli`, dan `Harga Jual` juga dikenali.

> **Tips:** Jika data Anda tinggal di Google Sheets, Anda bisa memakai tombol `Import dari Google Sheets` pada kartu `Google Sheets` di halaman yang sama — tanpa perlu mengunggah file.
