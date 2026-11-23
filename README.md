# AeroBook — Website Reservasi Tiket Pesawat

AeroBook adalah proyek demonstrasi reservasi tiket pesawat berbasis PHP, MySQL, HTML, CSS, dan JavaScript untuk dijalankan secara lokal menggunakan XAMPP.

> **Penting:** Ini proyek simulasi pembelajaran. Tidak terhubung ke maskapai atau payment gateway sungguhan. Jangan gunakan data identitas asli atau menganggap invoice sebagai tiket pesawat resmi.

## Fitur

### Pengunjung
- Mencari penerbangan berdasarkan bandara asal, tujuan, dan tanggal.
- Melihat jadwal, maskapai, rute, durasi perkiraan dari jadwal, dan harga.
- Memilih kursi yang masih tersedia.
- Mengisi data penumpang.
- Membuat kode booking dan invoice.
- Melakukan simulasi pembayaran.
- Status kursi dan booking diperbarui berdasarkan transaksi simulasi.
- Reservasi Pending yang melewati batas waktu ditandai Kedaluwarsa dan kursinya dilepas saat invoice dibuka, pembayaran diproses, atau checkout berikutnya berjalan.

### Admin
- Login admin menggunakan password hash.
- Dashboard ringkasan.
- CRUD data pesawat.
- CRUD jadwal penerbangan.
- Melihat, mencari, dan memfilter pemesanan.
- Melihat, mencari, dan memfilter pembayaran.
- CSRF protection untuk formulir POST dan logout.

## Persyaratan
- XAMPP dengan Apache, PHP 8.1+ (disarankan PHP 8.2+), dan MySQL/MariaDB.
- Ekstensi PDO MySQL aktif.
- Folder proyek harus bernama `airline-reservation`, karena URL aplikasi menggunakan `/airline-reservation`.

## Instalasi di Windows dengan XAMPP

1. Ekstrak folder `airline-reservation` ke:
   `C:\xampp\htdocs\airline-reservation`
2. Buka XAMPP Control Panel, lalu jalankan **Apache** dan **MySQL**.
3. Buka `http://localhost/phpmyadmin`.
4. Pilih tab **Import**, lalu impor:
   `C:\xampp\htdocs\airline-reservation\database\airline_reservation.sql`
   File SQL membuat database, tabel, armada contoh, dan jadwal demo yang tanggalnya dibuat relatif terhadap tanggal impor.
5. Jika MySQL XAMPP menggunakan konfigurasi default, buka `config/database.php` dan gunakan:
   - host: `localhost`
   - database: `airline_reservation`
   - username: `root`
   - password: kosong
   Jika akun MySQL Anda memiliki password, isi variabel `$password`.
6. Buka `http://localhost/airline-reservation/setup-admin.php`.
7. Buat akun admin pertama. Pilih username dan password milik Anda sendiri; tidak ada password admin bawaan.
8. Login melalui `http://localhost/airline-reservation/auth/login.php`.
9. Buka situs pengunjung di `http://localhost/airline-reservation/`.

Setelah akun admin dibuat, `setup-admin.php` tidak lagi mengizinkan pembuatan akun tambahan. Untuk keamanan ekstra, hapus file `setup-admin.php` setelah setup selesai.

## Alur pengujian yang disarankan

1. Buka beranda dan pastikan jadwal demo muncul untuk tanggal yang tertera di data penerbangan.
2. Pilih rute dan tanggal yang sesuai, lalu tekan **Cari Tiket**.
3. Pilih kursi, isi data penumpang, lalu lanjutkan.
4. Simpan kode booking yang muncul pada invoice.
5. Tekan **Simulasikan Pembayaran** dan pastikan status berubah menjadi Lunas/Berhasil serta kursi menjadi Terjual.
6. Ulangi dengan booking baru dan jangan lakukan pembayaran; setelah 30 menit, buka invoice atau lakukan checkout lain untuk memicu pemeriksaan kedaluwarsa.
7. Login admin dan periksa Dashboard, Data Pesawat, Data Penerbangan, Data Pemesanan, dan Data Pembayaran.
8. Coba buka halaman admin langsung tanpa login; seharusnya diarahkan ke halaman login.

## Catatan teknis

- Data bandara disimpan sebagai kode IATA seperti `DPS`, `CGK`, `SUB`, dan `YIA` supaya filter pengunjung konsisten dengan formulir admin.
- Reservasi mendukung satu penumpang/kursi per kode booking.
- Kursi dikunci dengan transaksi database saat checkout untuk mengurangi risiko dua pemesanan pada kursi yang sama.
- Riwayat detail pemesanan tetap disimpan saat booking kedaluwarsa.
- Waktu database dikonfigurasi ke UTC+08:00 (WITA).
- Pesawat atau penerbangan yang sudah terhubung dengan data lain mungkin tidak dapat dihapus karena foreign key; hal ini menjaga integritas riwayat.

## Jika terjadi masalah

- **Koneksi database gagal:** pastikan MySQL aktif, database sudah diimpor, dan kredensial di `config/database.php` sesuai.
- **Halaman kosong/error 500:** periksa `C:\xampp\php\logs\php_error_log` atau log PHP yang dikonfigurasi pada XAMPP.
- **Tidak ada penerbangan:** pilih tanggal sesuai jadwal demo atau buat jadwal baru melalui admin.
- **Tidak bisa membuat admin:** pastikan tabel `admin` kosong. Jika akun sudah ada, gunakan halaman login.
- **CSS tidak berubah:** lakukan hard refresh dengan `Ctrl + F5`.

## Batasan proyek

Ini adalah prototipe lokal. Pembayaran hanya simulasi, email tidak dikirim, dan tidak ada integrasi ke sistem reservasi maskapai nyata. Untuk deployment publik diperlukan audit keamanan, pengelolaan rahasia, validasi bisnis lebih lanjut, logging, HTTPS, dan integrasi payment gateway resmi.


## Pemesanan beberapa kursi sekaligus

Fitur pemilihan multi-kursi sudah tersedia pada menu pemilihan kursi. Pengguna dapat
memilih beberapa kursi, mengisi data masing-masing penumpang, lalu membuat satu
kode booking dan satu invoice dengan total harga seluruh tiket.

Jika menggunakan database lama, jalankan file
`database/migration_multi_seat_booking.sql` satu kali melalui phpMyAdmin atau
client MariaDB sebelum mencoba fitur ini. Jika membuat database dari awal,
gunakan `database/airline_reservation.sql` yang sudah diperbarui.
