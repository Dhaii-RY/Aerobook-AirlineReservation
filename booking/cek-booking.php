<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

$pageTitle = 'Cek Booking — AeroBook';
$extraCss = ['/airline-reservation/assets/css/booking.css'];

$error = '';
$kode = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $kode = strtoupper(trim((string) ($_POST['kode_booking'] ?? '')));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));

    // Validasi format kode booking.
    if (!preg_match('/^AB-[A-F0-9]{12}$/', $kode)) {
        $error = 'Kode booking atau email tidak ditemukan.';
    } elseif (
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($email) > 150
    ) {
        $error = 'Kode booking atau email tidak ditemukan.';
    } else {
        try {
            // Cocokkan kode booking dan email pemesan.
            $stmt = $pdo->prepare(
                "SELECT kode_booking
                 FROM pemesanan
                 WHERE kode_booking = ?
                   AND LOWER(email_pemesan) = ?
                 LIMIT 1"
            );

            $stmt->execute([$kode, $email]);

            $booking = $stmt->fetch();

            if ($booking) {
                header(
                    'Location: /airline-reservation/booking/invoice.php?kode='
                    . rawurlencode($booking['kode_booking'])
                );
                exit;
            }

            $error = 'Kode booking atau email tidak ditemukan.';

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = 'Terjadi kesalahan. Silakan coba kembali.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="booking-page">
    <div class="booking-container lookup-container">

        <a class="back-link"
           href="/airline-reservation/index.php">
            &larr; Kembali ke beranda
        </a>

        <div class="booking-heading">
            <p class="eyebrow">AEROBOOK · BOOKING MANAGEMENT</p>

            <h1>Cek Booking Anda</h1>

            <p>
                Masukkan kode booking dan email yang digunakan
                saat melakukan pemesanan untuk melihat invoice
                dan status pembayaran tiket Anda.
            </p>
        </div>

        <div class="lookup-card">

            <h2>Temukan Pemesanan</h2>

            <p class="lookup-description">
                Pastikan informasi yang dimasukkan sesuai
                dengan data pemesanan Anda.
            </p>

            <?php if ($error !== ''): ?>
                <div class="invoice-notice">
                    <?= h($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST"
                  action="cek-booking.php"
                  class="lookup-form">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= h(csrfToken()) ?>"
                >

                <div class="passenger-field">
                    <label for="kode_booking">
                        Kode Booking
                    </label>

                    <input
                        type="text"
                        id="kode_booking"
                        name="kode_booking"
                        placeholder="Contoh: AB-A1B2C3D4E5F6"
                        value="<?= h($kode) ?>"
                        maxlength="15"
                        required
                    >
                </div>

                <div class="passenger-field">
                    <label for="email">
                        Email Pemesan
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Masukkan email pemesanan"
                        value="<?= h($email) ?>"
                        maxlength="150"
                        required
                    >
                </div>

                <button
                    type="submit"
                    class="btn btn-primary lookup-button"
                >
                    Cari Pemesanan
                    <span aria-hidden="true">&rarr;</span>
                </button>

            </form>

            <div class="lookup-help">
                <strong>Belum menemukan kode booking?</strong>
                <p>
                    Pastikan Anda memasukkan kode booking dan email
                    yang sama dengan yang digunakan saat melakukan
                    pemesanan. Jika masih belum menemukan, silakan
                    hubungi  <span style="color: blue;">layanan pelanggan AeroBook</span> untuk bantuan.
                </p>
            </div>

        </div>

    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
