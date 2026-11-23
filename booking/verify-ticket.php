<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

$kode = strtoupper(trim((string) ($_GET['kode'] ?? '')));
$booking = null;
$valid = false;

if (preg_match('/^AB-[A-F0-9]{12}$/', $kode)) {
    $stmt = $pdo->prepare(
        "SELECT kode_booking, status
         FROM pemesanan
         WHERE kode_booking = ?
         LIMIT 1"
    );

    $stmt->execute([$kode]);
    $booking = $stmt->fetch();

    $valid = $booking && $booking['status'] === 'Lunas';
}

$pageTitle = 'Verifikasi Tiket — AeroBook';

require_once __DIR__ . '/../includes/header.php';
?>

<section class="booking-page">
    <div class="booking-container" style="max-width: 550px;">
        <div class="lookup-card" style="text-align:center;">

            <?php if ($valid): ?>

                <h1 style="color:green;">TIKET VALID</h1>

                <p>
                    Tiket berhasil diverifikasi.
                    Pembayaran telah lunas.
                </p>

                <p><strong>Kode Booking:</strong></p>

                <h2><?= h($booking['kode_booking']) ?></h2>

                <p style="color:green;">
                    Status: LUNAS
                </p>

            <?php else: ?>

                <h1 style="color:#dc2626;">
                    TIKET TIDAK VALID
                </h1>

                <p>
                    Booking tidak ditemukan atau
                    pembayaran belum lunas.
                </p>

            <?php endif; ?>

        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
