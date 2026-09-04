# Pesanan

Halaman `Pesanan` adalah pusat kerja Anda sehari-hari: membuat pesanan, memantau status, mencatat pembayaran, hingga membatalkan pesanan. Buka tab `Pesanan` di bilah bawah (ponsel) atau menu samping kiri (komputer).

## Ringkasan di Atas Daftar

Pada komputer, tiga kartu ringkasan tampil di atas daftar pesanan untuk periode bulan dan tahun yang sedang dipilih filter:

- `Total Pesanan` — jumlah pesanan pada periode tersebut.
- `Omzet` — total nilai pesanan, contoh: Rp 1.500.000.
- `Belum Dibayar` — total uang yang belum diterima, plus jumlah pesanan yang belum lunas (misal "3 pesanan").

> **Catatan:** Kartu ringkasan hanya tampil di tampilan komputer/layar lebar. Di ponsel, Anda tetap bisa melihat sisa tagihan tiap pesanan dari label pembayarannya.

## Mencari dan Memfilter Pesanan

1. Ketik nomor invoice atau nama pelanggan pada kolom pencarian `Cari invoice / customer...`.
2. Ketuk tombol `Filter` di samping kolom pencarian untuk membuka panel `Filter`:
   - `Bulan` — pilih salah satu bulan atau `Semua bulan`.
   - `Tahun` — pilih tahun atau `Semua tahun`.
   - `Urutkan` — pilihan antara lain `Tanggal terbaru`, `Tanggal terlama`, `Total tertinggi`, `Total terendah`, `Invoice terbaru`, dan `Invoice terlama`.
3. Ketuk `Terapkan` untuk menyaring, atau `Reset` untuk kembali ke bawaan (bulan dan tahun berjalan, urut `Tanggal terbaru`).
4. Di bawah kolom pencarian ada chip status untuk menyaring cepat: `Semua`, `Draft`, `Menunggu`, `Diproses`, `Dikirim`, `Selesai`, `Batal`. Angka kecil di chip menunjukkan jumlah pesanan berstatus tersebut.

> **Tips:** Tombol `Filter` menampilkan badge angka bila ada pengaturan yang berbeda dari bawaan, jadi Anda langsung tahu filter sedang aktif.

## Mengatur Jumlah Baris per Halaman

Jika pesanan lebih dari satu halaman, di bagian bawah daftar tampil informasi seperti "1–20 dari 57".

1. Ketuk angka di samping informasi tersebut untuk memilih jumlah baris per halaman: `20`, `50`, atau `100`.
2. Gunakan tombol `Sebelumnya` dan `Berikutnya` untuk berpindah halaman.

## Membuat Pesanan

1. Ketuk tombol `Baru` di kanan atas judul `Pesanan`.
2. Cari produk pada kolom `Cari produk...`, atau saring dengan tombol kategori di sampingnya. Ketuk produk untuk menambahkannya ke keranjang; ketuk lagi untuk menambah jumlah.
3. Di ponsel, ketuk bilah keranjang di bawah (menampilkan jumlah item dan total) untuk membuka `Review Pesanan`. Di komputer, panel review sudah tampil di sisi kanan.
4. Buka bagian `+ Tambah Pelanggan` lalu isi:
   - `Nama Pelanggan` — saat mengetik, muncul saran dari direktori pelanggan toko, yaitu pelanggan dengan nomor telepon (lihat bab [Pengguna](08-pengguna.md)). Tiap baris saran menampilkan nama beserta telepon dan alamatnya; memilih satu baris mengisi `Nama`, `Telepon`, dan `Alamat` sekaligus. Tamu yang dipesan di tempat — tanpa telepon — cukup mengetik namanya dan tidak otomatis tersimpan ke direktori.
   - `Telepon` — nomor kontak pelanggan (opsional).
   - `Tanggal Pesanan` — otomatis terisi hari ini; tidak boleh tanggal masa depan.
   - `Catatan` — keterangan tambahan (opsional).
5. Atur tiap item: gunakan tombol +/− untuk jumlah, ketuk ikon tag untuk memberi `Diskon` per item dalam `Rp` atau `%`. Item bisa dihapus lewat ikon tempat sampah, atau dengan geser kiri pada baris item lalu `Hapus`.
6. Ketuk `Buat Pesanan` untuk menyimpan dan langsung mengirimnya.

Setelah `Buat Pesanan`, muncul notifikasi "Pesanan dibuat" dan layar `Tambah Pembayaran` terbuka untuk mencatat pembayaran (lihat `03-pembayaran.md`).

> **Catatan:** Harga dan stok bersifat snapshot: harga produk tersimpan pada saat pesanan dikirim (bukan saat harga katalog berubah), dan stok produk baru berkurang saat pesanan dikirim dari Draft. Jika pesanan dibatalkan setelah dikirim, stok dikembalikan otomatis.

## Mengatur Pengiriman dan Ongkir

Di dalam bagian `+ Tambah Pelanggan` pada panel `Review Pesanan`, ada pilihan `Pengiriman`:

- `Diambil` (bawaan) — pelanggan mengambil sendiri pesanannya; tidak ada ongkir.
- `Diantar` — pesanan diantar dan dikenai ongkir sesuai tarif yang diatur Owner (lihat `10-pengaturan-operasi.md`).

Bila memilih `Diantar`:

1. Isi `Alamat` tujuan pengiriman — ketik langsung, atau ketuk ikon pin peta di samping label `Alamat` untuk memilih lokasinya lewat peta (lihat `Memilih Lokasi lewat Peta` di bawah). Alamat boleh dikosongkan bila sudah hafal, tetapi dibutuhkan untuk mode `Otomatis` di bawah.
2. Ketuk `Atur Ongkir` — atau ketuk chip ringkasan `Ongkir Rp X · Y km` bila ongkirnya sudah terisi — untuk membuka panel `Ongkir`.
3. Pilih salah satu mode penghitungan ongkir pada panel:
   - `Otomatis` — ketuk `Hitung Ongkir`; aplikasi memperkirakan jarak dari alamat — teks atau titik peta — memakai OpenStreetMap lalu menghitung tarifnya (perlu `Alamat` terisi atau lokasi dipilih lewat peta; tanpa API key). Bila gagal — misalnya alamat tidak dikenali atau layanan sedang sibuk — pesan error muncul dan Anda bisa beralih ke mode manual.
   - `Jarak manual` — isi kolom jarak (km) sendiri; tarif langsung tampil sesuai pengaturan Owner (lihat `10-pengaturan-operasi.md`).
   - `Tarif manual` — isi nominal ongkirnya langsung, misalnya untuk memberi keringanan atau tarif khusus.
4. Tutup panel dengan tombol X. Baris `Ongkir` beserta nominalnya tetap tampil di luar panel, dan baris total berubah menjadi `Total (termasuk ongkir)`: total item setelah diskon ditambah ongkir. PPN tetap dihitung oleh aplikasi dan tidak dikenakan pada ongkir.

Kapan pakai mode yang mana: `Otomatis` saat alamat sudah terisi dan jarak ingin diperkirakan sendiri; `Jarak manual` saat jarak sudah diketahui atau perkiraan otomatisnya gagal; `Tarif manual` saat nominal ongkir ingin ditetapkan sendiri tanpa menghitung jarak.

### Memilih Lokasi lewat Peta

Bila alamat pelanggan sulit dijelaskan dengan teks, pilih titiknya langsung di peta:

1. Pada bagian `+ Tambah Pelanggan`, ketuk ikon pin peta di samping label `Alamat`.
2. Peta terbuka — memakai peta OpenStreetMap. Saat peta dibuka dan belum ada pin tersimpan, browser dapat meminta izin lokasi; bila diizinkan, peta langsung melompat ke posisi perangkat Anda. Geser peta sampai titik silang di tengah layar berada tepat di lokasi pelanggan. Kapan pun dibutuhkan, tombol `Lokasi Saya` dapat melompat ke posisi perangkat Anda.
3. Ketuk `Pakai Lokasi Ini`.
4. Kolom `Alamat` terisi otomatis dengan hasil pembacaan lokasi tersebut — teksnya tetap bisa Anda sunting.

> **Catatan:** Selama teks alamat hasil peta belum Anda ubah, tombol `Hitung Ongkir` memakai koordinat persis titik pin tersebut, sehingga perkiraannya lebih tepat. Begitu teks alamatnya diedit manual, titik pin dilepas dan aplikasi kembali memakai teks alamat.

> **Tips:** Peta memerlukan koneksi internet. Bila jaringan lambat hingga peta tampak kosong, titik silang dan tombol `Pakai Lokasi Ini` tetap berfungsi — yang tercatat adalah lokasi tepat di tengah peta.

> **Catatan:** Saat pertama memakai `Lokasi Saya`, browser akan meminta izin — pilih `Izinkan`. Jika izinnya pernah ditolak, berikan ulang lewat ikon kunci di address bar (`Izinkan Lokasi`) lalu muat ulang halaman. Fitur ini membutuhkan HTTPS dan layanan lokasi perangkat yang aktif; bila tetap gagal, geser peta secara manual ke posisi Anda — titik silang tetap berfungsi.

> **Catatan:** Mengganti mode mengosongkan isian mode yang lain, jadi nilai lama tidak tersimpan diam-diam.

Setelah pesanan dibuat, detail pesanan menampilkan baris `Pengiriman:` (`Diantar` beserta jaraknya, atau `Diambil`), baris `Ongkir` pada rincian tagihan, serta alamat dan ongkir pada struk yang dicetak.

> **Catatan:** Metode pengiriman, jarak, dan ongkir hanya bisa diubah selama pesanan masih `Draft` — sama seperti daftar item. Kolom `Alamat` tetap bisa diubah kapan saja karena termasuk data pelanggan. Akibatnya, ongkir yang lupa diisi pada pesanan yang sudah dikirim tidak bisa ditambahkan lagi — bila perlu menagihnya, buat pesanan baru khusus ongkirnya.

> **Tips:** Bila perkiraan otomatis gagal (misalnya alamat tidak dikenali), beralihlah ke mode `Jarak manual` dan isi jaraknya sendiri — ongkir tetap dihitung dari tarif per km.

## Menyimpan sebagai Draft

Jika pesanan belum siap dikirim, ketuk `Simpan Draft` alih-alih `Buat Pesanan`. Pesanan tersimpan berstatus `Draft`, terlihat di daftar dengan chip `Draft`, dan bisa dilanjutkan kapan saja:

1. Ketuk pesanan berstatus `Draft` di daftar.
2. Form `Ubah Pesanan` terbuka — lengkapi item dan data pelanggan.
3. Ketuk `Kirim Pesanan` untuk mengirimkannya (stok mulai berkurang saat ini).

## Draf Otomatis (Auto-Save)

Saat Anda mengetik di form pesanan, isian tersimpan otomatis setiap beberapa detik — meski aplikasi tertutup tanpa sengaja. Jika Anda membuka form pesanan baru dan ada isian yang belum tersimpan, muncul pita "Draf tersimpan ditemukan":

- Ketuk `Pulihkan` untuk melanjutkan isian terakhir (muncul notifikasi "Draft dipulihkan").
- Ketuk `Buang` untuk memulai dari awal.

## Mengubah Pesanan

1. Ketuk pesanan di daftar untuk membuka detailnya.
2. Ketuk tombol `Ubah`.
3. Pada form `Ubah Pesanan`, Anda bisa mengubah `Nama Pelanggan`, `Telepon`, `Tanggal Pesanan`, `Catatan`, serta item dan diskonnya.
4. Ketuk `Simpan`.

> **Catatan:** Perubahan daftar item hanya tersimpan selama pesanan masih `Draft`, termasuk metode pengiriman dan ongkirnya (alamat tetap bisa diubah — lihat `Mengatur Pengiriman dan Ongkir`). Setelah dikirim, yang bisa diubah hanya data pelanggan, tanggal, dan catatan. Setiap perubahan tanggal tercatat di `Riwayat Tanggal`, dan status tidak diubah dari form ini — melainkan dari tampilan detail pesanan.

## Alur Status Pesanan

Perjalanan pesanan mengikuti urutan: `Draft` → `Menunggu` → `Diproses` → `Dikirim` → `Selesai`. Status `Batal` menandakan pesanan dibatalkan. Setiap status tampil sebagai badge pada baris pesanan, dan titik-titik kemajuan (stepper) tampil di detail pesanan.

Cara maju status, dari detail pesanan (ketuk pesanan di daftar):

1. Status `Draft` → ketuk `Kirim Pesanan` untuk menjadi `Menunggu`.
2. Status `Menunggu` → ketuk `Mulai Proses` untuk menjadi `Diproses`.
3. Status `Diproses` → ketuk `Tandai Dikirim` untuk menjadi `Dikirim`.
4. Status `Dikirim` → ketuk `Selesaikan` untuk menjadi `Selesai`.

Di komputer, tombol yang sama juga tersedia langsung pada baris tabel saat disorot kursor.

Membatalkan pesanan:

1. Buka detail pesanan yang masih `Draft` atau `Menunggu`. Pembatalan hanya tersedia dari kedua status ini.
2. Ketuk `Batalkan Pesanan`.
3. Pada konfirmasi "Batalkan pesanan?", ketuk `Batalkan Pesanan` lagi. Status berubah menjadi `Batal` dan tidak bisa dikembalikan.

> **Tips:** Pesanan `Selesai` dan `Batal` adalah status akhir — tidak ada tombol aksi lagi. Itulah sebabnya aksi geser pada barisnya tidak bereaksi.

## Aksi Geser (Swipe) di Ponsel

Pada daftar pesanan di ponsel, tiap baris bisa digeser:

- Geser ke kiri → tombol `Bayar` untuk mencatat pembayaran. Tersedia jika statusnya `Menunggu`, `Diproses`, atau `Dikirim` dan belum lunas.
- Geser ke kanan → tombol `Batal` untuk membatalkan pesanan. Tersedia hanya untuk status `Draft` dan `Menunggu`.

Lepaskan jari setelah tarikan melewati lebar tombolnya untuk menjalankan aksi; jika dilepas terlalu dekat, baris kembali seperti semula tanpa terjadi apa-apa. Geser agak jauh (bukan sekadar sentuhan) agar tidak tertukar dengan geser scroll.

Interaksi lain pada baris: ketuk untuk membuka detail (atau form ubah untuk `Draft`), dan tekan-lama (khusus Owner) untuk membuka konfirmasi hapus. Di komputer, "geser" setara dengan tahan-klik lalu geser.

## Tab pada Detail Pesanan

Detail pesanan memiliki beberapa tab di bagian bawah:

- `Ringkasan` — rincian tagihan: `Subtotal`, `Diskon`, `PPN`, `Ongkir` (bila ada), `Total`, `Dibayar`, `Sisa`, dan `Kembalian`; daftar `Item`; riwayat `Pembayaran`; tombol `Pratinjau PDF`, `Unduh PDF`, dan `Cetak Struk`; serta baris `Staff:` pembuat pesanan (dari sini Owner dapat menugaskan ulang — lihat bagian di bawah).
- `Riwayat` — kartu `Riwayat Status` (perpindahan status), `Riwayat Tanggal`, dan `Log Aktivitas` (siapa mengubah apa dan kapan). Jika kosong tertulis "Belum ada riwayat."
- `Retur` — hanya tampil untuk Owner pada pesanan berstatus `Selesai`: isi `Alasan retur` dan jumlah tiap item, lalu ketuk `Proses Retur`. Penjelasan lengkap retur dan pembayaran ada di `03-pembayaran.md`.

## Pesanan Belum Lunas

Label pembayaran pada setiap pesanan menunjukkan kondisinya: `Belum bayar`, `Sebagian`, `Lunas`, atau `Lebih bayar`. Tombol `Bayar` muncul untuk pesanan berstatus `Menunggu`, `Diproses`, atau `Dikirim` yang belum lunas. Di tabel komputer, sisa tagihan juga tampil sebagai "Sisa Rp ..." merah di bawah total. Cara mencatat pembayaran dijelaskan di `03-pembayaran.md`.

## Peran: Owner dan Staff

- **Owner** melihat semua pesanan toko.
- **Staff** hanya melihat pesanan yang dibuatnya sendiri.

> **Catatan:** Menghapus pesanan dan memproses `Retur` hanya dapat dilakukan Owner.

## Menghapus Pesanan (Owner)

1. Di ponsel: tekan-lama baris pesanan. Di komputer: sorot baris lalu ketuk ikon tiga titik (⋮) → `Hapus`.
2. Pada konfirmasi `Hapus order?`, Owner dapat mencentang `Force delete (permanen)` untuk menghapus selamanya; tanpa centang, pesanan hanya dihapus biasa (soft delete). Stok produk akan dikembalikan.
3. Ketuk `Hapus`. Muncul notifikasi "Order dihapus".

> **Tips:** Gunakan `Batal` pada pesanan yang benar-benar sudah terjadi, dan hapus hanya untuk pesanan salah buat.

## Menugaskan Ulang Pesanan (Owner)

Owner dapat memindahkan pesanan-pesanan seorang staf ke pengguna lain — berguna saat alih tugas, staf cuti panjang, atau staf berhenti.

1. Buka detail pesanan si staf yang dimaksud, lalu pada tab `Ringkasan` ketuk tombol `Tugaskan ulang` di baris `Staff:` (baris yang menampilkan nama staf, misalnya "Staff: Jono").
2. Panel `Tugaskan ulang pesanan` terbuka, menampilkan nomor pesanan tersebut. Pilih `Pengguna baru` — daftarnya menampilkan tiap pengguna sebagai "Nama — Peran" (Owner atau Staff).
3. Ketuk `Simpan`. Tombolnya baru bisa ditekan setelah Anda memilih pengguna yang berbeda dari sebelumnya; ketuk `Batal` bila tidak jadi.
4. Muncul notifikasi "Pesanan ditugaskan ulang".

> **Catatan:** Perhatikan keterangan pada panelnya: "Semua pesanan milik pengguna saat ini akan dipindahkan ke pengguna baru." Aksi ini memindahkan **semua** pesanan milik staf tersebut ke pengguna baru — bukan hanya pesanan yang sedang Anda buka.

> **Tips:** Gunakan fitur ini untuk pengalihan tugas sepenuhnya, misalnya saat staf berhenti dan seluruh pesanannya diambil alih rekan lain.
