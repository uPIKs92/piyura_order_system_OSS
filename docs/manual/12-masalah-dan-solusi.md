# Masalah & Solusi

Bab ini menghimpun keluhan dan pertanyaan yang paling sering muncul beserta cara mengatasinya. Cari gejala yang paling mirip dengan yang Anda alami, lalu ikuti langkah-langkahnya. Bila masalah Anda tidak ada di daftar, lihat bagian [Masih bermasalah?](#masih-bermasalah) di akhir bab.

## Lupa kata sandi dan tidak bisa masuk

**Penyebab:** Kata sandi salah atau terlupakan. Halaman masuk tidak menyediakan tombol "lupa kata sandi" — kata sandi hanya dapat diatur ulang oleh Owner.

**Solusi:**

1. Jika Anda Staff, hubungi Owner toko Anda dan minta kata sandi direset.
2. Jika Anda Owner, buka menu `Pengguna` ([Bab 8](08-pengguna.md)), cari akun yang dimaksud, lalu pilih `Reset kata sandi` pada menu tindakannya.
3. Konfirmasi dengan menekan `Kirim`. Tautan pengaturan ulang akan dikirim ke email akun tersebut — buka tautan itu dan buat kata sandi baru.

## Muncul pesan "Terlalu banyak percobaan login. Coba lagi dalam 1 menit."

**Penyebab:** Anda telah gagal masuk 5 kali berturut-turut, sehingga aplikasi sementara menolak percobaan login berikutnya demi keamanan.

**Solusi:**

1. Tunggu sekitar 1 menit tanpa menekan tombol masuk.
2. Setelah itu, coba masuk kembali dengan hati-hati.
3. Jika kata sandi benar-benar lupa, jangan terus menebak — minta reset seperti pada bagian [Lupa kata sandi](#lupa-kata-sandi-dan-tidak-bisa-masuk).

## Muncul pesan "Beberapa akun ditemukan. Pilih toko Anda terlebih dahulu."

**Penyebab:** Email Anda terdaftar di lebih dari satu toko, jadi aplikasi perlu memastikan toko mana yang ingin Anda buka.

**Solusi:**

1. Pada layar pemilihan toko, ketuk toko yang ingin Anda buka.
2. Jika toko Anda tidak ada di daftar, ketuk `Pilih toko lain` lalu masukkan kode toko yang benar. Selengkapnya di [Bab 1](01-masuk-ke-aplikasi.md).

## Muncul pesan "Akun ini telah dinonaktifkan."

**Penyebab:** Akun Anda dinonaktifkan oleh Owner, dan hanya Owner yang dapat mengaktifkannya kembali.

**Solusi:**

1. Hubungi Owner toko Anda dan minta akun diaktifkan kembali.
2. Owner dapat melakukannya lewat menu `Pengguna` ([Bab 8](08-pengguna.md)): pilih `Ubah` pada akun Anda, nyalakan sakelar `Aktif`, lalu simpan.

## Menu `Katalog`, `Laporan`, atau `Pengaturan` tidak muncul

**Penyebab:** Menu tersebut memang khusus Owner. Akun Staff hanya melihat menu `Pesanan` (dan tombol `Keluar`).

**Solusi:**

1. Pastikan Anda masuk dengan akun yang benar — Staff tidak akan pernah melihat menu-menu Owner.
2. Bila Anda seharusnya Owner tetapi menunya tidak muncul, hubungi pemilik toko untuk memeriksa peran akun Anda.

## Staff tidak bisa melihat pesanan rekan kerja

**Penyebab:** Ini perilaku yang memang dirancang demikian. Staff hanya melihat pesanan yang dibuat sendiri; hanya Owner yang melihat semua pesanan toko.

**Solusi:**

1. Tidak ada yang perlu diperbaiki — ini normal.
2. Bila Anda perlu mengakses pesanan rekan kerja, minta Owner yang membukanya.

## Tampilan aplikasi tidak berubah setelah pembaruan

**Penyebab:** Aplikasi menyimpan salinan berkas lama di perangkat agar terbuka lebih cepat, dan sesekali salinan lama itu masih dipakai.

**Solusi:**

1. Muat ulang aplikasi seperti biasa (tarik ke bawah pada halaman, atau tekan F5 di komputer) — umumnya tampilan terbaru langsung muncul.
2. Bila masih tampilan lama, lakukan refresh paksa: tekan **Ctrl+Shift+R** di komputer.
3. Di ponsel, tutup aplikasi sepenuhnya (hapus dari daftar aplikasi terbuka), lalu buka kembali.

## Tombol `Pasang Aplikasi` tidak muncul

**Penyebab:** Kartu pemasangan hanya muncul bila aplikasi belum terpasang di perangkat Anda.

**Solusi:**

1. Pastikan Anda membuka `Pengaturan` → `Profil` — kartu `Pasang Aplikasi` berada di sana.
2. Bila kartunya tetap tidak muncul, pasang secara manual lewat menu browser:
   - **iPhone/iPad:** ketuk tombol `Share` di Safari, lalu pilih `Add to Home Screen`.
   - **Android:** ketuk menu (⋮) di pojok kanan atas browser, lalu pilih `Add to Home screen`.

> **Tips:** Panduan lengkap pemasangan ada di [Bab 1 — Masuk ke Aplikasi](01-masuk-ke-aplikasi.md).

## Draf pesanan yang sedang dikerjakan hilang

**Penyebab:** Pesanan yang belum disimpan otomatis disimpan sebagai draf, dan saat aplikasi dibuka kembali draf itu ditawarkan lewat pita (banner) di bagian atas — tidak langsung terbuka.

**Solusi:**

1. Buka menu `Pesanan` dan perhatikan pita berisi tulisan "Draf tersimpan ditemukan".
2. Ketuk `Pulihkan` untuk melanjutkan draf tersebut, atau `Buang` bila tidak jadi diperlukan. Selengkapnya di [Bab 2](02-pesanan.md).

## Muncul pesan "QRIS belum dikonfigurasi." atau "Rekening belum dikonfigurasi."

**Penyebab:** Gambar QRIS atau nomor rekening bank toko belum diunggah, sehingga metode pembayaran tersebut belum bisa dipakai.

**Solusi:**

1. Pada pesan tersebut, ketuk `Atur di Pengaturan` untuk langsung menuju halaman pengaturannya.
2. Owner mengunggah gambar QRIS dan mengisi rekening bank di `Pengaturan` → `Pembayaran` ([Bab 11](11-pengaturan-pembayaran.md)).

## Sinkronisasi Google Sheets gagal

**Penyebab:** Koneksi ke Google terputus, akun Google belum terhubung, atau ada baris data yang gagal terkirim setelah beberapa kali percobaan.

**Solusi:**

1. Buka `Pengaturan` → `Operasi`, lalu cari bagian `Sheets Sync Gagal`.
2. Periksa item yang gagal, lalu ketuk `Coba lagi` untuk mengirim ulang.
3. Pastikan koneksi Google masih aktif — bila perlu, hubungkan ulang akun Google. Selengkapnya di [Bab 10](10-pengaturan-operasi.md).

## Jumlah stok tidak cocok atau minus

**Penyebab:** Ada pergerakan stok yang belum tercatat (barang rusak, terpakai, dsb.) sehingga catatan di aplikasi berbeda dengan stok fisik.

**Solusi:**

1. Buka `Katalog` → tab `Stok`, pilih produknya, lalu periksa bagian `Riwayat` dan `Batch` untuk melihat pergerakan yang tercatat.
2. Lakukan pengecekan fisik lewat tab `Opname`: pilih produk, isi jumlah hasil hitung fisik, lalu tekan `Simpan Opname`.
3. Stok akan langsung disesuaikan dengan hasil opname. Panduan selengkapnya di [Bab 6](06-stok.md).

## Laporan pajak kosong

**Penyebab:** PPN belum diaktifkan, atau periode laporan yang dipilih tidak memuat data apa pun.

**Solusi:**

1. Pastikan PPN aktif: buka `Pengaturan` → `Operasi`, periksa pengaturan PPN, lalu simpan.
2. Buka `Laporan` → tab `Pajak` dan pastikan `Tahun Pajak` yang dipilih sudah benar. Selengkapnya di [Bab 7](07-laporan.md).

## Retur tidak bisa diproses

**Penyebab:** Retur hanya dapat diproses oleh Owner, dan hanya untuk pesanan berstatus `Selesai` — pada pesanan lain tab `Retur` tidak akan muncul.

**Solusi:**

1. Pastikan pesanan sudah berstatus `Selesai`; pesanan yang masih diproses atau dikirim harus diselesaikan dulu.
2. Pastikan Anda masuk sebagai Owner, karena Staff tidak dapat memproses retur.
3. Buka pesanan, pilih tab `Retur`, isi alasan dan jumlah barang, lalu ketuk `Proses Retur`. Lihat [Bab 3](03-pembayaran.md).

## Masih bermasalah?

Bila masalah Anda tidak terjawab di bab ini:

1. Catat pesan error yang muncul persis apa adanya (disalin atau difoto).
2. Catat langkah-langkah yang Anda lakukan sebelum error muncul — ini sangat membantu menemukan penyebabnya.
3. Sampaikan kedua catatan tersebut kepada Owner atau pengelola toko Anda. Bila perlu, Owner akan meneruskannya ke pihak yang mengelola aplikasi.
