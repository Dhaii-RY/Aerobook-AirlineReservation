# Implementasi perubahan AeroBook

## Pembagian kelas kabin
- Baris kursi 1: First Class.
- Baris kursi 2–3: Business Class (disimpan sebagai `Bisnis` pada database).
- Baris kursi 4 dan seterusnya: Economy Class.
- Pembagian kelas diperbarui otomatis ketika halaman `booking/seats.php` dibuka. Struktur tabel `kursi_pesawat.kelas` pada database yang ada sudah mendukung ketiga kelas tersebut.
- Harga tiket dihitung per kursi: Economy = 1× harga dasar penerbangan, Business = 2×, First Class = 4×. Harga masing-masing kursi disimpan ke `detail_pemesanan.harga_tiket`, lalu dijumlahkan ke total pemesanan.

## Pengurutan dashboard admin
- Dashboard: keberangkatan terbaru, nomor penerbangan A–Z/Z–A, harga terendah–tertinggi/tertinggi–terendah.
- Data Pesawat: kode A–Z/Z–A, maskapai A–Z, kapasitas rendah–tinggi/tinggi–rendah.
- Penerbangan: nomor A–Z/Z–A, rute A–Z, harga rendah–tinggi/tinggi–rendah, atau keberangkatan terbaru.
- Pemesanan dan Pembayaran: nama pemesan A–Z/Z–A serta nominal terendah–tertinggi/tertinggi–terendah.

Semua opsi pengurutan SQL menggunakan whitelist di sisi server.
