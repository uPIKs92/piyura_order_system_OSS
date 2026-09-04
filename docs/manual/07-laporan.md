# Laporan

Menu `Laporan` adalah pusat ringkasan bisnis Anda: hasil penjualan hari ini, tren laba, performa staf dan produk, hingga rekap pajak tahunan. Menu ini khusus pemilik usaha (Owner) — staf tidak melihat menu `Laporan` di navigasinya.

## Membuka Laporan

1. Masuk ke aplikasi dengan akun Owner.
2. Pilih menu `Laporan` — di ponsel letaknya di bilah bawah, di komputer di sidebar kiri.
3. Di bagian atas halaman ada tujuh tab: `Harian`, `Pengeluaran`, `Buku Bon`, `Staff`, `Produk`, `Status`, dan `Pajak`.
4. Tombol `Cetak` dan `Unduh PDF` di kanan atas berlaku untuk tab yang sedang terbuka (lihat bagian "Mencetak dan Mengunduh Laporan" di bawah).

> **Catatan:** Nilai omzet dihitung dari total pesanan (termasuk PPN bila aktif) dan tidak menghitung pesanan yang dibatalkan.

## Laporan Harian

Tab `Harian` menunjukkan hasil hari ini serta tren sepanjang periode pilihan Anda.

1. Buka `Laporan` → tab `Harian`.
2. Kartu di atas menampilkan `Pesanan Hari Ini` (jumlah pesanan hari ini), `Pendapatan Bersih` (pendapatan setelah dikurangi retur), `Retur` (nilai pengembalian hari ini), `Pendapatan Kotor` (total nilai pesanan hari ini), `Pengeluaran` (total catatan pengeluaran hari ini), dan `Laba Bersih` (laba usaha setelah dikurangi semua pengeluaran — ditampilkan menonjol).
3. Kartu `Tren Harian` memuat ringkasan periode: `Sales Turnover`, `COGS`, `Net Profit`, lalu `Omzet Kotor`, `Diskon`, dan `Rata-rata Pesanan`, ditutup `Retur`, `Pendapatan Bersih`, `Pengeluaran`, dan `Laba Bersih`.
4. Tentukan periode pada kolom `Dari` dan `Sampai`, lalu tekan `Perbarui Grafik`.

Arti istilah pada ringkasan tersebut:

- `Sales Turnover` — nilai penjualan barang pada periode tersebut.
- `COGS` — total harga beli barang yang terjual (biaya pokok).
- `Net Profit` — laba kotor, yaitu `Sales Turnover` dikurangi `COGS`.
- `Pengeluaran` — total catatan pengeluaran toko pada periode tersebut (lihat `Mencatat Pengeluaran` di bawah).
- `Laba Bersih` — `Net Profit` dikurangi `Pengeluaran`; angka paling mewakili untung-rugi usaha.
- `Omzet Kotor` — total nilai pesanan sebelum potongan.
- `Diskon` — total potongan harga yang Anda berikan.
- `Rata-rata Pesanan` — nilai rata-rata satu pesanan pada periode tersebut.

> **Tips:** Periode bawaan adalah 30 hari terakhir. Setiap kali mengganti tanggal, tekan `Perbarui Grafik` agar grafik dan ringkasan mengikuti periode baru.

## Membaca Grafik Tren Harian

Grafik pada kartu `Tren Harian` menampilkan batang per hari dengan tiga warna yang mewakili `Sales Turnover`, `Purchasing Cost (COGS)`, dan `Net Profit`, ditambah satu garis `Laba Bersih` yang memperlihatkan hasil usaha setelah pengeluaran.

1. Arahkan kursor (atau ketuk) sebuah batang untuk melihat rinciannya: tanggal, jumlah pesanan, dan nilai keempat ukuran tersebut.
2. Sumbu tegak memakai singkatan `rb` (ribuan) dan `jt` (juta) — misalnya `1,5jt` berarti Rp 1.500.000.
3. Bila periode tidak memiliki data, muncul pesan "Tidak ada data pada periode ini".

## Mencatat Pengeluaran

Tab `Pengeluaran` adalah buku catatan pengeluaran toko — mulai dari belanja bahan baku sampai biaya listrik — yang langsung memengaruhi `Laba Bersih` pada laporan Harian.

1. Buka `Laporan` → tab `Pengeluaran`.
2. Pilih tanggal pada kolom `Tanggal` (bawaannya hari ini). Pengeluaran hanya bisa dicatat untuk hari ini atau hari sebelumnya.
3. Isi `Nominal` dalam rupiah, pilih salah satu kategori — `Bahan Baku`, `Kemasan`, `Transport-Bensin`, `Listrik-Air-Pulsa`, `Gaji`, atau `Lain-lain` — dan tulis catatan singkat bila perlu.
4. Tekan `Simpan`. Pengeluaran langsung masuk daftar dan kartu `Total Pengeluaran` di bagian atas ikut bertambah.
5. Daftar di bawah menampilkan seluruh pengeluaran pada tanggal itu. Untuk mengubah, ketuk barisnya lalu perbaiki datanya; untuk menghapus, pilih `Hapus` pada baris dan konfirmasi.

> **Catatan:** Menu `Laporan` khusus Owner, jadi hanya Owner yang bisa mencatat dan melihat pengeluaran. Setiap pengeluaran yang dicatat atau dihapus langsung memperbarui angka `Pengeluaran` dan `Laba Bersih` pada laporan hari itu.

## Buku Bon (Piutang Pelanggan)

Tab `Buku Bon` adalah daftar pelanggan yang masih memiliki sisa pembayaran (bon). Buku ini terisi otomatis: setiap pesanan yang belum lunas (sisa pembayarannya lebih dari nol) dan tidak berstatus `Draft` atau `Batal` otomatis tercatat di sini — tidak ada tombol tambah manual. Pesanan dengan nomor telepon yang sama digabung menjadi satu debitur, meski pelanggannya tidak terdaftar di direktori.

1. Buka `Laporan` → tab `Buku Bon`.
2. Tiga kartu di atas merangkum: `Total Piutang` (jumlah seluruh sisa tagihan), `Pesanan Belum Lunas` (jumlah pesanan yang masih ada bonnya), dan `Jumlah Debitur` (jumlah pelanggan yang masih berutang).
3. Setiap baris debitur menampilkan nama, telepon, total bon, jumlah pesanan, dan lencana umur bon tertua: `≤7 hari` (netral), `8–14 hari` (kuning), `15–30 hari` (oranye), atau `>30 hari` (merah) — makin lama bon menggantung, makin merah lencananya.
4. Ketuk baris debitur untuk membuka daftar pesanan yang belum lunas: tanggal pesanan, total, dan sisa tagihan tiap pesanan.
5. Tekan `Catat Bayar` pada pesanan yang dituju, isi nominal (sudah terisi otomatis sebesar sisa tagihan), pilih metode `Cash`/`Transfer`/`QRIS`, lalu tekan `Simpan`.
6. Pembayaran langsung tercatat dan daftar bon diperbarui — pesanan yang lunas otomatis keluar dari daftar.

> **Catatan:** Bila semua pesanan sudah lunas, tab menampilkan kartu "Belum ada bon" beserta penjelasan singkat cara bon terbentuk.

> **Catatan:** Staf tidak melihat menu `Laporan`, jadi pencatatan pembayaran bon dilakukan dari akun Owner.

## Laporan Staff

Tab `Staff` mengurutkan performa staf pada periode tertentu — dari pendapatan terbesar — lengkap dengan piutang dan rata-rata nilai pesanannya.

1. Buka `Laporan` → tab `Staff`.
2. Isi `Dari` dan `Sampai`, lalu pilih staf pada kolom `Staff`: `Semua Staff` untuk membandingkan seluruh staf, atau nama staf tertentu.
3. Tekan `Muat Laporan`.
4. Kartu rangkuman menampilkan `Pesanan`, `Pendapatan`, `Piutang` (sisa tagihan pesanan yang belum lunas, beserta jumlah pesanannya), `AOV` (rata-rata nilai per pesanan, yaitu pendapatan dibagi jumlah pesanan), dan `PPN` (hanya tampil bila ada PPN).
5. Bila memilih `Semua Staff`, tabel peringkat menampilkan tiap staf berurut dari pendapatan terbesar: nomor peringkat, nama, `Pesanan`, `Pendapatan`, `%` kontribusi terhadap total pendapatan, `Piutang`, dan `AOV`, ditutup baris total.
6. Bila memilih satu staf, di bawah kartu rangkuman tampil chip status pesanan milik staf tersebut — misalnya `Menunggu 2 · Diproses 1` — hanya untuk status yang punya pesanan.

> **Catatan:** `Piutang` per staf menunjukkan sisa tagihan dari penjualannya yang belum dibayar pelanggan, sehingga Anda bisa melihat staf mana yang penjualannya masih menunggu pelunasan.

> **Catatan:** Bila muncul "Tidak ada data staff pada periode ini", coba perlebar rentang tanggal `Dari`–`Sampai`.

## Laporan Produk

Tab `Produk` merangkum penjualan, biaya, dan laba per produk.

1. Buka `Laporan` → tab `Produk`.
2. Isi `Dari` dan `Sampai`, lalu pilih produk pada kolom `Produk`: `Semua Produk` atau produk tertentu.
3. Tekan `Muat Laporan`.
4. Kartu rangkuman menampilkan `Pesanan`, `Pendapatan Kotor`, `Retur`, `Pendapatan Bersih`, `Terjual` (jumlah unit terjual), `Terjual Bersih` (jumlah unit setelah retur), `COGS` (total harga beli barang terjual), dan `Profit Bersih`.
5. Bila memilih `Semua Produk`, daftar di bawahnya menampilkan tiap produk: nama beserta satuannya, jumlah terjual (beserta angka `Retur` bila ada) dan jumlah pesanan yang memuatnya, pendapatan, serta `Profit` per produk.

> **Catatan:** Bila muncul "Tidak ada data produk pada periode ini", coba perlebar rentang tanggal `Dari`–`Sampai`.

## Pendapatan Kotor, Retur, dan Pendapatan Bersih

Laporan `Harian` (termasuk `Tren Harian`) dan `Produk` membedakan tiga angka pendapatan:

- `Pendapatan Kotor` — total nilai pesanan pada periode tersebut, sebelum retur.
- `Retur` / `Pengembalian (Retur)` — total nilai pesanan yang dikembalikan pelanggan pada periode tersebut.
- `Pendapatan Bersih` — `Pendapatan Kotor` dikurangi `Retur`; inilah uang yang benar-benar Anda terima.

Pada laporan produk juga ada `Terjual` (jumlah unit sebelum retur), `Retur` (jumlah unit yang dikembalikan), dan `Terjual Bersih` (jumlah unit setelah dikurangi retur). Laporan PDF menampilkan angka-angka yang sama dengan label `Pengembalian (Retur)` dan `Pendapatan Bersih`.

> **Catatan:** Retur dihitung pada tanggal retur dilakukan, bukan tanggal pesanan aslinya. Misalnya, pesanan tanggal 1 Agustus yang dikembalikan pada 10 Agustus akan mengurangi `Pendapatan Bersih` tanggal 10 Agustus — periksa kembali rentang `Dari`–`Sampai` bila nilai retur tidak muncul pada periode yang Anda harapkan.

## Order per Status

Tab `Status` menunjukkan jumlah dan nilai pesanan menurut status terkininya — pesanan yang dibuat pada periode pilihan Anda, atau seluruh pesanan bila tidak ada penyaring tanggal.

1. Buka `Laporan` → tab `Status`.
2. Secara bawaan tab ini menampilkan semua waktu. Bila ingin membatasi, isi `Dari` dan `Sampai` lalu tekan `Muat Laporan`; tekan `Semua Waktu` untuk kembali mencakup seluruh pesanan.
3. Empat kartu di atas merangkum: `Pesanan Aktif` (jumlah pesanan berjalan — `Draft`, `Menunggu`, `Diproses`, atau `Dikirim`), `Nilai Aktif` (total nilai pesanan aktif), `Piutang Total` (sisa tagihan, beserta keterangan jumlah pesanan yang belum bayar), dan `Total Pesanan`.
4. Tabel di bawahnya memuat satu baris per status — `Draft`, `Menunggu`, `Diproses`, `Dikirim`, `Selesai`, dan `Batal` — dengan kolom `Pesanan` (jumlah), `%` (porsi terhadap total pesanan), `Nilai Pesanan` (total nilainya), dan `Piutang` (sisa tagihannya) beserta keterangan "N belum bayar" pada status yang punya pesanan belum lunas; baris terakhir merangkum totalnya.

> **Catatan:** Pesanan dihitung menurut status terkininya, bukan status saat dipesan — pesanan periode lampau yang kini sudah `Selesai` tetap masuk baris `Selesai`. `Piutang` tidak memasukkan pesanan `Batal`, dan status tanpa pesanan tetap tampil dengan angka nol.

> **Tips:** Tekan `Cetak` atau `Unduh PDF` selagi tab ini terbuka untuk mendapatkan PDF yang sama: periode (atau keterangan "Semua Waktu"), kartu ringkasan, dan tabel per status.

## Laporan Pajak

Tab `Pajak` merangkum PPN keluaran dan PPh Final satu tahun pajak, bulan demi bulan — berguna sebagai bahan lapor pajak.

1. Buka `Laporan` → tab `Pajak`.
2. Pilih tahun pada kolom `Tahun Pajak` (tersedia 6 tahun terakhir); rekap langsung tampil.
3. Empat kartu di atas menampilkan `Omzet <tahun>`, `DPP`, `PPN Keluaran` (beserta persentasenya, misalnya (11%)), dan `PPh Final` (beserta modenya, misalnya `UMKM Non-PKP (0,5% / 12%)`).
4. Tabel `Rekap Masa Pajak <tahun>` memuat kolom `Masa` (Januari–Desember), `Transaksi`, `Omzet`, `Faktur`, `DPP`, `PPN`, `PPh`, dan `e-Faktur`.

### Memahami rekap PPN

- `Omzet` — total nilai pesanan sepanjang masa pajak tersebut, termasuk PPN, tanpa pesanan yang dibatalkan.
- `DPP` (Dasar Pengenaan Pajak) — omzet dikurangi PPN, yaitu nilai jual yang menjadi dasar penghitungan PPN.
- `PPN` — PPN keluaran, yaitu PPN yang Anda kumpulkan dari pelanggan.
- `Faktur` — jumlah pesanan dikenai PPN yang perlu dibuatkan faktur pajak pada masa tersebut.

> **Catatan:** Rekap ini hanya mencakup PPN keluaran; PPN masukan (pajak dari pembelian Anda) tidak termasuk. Status dan tarif PPN diatur di `Pengaturan` → `Operasi` pada kartu `Pengaturan PPN` — lihat bab [Pengaturan: Operasi](10-pengaturan-operasi.md).

### Memahami PPh Final

PPh dihitung otomatis dari omzet dengan dua mode:

- **`UMKM Non-PKP (0,5% / 12%)`** — untuk usaha kecil non-PKP. Omzet setahun sampai Rp 500.000.000 dikenai 0,5%; kelebihan di atasnya dikenai 12%. Contoh: omzet setahun Rp 480.000.000 → PPh = 0,5% × Rp 480.000.000 = Rp 2.400.000. Omzet Rp 520.000.000 → Rp 2.500.000 (0,5% × Rp 500.000.000) ditambah Rp 2.400.000 (12% × Rp 20.000.000) = Rp 4.900.000.
- **`UMKM PKP (PPh 22 2,5%)`** — untuk usaha PKP. PPh tiap bulan dihitung 2,5% dari omzet bulan tersebut.

Untuk mode Non-PKP, PPh bulanan dihitung bertahap: aplikasi menghitung PPh atas omzet kumulatif sejak awal tahun sampai bulan berjalan, lalu menguranginya dengan PPh yang sudah terhitung sampai bulan sebelumnya. Dengan begitu batas Rp 500.000.000 terpakai tepat sepanjang tahun.

> **Tips:** Mode PPh yang sedang dipakai tertera pada kartu `PPh Final`. Mode PPh dan NPWP toko diatur pada kartu `Pajak` di `Pengaturan` → `Operasi` — lihat bab [Pengaturan: Operasi](10-pengaturan-operasi.md).

## Mengunduh e-Faktur (CSV)

Tombol `CSV` pada baris bulan di tabel `Rekap Masa Pajak` mengunduh berkas faktur pajak keluaran bulan tersebut dalam format CSV impor DJP — siap diunggah ke aplikasi e-Faktur DJP.

1. Buka `Laporan` → tab `Pajak` → pilih `Tahun Pajak`.
2. Cari baris bulan yang ingin diekspor, lalu pada kolom `e-Faktur` tekan `CSV`.
3. Berkas tersimpan dengan nama pola `efaktur-<tahun>-<bulan>.csv`, contoh: `efaktur-2026-08.csv`.
4. Gunakan berkas tersebut untuk unggah faktur ke aplikasi e-Faktur DJP sesuai prosedur yang berlaku.

> **Catatan:** Tombol `CSV` hanya aktif bila kolom `Faktur` bulan tersebut lebih dari 0, dan berkas hanya memuat pesanan yang dikenai PPN.

## Mencetak dan Mengunduh Laporan

1. Buka tab laporan yang ingin dicetak: `Harian`, `Staff`, `Produk`, `Status`, atau `Pajak`. Untuk `Staff` dan `Produk`, pilih dulu periode serta staf/produknya lalu tekan `Muat Laporan`.
2. Tekan `Cetak` — berkas PDF terbuka di tab baru; dari sana Anda bisa mencetaknya atau menyimpannya.
3. Atau tekan `Unduh PDF` untuk langsung mengunduh berkas PDF ke perangkat.

> **Catatan:** Tab `Pengeluaran` dan `Buku Bon` tidak bisa dicetak — menekan `Cetak` pada tab tersebut hanya menampilkan pemberitahuan.

> **Catatan:** PDF laporan memakai tata letak klasik profesional yang sama dengan faktur penjualan — kop toko di bagian atas, tabel bergaris, dan angka dalam format IDR. Contoh tampilannya dapat dilihat pada bab [Faktur dan Cetak](04-faktur-dan-cetak.md).

> **Tips:** Laporan pajak PDF memuat ringkasan `Mode PPh` dan `NPWP` (bila sudah diisi), sehingga praktis dijadikan lampiran saat lapor pajak.

> **Catatan:** Bagian PPN (kolom `DPP`, `PPN`, `Faktur`, dan tombol e-Faktur) hanya berisi bila PPN diaktifkan di `Pengaturan` → `Operasi`. Bila PPN dimatikan, pesanan baru tidak dikenai PPN sehingga rekap PPN kosong — tetapi `PPh Final` tetap dihitung dari omzet.
