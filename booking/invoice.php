<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

$pageTitle = 'Invoice Pemesanan — AeroBook';
$extraCss = ['/airline-reservation/assets/css/booking.css'];

$kode = strtoupper(trim((string) ($_GET['kode'] ?? '')));

if (!preg_match('/^AB-[A-F0-9]{12}$/', $kode)) {
    http_response_code(404);
    exit('Kode booking tidak valid.');
}

/*
 * Sinkronkan status bila invoice dibuka setelah batas pembayaran.
 * Detail tiket tetap disimpan untuk riwayat, tetapi kursi dilepas.
 */
try {
    $pdo->beginTransaction();

    $lockBooking = $pdo->prepare(
        'SELECT id_pemesanan, status, batas_pembayaran
         FROM pemesanan
         WHERE kode_booking = ?
         FOR UPDATE'
    );
    $lockBooking->execute([$kode]);
    $locked = $lockBooking->fetch();

    if (!$locked) {
        $pdo->rollBack();
        http_response_code(404);
        exit('Invoice tidak ditemukan.');
    }

    if (
        $locked['status'] === 'Pending'
        && strtotime($locked['batas_pembayaran']) <= time()
    ) {
        $bookingId = (int) $locked['id_pemesanan'];

        $seatStmt = $pdo->prepare(
            "SELECT id_kursi_penerbangan
             FROM detail_pemesanan
             WHERE id_pemesanan = ?
             FOR UPDATE"
        );
        $seatStmt->execute([$bookingId]);

        $releaseSeat = $pdo->prepare(
            "UPDATE kursi_penerbangan
             SET status = 'Tersedia'
             WHERE id_kursi_penerbangan = ?
               AND status = 'Dipesan'"
        );

        foreach ($seatStmt->fetchAll() as $detail) {
            $releaseSeat->execute([$detail['id_kursi_penerbangan']]);
        }

        $expireBooking = $pdo->prepare(
            "UPDATE pemesanan
             SET status = 'Kedaluwarsa'
             WHERE id_pemesanan = ? AND status = 'Pending'"
        );
        $expireBooking->execute([$bookingId]);

        $expirePayment = $pdo->prepare(
            "UPDATE pembayaran
             SET status = 'Gagal'
             WHERE id_pemesanan = ? AND status = 'Pending'"
        );
        $expirePayment->execute([$bookingId]);
    }

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($exception->getMessage());
    http_response_code(500);
    exit('Invoice gagal dimuat. Silakan coba kembali.');
}

$stmt = $pdo->prepare(
    "SELECT
        b.kode_booking,
        b.nama_pemesan,
        b.email_pemesan,
        b.total_harga,
        b.status AS status_booking,
        b.batas_pembayaran,
        b.waktu_pemesanan,
        pay.nomor_invoice,
        pay.metode_pembayaran,
        pay.jumlah_bayar,
        pay.status AS status_pembayaran,
        pay.waktu_pembayaran,
        pen.nama_penumpang,
        pen.nomor_paspor_nik,
        f.nomor_penerbangan,
        f.bandara_asal,
        f.bandara_tujuan,
        f.waktu_keberangkatan,
        f.waktu_kedatangan,
        s.nomor_kursi,
        ps.maskapai
     FROM pemesanan b
     JOIN pembayaran pay ON pay.id_pemesanan = b.id_pemesanan
     JOIN detail_pemesanan d ON d.id_pemesanan = b.id_pemesanan
     JOIN penumpang pen ON pen.id_penumpang = d.id_penumpang
     JOIN penerbangan f ON f.id_penerbangan = d.id_penerbangan
     JOIN kursi_penerbangan kp
       ON kp.id_kursi_penerbangan = d.id_kursi_penerbangan
     JOIN kursi_pesawat s ON s.id_kursi = kp.id_kursi
     JOIN pesawat ps ON ps.id_pesawat = f.id_pesawat
     WHERE b.kode_booking = ?
     ORDER BY s.nomor_kursi"
);
$stmt->execute([$kode]);
$invoiceRows = $stmt->fetchAll();
$booking = $invoiceRows[0] ?? false;

if (!$booking) {
    http_response_code(404);
    exit('Invoice tidak ditemukan.');
}

$deadline = strtotime($booking['batas_pembayaran']);
$isPending = $booking['status_booking'] === 'Pending';
$isExpiredPending = $isPending && $deadline <= time();

function invoiceRupiah($amount): string
{
    return 'Rp' . number_format((float) $amount, 0, ',', '.');
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="booking-page">
    <div class="booking-container invoice-container">
        <a class="back-link" href="/airline-reservation/index.php">
            &larr; Kembali ke beranda
        </a>

        <div class="invoice-header">
            <p class="eyebrow">AEROBOOK · BOOKING CONFIRMATION</p>
            <h1>Invoice pemesanan</h1>
            <p>Simpan kode booking ini untuk melihat detail pemesanan Anda.</p>
        </div>

        <div class="invoice-card">
            <div class="invoice-top">
                <div>
                    <span class="summary-label">Kode booking</span>
                    <strong class="booking-code"><?= h($booking['kode_booking']) ?></strong>
                </div>
                <div class="invoice-status">
                    <span class="summary-label">Status pemesanan</span>
                    <strong><?= h($booking['status_booking']) ?></strong>
                </div>
            </div>

<div class="invoice-qr-section">
    <h2>Boarding Pass Verification</h2>

    <div id="qrcode"></div>

    <strong>
        <?= h($booking['kode_booking']) ?>
    </strong>

    <small>
        Scan QR Code untuk memeriksa validitas tiket.
    </small>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const qrContainer = document.getElementById('qrcode');

    const verificationUrl =
        window.location.origin +
        '/airline-reservation/booking/verify-ticket.php?kode=' +
        encodeURIComponent(<?= json_encode($booking['kode_booking']) ?>);

    new QRCode(qrContainer, {
        text: verificationUrl,
        width: 180,
        height: 180,
        colorDark: '#172554',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.H
    });
});
</script>
            <div class="invoice-divider"></div>

            <div class="invoice-route">
                <div>
                    <span class="summary-label">Dari</span>
                    <strong><?= h($booking['bandara_asal']) ?></strong>
                </div>
                <span class="route-arrow">&rarr;</span>
                <div>
                    <span class="summary-label">Ke</span>
                    <strong><?= h($booking['bandara_tujuan']) ?></strong>
                </div>
            </div>

            <div class="invoice-details">
                <div><span class="summary-label">Maskapai</span><strong><?= h($booking['maskapai']) ?></strong></div>
                <div><span class="summary-label">Nomor penerbangan</span><strong><?= h($booking['nomor_penerbangan']) ?></strong></div>
                <div><span class="summary-label">Keberangkatan</span><strong><?= h(date('d M Y, H:i', strtotime($booking['waktu_keberangkatan']))) ?></strong></div>
                <div><span class="summary-label">Kedatangan</span><strong><?= h(date('d M Y, H:i', strtotime($booking['waktu_kedatangan']))) ?></strong></div>
                <div><span class="summary-label">Jumlah penumpang</span><strong><?= count($invoiceRows) ?> orang</strong></div>
                <div><span class="summary-label">Kursi</span><strong><?= h(implode(', ', array_column($invoiceRows, 'nomor_kursi'))) ?></strong></div>
                <div><span class="summary-label">Email pemesan</span><strong><?= h($booking['email_pemesan']) ?></strong></div>
                <div><span class="summary-label">Nomor invoice</span><strong><?= h($booking['nomor_invoice']) ?></strong></div>
            </div>

            <div class="invoice-divider"></div>

            <div class="invoice-passenger-list">
                <h2>Daftar penumpang</h2>
                <?php foreach ($invoiceRows as $index => $ticket): ?>
                    <div class="invoice-passenger-item">
                        <strong><?= (int) ($index + 1) ?>. <?= h($ticket['nama_penumpang']) ?></strong>
                        <span><?= h($ticket['nomor_paspor_nik']) ?> · Kursi <?= h($ticket['nomor_kursi']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="invoice-divider"></div>

            <div class="invoice-total">
                <span>Total pembayaran</span>
                <strong><?= invoiceRupiah($booking['total_harga']) ?></strong>
            </div>

            <div class="invoice-payment">
                <div>
                    <span class="summary-label">Metode pembayaran</span>
                    <strong><?= h($booking['metode_pembayaran']) ?></strong>
                </div>
                <div>
                    <span class="summary-label">Status pembayaran</span>
                    <strong><?= h($booking['status_pembayaran']) ?></strong>
                </div>
                <?php if ($isPending): ?>
                    <div>
                        <span class="summary-label">Batas pembayaran</span>
                        <strong><?= h(date('d M Y, H:i', $deadline)) ?></strong>
                    </div>
                <?php endif; ?>
                <?php if ($booking['waktu_pembayaran']): ?>
                    <div>
                        <span class="summary-label">Waktu pembayaran</span>
                        <strong><?= h(date('d M Y, H:i', strtotime($booking['waktu_pembayaran']))) ?></strong>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($booking['status_booking'] === 'Kedaluwarsa' || $isExpiredPending): ?>
                <div class="invoice-notice">
                    Batas pembayaran telah berakhir. Kursi telah dilepas dan pemesanan ini tidak dapat dibayar lagi. Silakan buat pemesanan baru.
                </div>
            <?php elseif ($booking['status_pembayaran'] === 'Berhasil' && $booking['status_booking'] === 'Lunas'): ?>
                <div class="invoice-notice invoice-success">
                    Pembayaran berhasil. Simpan kode booking Anda.
                </div>
            <?php elseif ($booking['status_booking'] === 'Pending'): ?>
                <form
                    action="/airline-reservation/booking/pay.php"
                    method="POST"
                    class="payment-simulation"
                    onsubmit="return confirm('Apakah Anda yakin ingin membayar invoice ini?');"
                >
                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                    <input type="hidden" name="kode_booking" value="<?= h($booking['kode_booking']) ?>">
                    <button type="submit" class="btn btn-primary">Bayar Sekarang</button>
                </form>
            <?php elseif ($booking['status_booking'] === 'Dibatalkan'): ?>
                <div class="invoice-notice">Pemesanan ini telah dibatalkan.</div>
            <?php endif; ?>
        </div>

        <p class="invoice-disclaimer">
            AeroBook adalah proyek demonstrasi. Invoice ini bukan tiket pesawat resmi dan tidak dapat digunakan untuk perjalanan nyata.
        </p>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
