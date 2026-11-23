<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Makassar');

$host = 'localhost';
$dbname = 'airline_reservation';
$username = 'root';
$password = '';

$dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
    // Bali/WITA (UTC+08:00), agar batas pembayaran konsisten.
    $pdo->exec("SET time_zone = '+08:00'");
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('Koneksi database gagal. Impor database/airline_reservation.sql dan periksa config/database.php.');
}
