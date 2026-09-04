# Stok & Pemasok

Bab ini membahas cara memantau dan mengatur stok: menerima barang dari pemasok, memantau batch dan kedaluwarsa, mencocokkan stok lewat opname, serta mengelola daftar pemasok. Semua fitur ini berada di dalam menu `Katalog` (khusus owner) pada tab `Stok`, `Pemasok`, dan `Opname`.

## Melihat Ringkasan Stok

1. Buka `Katalog` → tab `Stok`.
2. Tiga kartu di bagian atas menampilkan `Total SKU` (jumlah produk), `Stok Rendah` (satuan di bawah batas minimum), dan `Habis` (satuan dengan stok nol).
3. Di bawahnya tersedia dua tombol: `Restok cepat` dan `Penerimaan supplier`.

> **Tips:** Tarik ke bawah halaman untuk menyegarkan angka ringkasan.

## Menanggapi Peringatan Stok dan Kedaluwarsa

Di sidebar (komputer), pemilik usaha melihat dua tombol notifikasi: ikon bel untuk `Stok Rendah` dan ikon jam-kalender untuk `Kedaluwarsa`. Angka merah pada ikon menunjukkan jumlah peringatan.

1. Tekan ikon bel untuk membuka panel `Stok Rendah`. Setiap baris menampilkan nama produk, satuan, dan lencana `stok/batas minimum`. Tekan `Restok` pada baris untuk langsung menambah stok produk tersebut.
2. Tekan ikon jam-kalender untuk membuka panel `Peringatan Kedaluwarsa`. Panel ini terbagi dua bagian: `Kedaluwarsa` dan `Segera kedaluwarsa`, lengkap dengan jumlah sisa hari dan kuantitas tiap batch.
3. Batas "segera kedaluwarsa" (default 30 hari) dapat diubah di `Pengaturan` → `Operasi` → kartu `Peringatan Kedaluwarsa` → kolom `Batas peringatan (hari)`.

## Menambah Stok dengan Restok Cepat

Gunakan `Restok cepat` untuk menambah stok satu satuan produk dalam hitungan detik.

1. Buka `Katalog` → tab `Stok` → tombol `Restok cepat`. (Alternatif: tekan-lama produk di tab `Produk`, atau tombol `Restok` di panel `Stok Rendah`.)
2. Cari produk lewat kolom `Cari produk`, atau isi kolom `Scan barcode` dengan kode barcode lalu tekan Enter.
3. Setelah produk terpilih, kartu kecil menampilkan satuan dan `stok saat ini`.
4. Isi `Jumlah tambah` — gunakan tombol preset `+1`, `+5`, `+10` atau ketik angka.
5. Jika perlu, isi `Kedaluwarsa (opsional)`, `No. batch (opsional)` (contoh: B-2401), dan `Catatan (opsional)`.
6. Tekan `Simpan`. Stok satuan tersebut langsung bertambah dan tercatat di riwayat.

> **Catatan:** Restok cepat selalu bekerja pada satu satuan spesifik (produk + satuan). Jika produk punya beberapa satuan, pastikan satuan yang tampil di kartu adalah yang benar.

## Mencatat Penerimaan dari Pemasok

`Penerimaan supplier` mencatat barang masuk sekaligus menyimpan nomor penerimaan otomatis — cocok untuk mencocokkan nota belanja dari pemasok.

1. Buka `Katalog` → tab `Stok` → tombol `Penerimaan supplier`.
2. Pilih `Supplier (opsional)` dari daftar, atau pilih `— Ketik manual —` lalu isi nama pada kolom `Nama supplier...`. Boleh juga dikosongkan.
3. Isi `Catatan` bila perlu (misalnya nomor nota pemasok).
4. Pada kartu `Tambah baris`: pilih produk, isi jumlah, lalu tekan `Tambah`. Baris produk muncul di daftar; ulangi untuk produk lain. Baris dengan satuan, kedaluwarsa, atau batch berbeda akan tetap terpisah.
5. Isi `Kedaluwarsa (ops.)` dan `No. batch (ops.)` sebelum menekan `Tambah` bila ingin mencatat batch.
6. Tekan `Simpan`. Muncul konfirmasi `Penerimaan RCV-...` berisi nomor penerimaan yang dibuat otomatis (format RCV-tanggal-nomor).

> **Catatan:** Tidak ada kolom tanggal penerimaan — tanggal tercatat otomatis saat Anda menyimpan. Setiap baris langsung menambah stok satuan terkait.

## Memahami Batch dan Kedaluwarsa

Batch adalah kelompok barang yang masuk bersamaan, biasanya dengan nomor batch atau tanggal kedaluwarsa yang sama. Batch terbentuk otomatis setiap kali Anda restok atau menerima barang dengan isian kedaluwarsa/batch.

1. Buka `Katalog` → tab `Stok` → kartu `Batch`. Header kartu menampilkan `Batch aktif` beserta jumlahnya.
2. Daftar dikelompokkan per produk dan satuan dengan lencana jumlah batch di kanan.
3. Ketuk baris produk untuk membuka rincian batch: nomor batch (atau `Tanpa no.`), tanggal kedaluwarsa (atau `Tanpa kedaluwarsa`), kuantitas (`×` jumlah), dan lencana status: `Aman`, sisa hari (misal `12 hari`), atau `Kedaluwarsa`.

> **Tips:** Saat penjualan terjadi, aplikasi mengeluarkan stok dari batch yang kedaluwarsa lebih dulu (FEFO), sehingga barang lama terjual lebih dahulu.

## Menulis Rusak Batch (Write-off)

Gunakan write-off untuk memusnahkan batch yang sudah tidak layak jual — rusak, kedaluwarsa, atau terkontaminasi.

1. Buka panel `Peringatan Kedaluwarsa` dari ikon jam-kalender di sidebar.
2. Pada batch yang bermasalah, tekan tombol `Write-off`.
3. Muncul konfirmasi `Tulis rusak batch ini?` — kuantitas batch akan dinolkan dan pergerakan stok write-off dicatat. Isi `Alasan (opsional)` bila perlu (contoh: rusak, kedaluwarsa).
4. Tekan `Write-off` untuk mengeksekusi. Stok batch berkurang menjadi nol dan tercatat sebagai `Write-off` di riwayat.

> **Catatan:** Write-off tidak dapat dibatalkan. Pastikan jumlah batch memang benar-benar tidak bisa dijual.

## Membaca Riwayat Pergerakan Stok

1. Buka `Katalog` → tab `Stok` → kartu `Riwayat`.
2. Setiap baris menampilkan nama produk, satuan, jenis pergerakan, waktu, lencana perubahan (+ atau −), serta angka sebelum → sesudah.
3. Jenis pergerakan yang bisa muncul: `Penjualan`, `Retur`, `Restok`, `Penerimaan supplier`, `Penyesuaian`, `Batal pesanan`, `Hapus pesanan`, dan `Write-off`.
4. Gunakan tombol `Sebelumnya` dan `Berikutnya` di bawah daftar untuk berpindah halaman.

## Stok Opname (Penyesuaian Hasil Hitung Fisik)

Opname adalah mencocokkan stok aplikasi dengan hitungan fisik di gudang atau etalase. Gunakan fitur ini secara berkala agar laporan stok dan nilai persediaan tetap akurat.

1. Buka `Katalog` → tab `Opname`.
2. Pada kartu `Pilih produk`, cari produk yang ingin dihitung lalu tekan `Tambah`. Produk muncul sebagai baris dengan angka `Sistem:` sebagai pembanding.
3. Isi kolom `Hitung fisik` dengan jumlah hasil hitungan nyata. Lencana selisih langsung muncul: `Sesuai`, `+N` (fisik lebih banyak), atau merah `-N` (fisik kurang).
4. Ulangi untuk produk lain, isi `Catatan (opsional)` bila perlu, lalu tekan `Simpan Opname`.
5. Kartu `Hasil opname` menampilkan ringkasan `Sistem X → Hitung Y` per produk. Stok aplikasi otomatis disesuaikan mengikuti angka hitungan Anda.

> **Tips:** Lakukan opname saat toko tutup atau sepi agar tidak ada barang berpindah saat menghitung. Perbedaan yang tercatat sebagai `Penyesuaian` memudahkan pelacakan kebocoran stok.

## Mengelola Pemasok

1. Buka `Katalog` → tab `Pemasok` → tombol `Tambah` di kanan atas.
2. Isi formulir `Tambah Pemasok`: `Nama` (wajib), `Telepon`, `Alamat`, dan `Catatan`, lalu tekan `Buat`.
3. Untuk mengubah: tekan `Ubah` di baris pemasok, edit datanya, lalu tekan `Simpan`.
4. Untuk menghapus: tekan `Hapus` di baris pemasok, lalu konfirmasi `Hapus pemasok?`. Pemasok tidak akan lagi muncul di penerimaan stok.

> **Tips:** Pemasok yang tersimpan otomatis muncul sebagai pilihan di formulir `Penerimaan supplier`, sehingga riwayat barang masuk bisa dilacak per pemasok.

## Catatan: Stok Berubah Otomatis

Anda tidak perlu mengurangi stok secara manual saat ada transaksi — aplikasi yang mengaturnya:

- Stok **berkurang** otomatis saat pesanan dibuat (`Penjualan`).
- Stok **kembali** saat pesanan dibatalkan (`Batal pesanan`) atau dihapus (`Hapus pesanan`).
- Saat retur dibuat untuk pesanan berstatus selesai, barang retur **masuk kembali** ke stok (`Retur`).
- Perubahan manual hanya terjadi lewat restok, penerimaan supplier, opname, dan write-off.

> **Catatan:** Karena nama dan harga produk disimpan permanen di setiap pesanan, riwayat pesanan lama tidak berubah walau stok atau harga produk saat ini sudah berbeda.
