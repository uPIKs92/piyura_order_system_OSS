# Pengaturan: Operasi

Sub-menu `Operasi` adalah ruang kendali untuk hal-hal teknis di balik aplikasi: pajak PPN, pengaturan pajak PPh dan NPWP, tarif pengiriman (ongkir), peringatan kedaluwarsa stok, integrasi Google Sheets, backup data toko, serta impor data dari Excel atau Google Sheets. Seluruh pengaturan di halaman ini hanya bisa diakses dan diubah oleh **Owner**.

1. Login sebagai Owner.
2. Buka `Pengaturan` → `Operasi`.

Halaman ini terdiri atas beberapa kartu pengaturan yang dijelaskan satu per satu di bawah. Semua kartu tampil terlipat secara bawaan — ketuk judul kartu untuk membukanya, dan ketuk lagi untuk melipatnya.

## Mengaktifkan dan Mengatur PPN

PPN (Pajak Pertambahan Nilai) dihitung otomatis pada pesanan baru sesuai persentase yang Anda tetapkan. Secara bawaan, PPN aktif dengan tarif 11%.

1. Buka `Pengaturan` → `Operasi` → kartu `Pengaturan PPN`.
2. Gunakan tombol geser `PPN aktif` untuk menyalakan atau mematikan PPN.
3. Isi `Persentase PPN (%)` dengan tarif yang Anda pakai, misalnya `11`. Angka boleh berkoma (0–100).
4. Ketuk `Simpan PPN`.
5. Muncul notifikasi "Pengaturan PPN disimpan".

> **Catatan:** Perubahan ini berlaku untuk pesanan baru. Nilai PPN tampil sebagai baris `PPN (…%)` pada detail pesanan, invoice, dan struk — lihat bab `04-faktur-dan-cetak.md` — serta terangkum pada laporan pajak di bab `07-laporan.md`.

> **Tips:** Pesanan lama tetap memakai tarif PPN saat pesanan itu dibuat, jadi mengubah tarif tidak mengubah catatan lama Anda.

## Pengaturan Pajak (NPWP & PPh)

Kartu `Pajak`, tepat di bawah kartu `Pengaturan PPN`, mengatur identitas dan mode pajak penghasilan (PPh) toko Anda yang dipakai pada laporan pajak.

1. Buka `Pengaturan` → `Operasi` → kartu `Pajak`.
2. Isi `NPWP` dengan nomor NPWP toko Anda, contoh formatnya `12.345.678.9-012.345`. Kolom ini boleh dikosongkan bila belum ada.
3. Pilih `Mode PPh` sesuai kondisi usaha Anda:
   - `UMKM Non-PKP (0,5% / 12%)` — untuk usaha kecil yang belum menjadi PKP. PPh final 0,5% dari omzet setahun hingga Rp 500.000.000; bagian omzet di atasnya dikenai 12%.
   - `UMKM PKP (PPh 22 2,5%)` — untuk usaha yang sudah menjadi PKP. PPh dihitung 2,5% dari omzet tiap bulan.
4. Ketuk `Simpan Pajak`.
5. Muncul notifikasi "Pengaturan pajak disimpan".

> **Catatan:** Sesuai keterangan pada kartunya, "Mode PPh dan NPWP dipakai pada perhitungan pajak di tab Pajak pada Laporan" — hasilnya dilihat pada menu `Laporan` → tab `Pajak`, lihat bab `07-laporan.md`.

## Mengatur Pengiriman (Ongkir)

Kartu `Pengiriman`, tepat di bawah kartu `Pajak`, mengatur tarif ongkir untuk pesanan yang metode pengirimannya `Diantar`. Pesanan `Diambil` tidak dikenai ongkir.

1. Buka `Pengaturan` → `Operasi` → kartu `Pengiriman`.
2. Pilih `Mode Tarif`:
   - `Per Kilometer` — ongkir dihitung dari jarak: isi `Tarif per km (Rp)`, dan boleh isi `Ongkir minimum (Rp)` sebagai batas bawah untuk jarak dekat (isi `0` bila tidak dipakai).
   - `Tarif Tetap` — ongkir sama untuk semua jarak: isi `Tarif tetap (Rp)`.
3. Ketuk `Simpan Pengiriman`.
4. Muncul notifikasi "Pengaturan pengiriman disimpan".

> **Tips:** Kebiasaan pasar umumnya Rp 3.000–10.000 per km (paling sering Rp 5.000/km), dengan ongkir minimum sekitar Rp 10.000 untuk jarak dekat. Ongkir tidak dikenai PPN.

> **Catatan:** Tarif di sini dipakai saat pesanan dibuat atau drafnya diedit. Pesanan yang sudah dikirim memakai ongkir yang sudah tersimpan, jadi mengubah tarif tidak mengubah pesanan lama.

### Hitung Ongkir Otomatis (OpenStreetMap)

Pada pesanan, tombol `Hitung Ongkir` memperkirakan jarak dan ongkir otomatis dari alamat pelanggan memakai layanan publik OpenStreetMap — tanpa API key dan tanpa langkah tambahan apa pun dari Anda.

1. Buka `Pengaturan` → `Operasi` → kartu `Pengiriman`.
2. Ketuk `Tes Koneksi` untuk memeriksa konektivitas layanan. Bila berhasil, muncul notifikasi "Koneksi OpenStreetMap berhasil."; bila gagal, muncul pesan penyebabnya (misalnya koneksi internet bermasalah).

> **Catatan:** Titik asal pengiriman adalah pin `Lokasi Toko di Peta` pada `Pengaturan` → `Profil` bila sudah diatur — cara ini paling disarankan karena jarak dihitung langsung dari titik yang Anda pilih sendiri (panduannya di bab `09-pengaturan-profil-tampilan.md`). Bila belum diatur, aplikasi memakai alamat teks toko Anda dan menerjemahkannya menjadi titik peta secara otomatis. Tombol `Tes Koneksi` juga menguji dari pin tersebut bila ada.

> **Tips:** Layanan publik kadang sedang sibuk atau gangguan. Bila perkiraan otomatis pada pesanan gagal, cukup isi jarak secara manual pada panel ongkir pesanan (mode `Jarak manual`) — ongkir tetap dihitung dari tarif yang sudah diatur.

> **Tips:** Di pesanan, alamat tujuan juga bisa dipilih langsung lewat peta agar perkiraan jaraknya lebih tepat — caranya ada di bab `02-pesanan.md`.

## Mengatur Peringatan Kedaluwarsa Stok

Anda bisa menentukan batas hari sebelum tanggal kedaluwarsa untuk menandai batch barang sebagai "segera kedaluwarsa".

1. Buka `Pengaturan` → `Operasi` → kartu `Peringatan Kedaluwarsa`.
2. Isi `Batas peringatan (hari)` dengan jumlah hari, misalnya `30` (boleh 1–365).
3. Ketuk `Simpan kedaluwarsa`.
4. Muncul notifikasi "Pengaturan kedaluwarsa disimpan".

> **Catatan:** Batch dengan sisa umur di bawah batas ini akan ditandai pada menu stok — selengkapnya di bab `06-stok.md`.

## Menghubungkan Google Sheets

Integrasi Google Sheets memakai akun Google Anda sendiri melalui izin resmi Google (OAuth). Sekali terhubung, Anda bisa mengekspor data penjualan ke spreadsheet dan mengimpor data lama dari spreadsheet.

1. Buka `Pengaturan` → `Operasi` → kartu `Google Sheets`.
2. Pada bagian `Status koneksi`, ketuk `Hubungkan Google`.
3. Layar Google akan terbuka. Masuk dengan akun Google Anda dan izinkan akses ke Google Sheets.
4. Setelah berhasil, Anda kembali ke halaman ini dan status berubah menjadi "Terhubung" disertai email akun Google Anda.

Untuk memutuskan hubungan:

1. Pada bagian `Status koneksi`, ketuk `Putuskan`.
2. Status kembali menjadi "Belum terhubung".

> **Catatan:** Sinkronisasi baru berjalan setelah Google terhubung, `Spreadsheet ID` terisi, dan tombol `Sync order aktif` dinyalakan.

## Mengatur Spreadsheet dan Tab

Data dikirim ke (dan dibaca dari) satu file Google Sheets milik Anda. Anda perlu menentukan file tersebut beserta nama tab-nya.

1. Buka `Pengaturan` → `Operasi` → kartu `Google Sheets`.
2. Isi `Spreadsheet ID` dengan ID dari URL Google Sheets Anda, contoh: bagian setelah `/spreadsheets/d/` pada alamat `docs.google.com/spreadsheets/d/...`.
3. Sesuaikan nama tab bila perlu:
   - `Tab Products` — tab daftar produk (bawaan: `Products`).
   - `Tab Orders` — tab daftar pesanan (bawaan: `Orders`).
   - `Tab Reporting` — tab tujuan laporan penjualan (bawaan: `Reporting`).
4. Nyalakan `Sync order aktif` agar pesanan dikirim otomatis ke spreadsheet.
5. Ketuk `Simpan integrasi`.

> **Tips:** Buat dulu Google Sheets dengan tab `Products`, `Orders`, dan `Reporting`, lalu salin ID-nya dari address bar browser.

## Menjalankan Sinkronisasi ke Google Sheets

Bila `Sync order aktif` menyala, pesanan baru dan yang berubah status otomatis masuk antrean dan dikirim ke tab `Reporting` setiap beberapa menit — tanpa data pribadi pelanggan. Anda juga bisa memaksa pengiriman sekarang:

1. Pastikan Google sudah terhubung, `Spreadsheet ID` terisi, dan `Sync order aktif` menyala.
2. Ketuk `Sinkronkan Sekarang`.
3. Muncul notifikasi berapa order yang berhasil dikirim.

> **Catatan:** Bila tombol `Sinkronkan Sekarang` tidak bisa ditekan, berarti salah satu syarat di atas belum terpenuhi. Bila antrean ada, di bawah tombol `Sync order aktif` tampil jumlah "order menunggu sinkronisasi".

## Mengimpor Data Lama dari Google Sheets

Bila data lama Anda ada di Google Sheets dengan tab `Products` dan `Orders` sesuai format, Anda bisa menariknya langsung ke aplikasi:

1. Hubungkan Google dan isi `Spreadsheet ID` (lihat bagian di atas).
2. Ketuk `Import dari Google Sheets`.
3. Tunggu proses selesai; muncul notifikasi "Import Sheets selesai" dengan jumlah baris yang berhasil.

> **Tips:** Produk diimpor lebih dulu, baru pesanan — pastikan tab `Products` terisi benar karena pesanan merujuk ke nama produk dan satuannya.

## Menangani Sinkronisasi yang Gagal

Kartu `Sheets Sync Gagal` menampilkan pesanan yang gagal dikirim ke Google Sheets, lengkap dengan pesan errornya. Kartu ini terbuka sendiri bila ada order yang gagal sinkron. Setelah perbaikan penyebabnya (misalnya spreadsheet dihapus atau diganti nama), Anda bisa mengirim ulang:

1. Buka `Pengaturan` → `Operasi` → kartu `Sheets Sync Gagal`.
2. Temukan pesanan yang gagal pada daftar.
3. Ketuk `Coba lagi` di baris pesanan tersebut.
4. Pesanan dijadwalkan ulang dan hilang dari daftar. Bila semua beres, tampil tulisan "Tidak ada sync gagal".

## Backup dan Ekspor Data Toko

Kartu `Ekspor Data Tenant` mengunduh seluruh data toko Anda menjadi satu berkas JSON — semacam foto lengkap seluruh isi aplikasi pada hari itu. Kartu ini tidak bisa dilipat dan selalu tampil terbuka.

1. Buka `Pengaturan` → `Operasi` → kartu `Ekspor Data Tenant`.
2. Ketuk `Ekspor data`.
3. Berkas `tenant-export-…json` terunduh ke perangkat Anda. Isinya mencakup profil toko, pengaturan PPN dan integrasi, daftar pengguna, kategori, produk beserta satuannya, serta semua pesanan beserta rinciannya.

> **Tips:** Lakukan ekspor ini secara rutin (misalnya tiap akhir bulan) dan simpan berkasnya di tempat aman seperti Google Drive atau flashdisk.

> **Catatan:** Aplikasi tidak memiliki tombol untuk memuat kembali berkas JSON ini dari halaman Pengaturan — berkas ini berfungsi sebagai cadangan data. Untuk memasukkan data ke aplikasi, gunakan `Import dari Excel` di bawah.

## Mengimpor Data dari Excel

Kartu `Import dari Excel` adalah cara cadangan untuk memasukkan data dari berkas Excel atau CSV (berisi tab `Products` + `Orders`), tanpa perlu menghubungkan Google.

1. Buka `Pengaturan` → `Operasi` → kartu `Import dari Excel`.
2. Ketuk `Unduh template Excel` untuk mendapat berkas contoh formatnya.
3. Salin data Anda ke template, lalu simpan sebagai .xlsx atau .csv.
4. Pilih berkas pada kolom `File import`.
5. Ketuk `Upload & Import`.
6. Setelah selesai, tampil ringkasan `Total`, `Berhasil`, dan `Gagal` baris.

> **Catatan:** Berkas berukuran besar diproses di latar belakang — muncul notifikasi "Import dijadwalkan" beserta jumlah barisnya, dan ringkasan hasil menyusul. Baris yang gagal biasanya karena nama produk/satuan tidak cocok dengan data di aplikasi. Hasil impor produk Anda bisa diperiksa di menu `Katalog` — lihat bab `05-produk-dan-kategori.md`.

> **Tips:** Laporan pajak (PPN keluaran dan PPh Final) dilihat dan diunduh dari menu `Laporan` → tab `Pajak`, lihat bab `07-laporan.md`. Tarif PPN-nya diatur pada kartu `Pengaturan PPN`, sedangkan mode PPh dan NPWP pada kartu `Pajak` di atas.
