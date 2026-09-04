# Faktur & Cetak

Dari setiap pesanan Anda dapat menghasilkan dua dokumen: **faktur PDF** (dokumen resmi untuk pelanggan, cocok dikirim lewat WhatsApp/email) dan **struk** (slip kecil untuk printer kasir thermal). Semua tombolnya tersedia di detail pesanan dan di panel sukses setelah pembayaran.

> **Catatan:** Cara membuka dan membaca detail pesanan dijelaskan di bab `02-pesanan.md`.

## Kapan Memakai Faktur, Kapan Memakai Struk

- **Faktur PDF** — dokumen formal berukuran kertas, lengkap dengan logo toko, data pelanggan, dan rincian pembayaran. Gunakan untuk pesanan pengiriman, dikonsumsi pelanggan, atau arsip.
- **Struk** — dokumen kecil berformat khusus printer thermal 58 mm. Gunakan untuk transaksi kasir langsung.

## Membuka Faktur dari Pesanan

1. Buka `Pesanan` → ketuk pesanan yang ingin dicetak.
2. Buka tab `Ringkasan`.
3. Di bawah kartu `Invoice` ada tiga tombol: `Pratinjau PDF`, `Unduh PDF`, dan `Cetak Struk`.

Tombol `Unduh Invoice PDF` dan `Cetak Struk` juga tersedia di panel `Pembayaran Dicatat` yang muncul setelah Anda mencatat pembayaran — jadi Anda bisa langsung menyerahkan struk begitu pelanggan membayar.

## Pratinjau Faktur

1. Dari tab `Ringkasan`, ketuk `Pratinjau PDF`.
2. Faktur terbuka di tab baru peramban (browser) sebagai berkas PDF.
3. Periksa isinya sebelum diunduh, dicetak, atau dikirim ke pelanggan.

Bagian-bagian faktur, dari atas ke bawah:

- **Kop faktur** — logo toko (bila ada) tampil di samping nama toko, disusul tagline, alamat, dan telepon · email. Di sisi kanan ada judul `INVOICE` dengan blok `No. Nota`, `Tanggal`, dan `Kasir` (nama petugas yang membuat pesanan). Kop ditutup garis tebal.
- **Blok pelanggan** — label "Diterbitkan Untuk Pelanggan" di atas nama, telepon, dan alamat pelanggan (bila terisi).
- **Tabel barang** — kolom `No.`, `Deskripsi Item`, `QTY`, `Harga Satuan`, dan `Subtotal`. Harga yang tercantum adalah harga saat pesanan dibuat, sehingga tetap benar walau harga produk sudah Anda ubah. Untuk produk berbatch, nomor batch dan tanggal kedaluwarsa tercetak kecil di bawah nama barang. Untuk pesanan dengan banyak barang, tabel menyambung ke halaman berikutnya dengan kepala tabel yang tetap tercetak.
- **Total** — daftar angka di sisi kanan bawah tabel: `Subtotal`, `Diskon / Potongan`, `PPN (…%)` (hanya bila PPN aktif), `Ongkir` (hanya bila pesanan punya biaya kirim), lalu `Total Akhir` yang ditandai garis ganda di atasnya, disusul `Dibayar`, `Sisa Tagihan` (merah, bila masih ada), dan `Kembalian` (hijau, bila ada).
- **Bagian `Pembayaran`** — di bawah total, selama sudah diatur di `Pengaturan → Pembayaran` — bukan hanya untuk pesanan yang sudah dibayar dengan metode tersebut — memuat dua sisi berdampingan: sisi `Transfer` (`Bank`, `No. Rekening`, `Atas Nama`) dan sisi `QRIS` (gambar QR yang tetap mudah dipindai). Bila hanya rekening yang diatur, sisi transfer memenuhi seluruh baris; bila hanya QRIS, gambar QR tampil di tengah. Khusus QR dinamis Mayar, gambar QR hanya tampil bila nominal QR-nya masih sama dengan sisa tagihan saat faktur dibuat; bila berbeda, sisi QRIS menampilkan catatan "Scan QRIS lewat aplikasi kasir atau hubungi toko" sebagai gantinya.
- **Kaki faktur** — teks penutup dari pengaturan toko Anda.

- **Cap `LUNAS`** — untuk pesanan yang sudah lunas (dibayar penuh, termasuk kelebihan bayar), faktur otomatis dicap besar "LUNAS" miring di tengah halaman; tanda air ini muncul di semua halaman faktur.

> **Catatan:** Angka pada PDF faktur ditulis dengan format "IDR 1.500.000", bukan "Rp".

## Mengunduh Faktur PDF

1. Dari tab `Ringkasan`, ketuk `Unduh PDF`.
2. Berkas tersimpan di perangkat dengan nama `invoice-<No. Nota>.pdf` — misalnya `invoice-INV-2026-0001.pdf`.
3. Kirimkan berkas tersebut kepada pelanggan lewat WhatsApp atau email bila perlu.

## Mencetak Faktur PDF

1. Ketuk `Pratinjau PDF` sehingga faktur terbuka di tab baru.
2. Pada tab PDF tersebut, buka menu cetak peramban (di komputer tekan Ctrl+P; di ponsel biasanya lewat menu ⋮ lalu pilih Cetak/Simpan sebagai PDF).
3. Pilih printer dan ukuran kertas, lalu cetak.

> **Tips:** Faktur paling rapi dicetak di kertas A5 atau A4.

## Mencetak Struk

1. Buka detail pesanan → tab `Ringkasan` → ketuk `Cetak Struk`. Bisa juga dari panel `Pembayaran Dicatat`.
2. Di komputer, struk terbuka di jendela kecil lalu dialog cetak peramban muncul sendiri. Di ponsel — baik lewat browser maupun aplikasi yang sudah terpasang (PWA) — dialog cetak langsung terbuka dari halaman yang sama.
3. Pilih printer struk Anda (printer thermal 58 mm; printer 80 mm menyesuaikan lebar otomatis), lalu cetak.

Isi struk dari atas ke bawah: logo (bila ada) serta nama, tagline, alamat, dan telepon toko; nomor nota, tanggal, dan nama kasir; nama dan telepon pelanggan; daftar barang dengan jumlah dan harga; `Subtotal`, `Diskon` (bila ada), `PPN (…%)` (bila ada), `Total`, `Dibayar`, `Sisa` (bila ada), dan `Kembali` (istilah di struk untuk kembalian); baris metode pembayaran; rekening transfer bila dibayar transfer; dan teks kaki struk.

> **Catatan:** Di komputer, jika jendela struk tidak muncul karena diblokir pemblokir pop-up, dialog cetak tetap dibuka otomatis dari halaman yang sama — tunggu sebentar lalu pilih printer.

## Pengaturan yang Memengaruhi Faktur dan Struk

- **Profil toko** — nama, alamat, telepon, logo, dan teks kaki (footer) faktur diambil dari `Pengaturan → Profil`. Mengganti data di sana otomatis mengubah dokumen berikutnya. Lihat bab `09-pengaturan-profil-tampilan.md`.
- **PPN** — baris PPN hanya tercetak bila PPN diaktifkan, dengan tarif sesuai pengaturan. Lihat bab `10-pengaturan-operasi.md`.
- **Rekening dan QRIS** — kotak rekening transfer serta QRIS pada faktur memakai data dari `Pengaturan → Pembayaran`, dan tampil di setiap faktur selama datanya sudah diatur. Lihat bab `11-pengaturan-pembayaran.md`.

> **Tips:** Setelah mengganti logo atau teks kaki faktur, lakukan satu kali cetak percobaan untuk memastikan tampilan dokumen sudah pas sebelum dikirim ke pelanggan.

## Faktur untuk Pesanan Bertahap

Untuk pesanan yang dibayar DP atau cicilan, faktur otomatis menyesuaikan:

1. Baris `Dibayar` menampilkan total yang sudah dibayarkan pelanggan.
2. Bila masih ada sisa, baris `Sisa Tagihan` menampilkan nominal yang belum dibayar.
3. Bila pelanggan membayar lebih, baris `Kembalian` menampilkan kelebihannya.

Karena itu faktur tetap aman dicetak kapan saja — saat DP maupun setelah lunas. Penjelasan pencatatan pembayaran bertahap ada di bab `03-pembayaran.md`.

## Ringkasan Cepat

| Kebutuhan | Tombol | Hasil |
| --- | --- | --- |
| Lihat faktur sebelum dicetak | `Pratinjau PDF` | PDF terbuka di tab baru |
| Simpan/kirim faktur ke pelanggan | `Unduh PDF` | berkas `invoice-<No. Nota>.pdf` |
| Cetak dokumen formal | `Pratinjau PDF` → Ctrl+P | faktur tercetak lewat dialog cetak |
| Cetak slip kasir | `Cetak Struk` | dialog cetak struk terbuka langsung |
