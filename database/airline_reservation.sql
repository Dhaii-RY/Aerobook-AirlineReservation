CREATE DATABASE IF NOT EXISTS airline_reservation
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE airline_reservation;
SET time_zone = '+08:00';

CREATE TABLE IF NOT EXISTS admin (
    id_admin INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nama_admin VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pesawat (
    id_pesawat INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode_pesawat VARCHAR(20) NOT NULL UNIQUE,
    maskapai VARCHAR(100) NOT NULL,
    kapasitas INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_pesawat_kapasitas CHECK (kapasitas BETWEEN 1 AND 1000)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS penerbangan (
    id_penerbangan INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nomor_penerbangan VARCHAR(20) NOT NULL UNIQUE,
    id_pesawat INT UNSIGNED NOT NULL,
    bandara_asal VARCHAR(100) NOT NULL,
    bandara_tujuan VARCHAR(100) NOT NULL,
    waktu_keberangkatan DATETIME NOT NULL,
    waktu_kedatangan DATETIME NOT NULL,
    harga DECIMAL(12,2) NOT NULL,
    status ENUM('Terjadwal','Ditunda','Dibatalkan') NOT NULL DEFAULT 'Terjadwal',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_penerbangan_pesawat
        FOREIGN KEY (id_pesawat) REFERENCES pesawat(id_pesawat)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_penerbangan_rute CHECK (bandara_asal <> bandara_tujuan),
    CONSTRAINT chk_penerbangan_waktu CHECK (waktu_kedatangan > waktu_keberangkatan),
    CONSTRAINT chk_penerbangan_harga CHECK (harga >= 0),
    INDEX idx_rute_tanggal (bandara_asal, bandara_tujuan, waktu_keberangkatan),
    INDEX idx_status_keberangkatan (status, waktu_keberangkatan)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS kursi_pesawat (
    id_kursi INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_pesawat INT UNSIGNED NOT NULL,
    nomor_kursi VARCHAR(5) NOT NULL,
    kelas ENUM('Ekonomi','Bisnis','First Class') NOT NULL DEFAULT 'Ekonomi',
    CONSTRAINT fk_kursi_pesawat
        FOREIGN KEY (id_pesawat) REFERENCES pesawat(id_pesawat)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    UNIQUE KEY uq_kursi_pesawat_nomor (id_pesawat, nomor_kursi)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS kursi_penerbangan (
    id_kursi_penerbangan INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_penerbangan INT UNSIGNED NOT NULL,
    id_kursi INT UNSIGNED NOT NULL,
    status ENUM('Tersedia','Dipesan','Terjual') NOT NULL DEFAULT 'Tersedia',
    CONSTRAINT fk_kursi_penerbangan_flight
        FOREIGN KEY (id_penerbangan) REFERENCES penerbangan(id_penerbangan)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_kursi_penerbangan_seat
        FOREIGN KEY (id_kursi) REFERENCES kursi_pesawat(id_kursi)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    UNIQUE KEY uq_flight_seat (id_penerbangan, id_kursi),
    INDEX idx_seat_status (id_penerbangan, status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS penumpang (
    id_penumpang INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nomor_paspor_nik VARCHAR(50) NOT NULL,
    nama_penumpang VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_penumpang_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pemesanan (
    id_pemesanan INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode_booking VARCHAR(30) NOT NULL UNIQUE,
    nama_pemesan VARCHAR(100) NOT NULL,
    email_pemesan VARCHAR(150) NOT NULL,
    total_harga DECIMAL(12,2) NOT NULL,
    status ENUM('Pending','Lunas','Dibatalkan','Kedaluwarsa') NOT NULL DEFAULT 'Pending',
    batas_pembayaran DATETIME NOT NULL,
    waktu_pemesanan TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_pemesanan_total CHECK (total_harga >= 0),
    INDEX idx_booking_status_deadline (status, batas_pembayaran),
    INDEX idx_booking_email (email_pemesan)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS detail_pemesanan (
    id_detail INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_pemesanan INT UNSIGNED NOT NULL,
    id_penerbangan INT UNSIGNED NOT NULL,
    id_penumpang INT UNSIGNED NOT NULL,
    id_kursi_penerbangan INT UNSIGNED NOT NULL,
    harga_tiket DECIMAL(12,2) NOT NULL,
    CONSTRAINT fk_detail_booking
        FOREIGN KEY (id_pemesanan) REFERENCES pemesanan(id_pemesanan)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_detail_flight
        FOREIGN KEY (id_penerbangan) REFERENCES penerbangan(id_penerbangan)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_detail_passenger
        FOREIGN KEY (id_penumpang) REFERENCES penumpang(id_penumpang)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_detail_flight_seat
        FOREIGN KEY (id_kursi_penerbangan) REFERENCES kursi_penerbangan(id_kursi_penerbangan)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_detail_harga CHECK (harga_tiket >= 0),
    UNIQUE KEY uq_booking_seat (id_pemesanan, id_kursi_penerbangan),
    INDEX idx_detail_booking (id_pemesanan)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pembayaran (
    id_pembayaran INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_pemesanan INT UNSIGNED NOT NULL UNIQUE,
    nomor_invoice VARCHAR(40) NOT NULL UNIQUE,
    metode_pembayaran VARCHAR(50) NOT NULL DEFAULT 'Transfer',
    jumlah_bayar DECIMAL(12,2) NOT NULL,
    status ENUM('Pending','Berhasil','Gagal') NOT NULL DEFAULT 'Pending',
    waktu_pembayaran DATETIME NULL,
    CONSTRAINT fk_pembayaran_booking
        FOREIGN KEY (id_pemesanan) REFERENCES pemesanan(id_pemesanan)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_pembayaran_jumlah CHECK (jumlah_bayar >= 0),
    INDEX idx_payment_status (status)
) ENGINE=InnoDB;

-- Seed armada demo; akun admin dibuat melalui setup-admin.php.
INSERT IGNORE INTO pesawat (kode_pesawat, maskapai, kapasitas) VALUES
('AB-A320', 'Aero Nusantara', 30),
('AB-B737', 'Garuda Indonesia', 36),
('AB-A321', 'Lion Air', 30),
('AB-ATR', 'Wings Air', 24);

-- Jadwal demo selalu dibuat relatif terhadap tanggal impor.
INSERT IGNORE INTO penerbangan
    (nomor_penerbangan, id_pesawat, bandara_asal, bandara_tujuan,
     waktu_keberangkatan, waktu_kedatangan, harga, status)
SELECT
    'GA-200', id_pesawat, 'DPS', 'CGK',
    TIMESTAMP(DATE_ADD(CURRENT_DATE, INTERVAL 2 DAY), '09:00:00'),
    TIMESTAMP(DATE_ADD(CURRENT_DATE, INTERVAL 2 DAY), '11:00:00'),
    1250000, 'Terjadwal'
FROM pesawat WHERE kode_pesawat = 'AB-B737';

INSERT IGNORE INTO penerbangan
    (nomor_penerbangan, id_pesawat, bandara_asal, bandara_tujuan,
     waktu_keberangkatan, waktu_kedatangan, harga, status)
SELECT
    'GA-201', id_pesawat, 'CGK', 'DPS',
    TIMESTAMP(DATE_ADD(CURRENT_DATE, INTERVAL 2 DAY), '15:00:00'),
    TIMESTAMP(DATE_ADD(CURRENT_DATE, INTERVAL 2 DAY), '17:00:00'),
    1325000, 'Terjadwal'
FROM pesawat WHERE kode_pesawat = 'AB-B737';

INSERT IGNORE INTO penerbangan
    (nomor_penerbangan, id_pesawat, bandara_asal, bandara_tujuan,
     waktu_keberangkatan, waktu_kedatangan, harga, status)
SELECT
    'JT-610', id_pesawat, 'DPS', 'SUB',
    TIMESTAMP(DATE_ADD(CURRENT_DATE, INTERVAL 3 DAY), '10:00:00'),
    TIMESTAMP(DATE_ADD(CURRENT_DATE, INTERVAL 3 DAY), '11:30:00'),
    680000, 'Terjadwal'
FROM pesawat WHERE kode_pesawat = 'AB-A320';

INSERT IGNORE INTO penerbangan
    (nomor_penerbangan, id_pesawat, bandara_asal, bandara_tujuan,
     waktu_keberangkatan, waktu_kedatangan, harga, status)
SELECT
    'IW-752', id_pesawat, 'DPS', 'YIA',
    TIMESTAMP(DATE_ADD(CURRENT_DATE, INTERVAL 4 DAY), '08:30:00'),
    TIMESTAMP(DATE_ADD(CURRENT_DATE, INTERVAL 4 DAY), '10:15:00'),
    790000, 'Terjadwal'
FROM pesawat WHERE kode_pesawat = 'AB-ATR';

INSERT IGNORE INTO penerbangan
    (nomor_penerbangan, id_pesawat, bandara_asal, bandara_tujuan,
     waktu_keberangkatan, waktu_kedatangan, harga, status)
SELECT
    'QZ-810', id_pesawat, 'CGK', 'SUB',
    TIMESTAMP(DATE_ADD(CURRENT_DATE, INTERVAL 5 DAY), '13:00:00'),
    TIMESTAMP(DATE_ADD(CURRENT_DATE, INTERVAL 5 DAY), '14:35:00'),
    720000, 'Terjadwal'
FROM pesawat WHERE kode_pesawat = 'AB-A321';
