
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /airline-reservation/index.php');
    exit;
}

verifyCsrf();

$kode = strtoupper(trim($_POST['kode_booking'] ?? ''));

if (!preg_match('/^AB-[A-F0-9]{12}$/', $kode)) {
    http_response_code(400);
    exit('Kode booking tidak valid.');
}

try {
    $pdo->beginTransaction();

    // Kunci pemesanan agar tidak diproses dua kali secara bersamaan.
    $stmt = $pdo->prepare(
        'SELECT id_pemesanan, status, batas_pembayaran
         FROM pemesanan
         WHERE kode_booking = ?
         FOR UPDATE'
    );
    $stmt->execute([$kode]);
    $booking = $stmt->fetch();

    if (!$booking) {
        throw new RuntimeException('Pemesanan tidak ditemukan.');
    }

    $bookingId = (int) $booking['id_pemesanan'];

    if ($booking['status'] !== 'Pending') {
        throw new RuntimeException(
            'Pemesanan ini bukan berstatus Pending. Periksa invoice Anda.'
        );
    }

    // Ambil detail dan kunci kursi yang terkait.
    $stmt = $pdo->prepare(
        'SELECT d.id_kursi_penerbangan,
                kp.status AS status_kursi
         FROM detail_pemesanan d
         JOIN kursi_penerbangan kp
           ON kp.id_kursi_penerbangan = d.id_kursi_penerbangan
         WHERE d.id_pemesanan = ?
         FOR UPDATE'
    );
    $stmt->execute([$bookingId]);
    $details = $stmt->fetchAll();

    if (count($details) < 1) {
        throw new RuntimeException('Detail tiket tidak tersedia atau tidak valid.');
    }

    // Kunci pembayaran.
    $stmt = $pdo->prepare(
        'SELECT id_pembayaran, status
         FROM pembayaran
         WHERE id_pemesanan = ?
         FOR UPDATE'
    );
    $stmt->execute([$bookingId]);
    $payment = $stmt->fetch();

    if (!$payment || $payment['status'] !== 'Pending') {
        throw new RuntimeException(
            'Pembayaran tidak ditemukan atau sudah diproses.'
        );
    }

    // Periksa waktu dari database, bukan jam browser.
    $stmt = $pdo->prepare(
        'SELECT batas_pembayaran > NOW() AS masih_berlaku
         FROM pemesanan
         WHERE id_pemesanan = ?'
    );
    $stmt->execute([$bookingId]);
    $deadline = $stmt->fetch();

    if ((int) $deadline['masih_berlaku'] !== 1) {
        // Tutup pemesanan dan lepaskan kursi.
        $stmt = $pdo->prepare(
            "UPDATE pemesanan
             SET status = 'Kedaluwarsa'
             WHERE id_pemesanan = ?"
        );
        $stmt->execute([$bookingId]);

        $stmt = $pdo->prepare(
            "UPDATE pembayaran
             SET status = 'Gagal'
             WHERE id_pembayaran = ?"
        );
        $stmt->execute([$payment['id_pembayaran']]);

        foreach ($details as $detail) {
            $stmt = $pdo->prepare(
                "UPDATE kursi_penerbangan
                 SET status = 'Tersedia'
                 WHERE id_kursi_penerbangan = ?
                   AND status = 'Dipesan'"
            );
            $stmt->execute([
                $detail['id_kursi_penerbangan']
            ]);
        }

        $pdo->commit();

        header(
            'Location: invoice.php?kode='
            . rawurlencode($kode)
            . '&payment=expired'
        );
        exit;
    }

    foreach ($details as $detail) {
        if ($detail['status_kursi'] !== 'Dipesan') {
            throw new RuntimeException('Satu atau beberapa kursi tidak lagi berstatus Dipesan.');
        }
    }

    // SIMULASI: tidak ada uang sungguhan yang ditransfer.
    $stmt = $pdo->prepare(
        "UPDATE pembayaran
         SET status = 'Berhasil',
             waktu_pembayaran = NOW()
         WHERE id_pembayaran = ?
           AND status = 'Pending'"
    );
    $stmt->execute([$payment['id_pembayaran']]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException(
            'Status pembayaran berubah. Silakan periksa invoice.'
        );
    }

    $stmt = $pdo->prepare(
        "UPDATE pemesanan
         SET status = 'Lunas'
         WHERE id_pemesanan = ?
           AND status = 'Pending'"
    );
    $stmt->execute([$bookingId]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException(
            'Status pemesanan gagal diperbarui.'
        );
    }

    $stmt = $pdo->prepare(
        "UPDATE kursi_penerbangan
         SET status = 'Terjual'
         WHERE id_kursi_penerbangan = ?
           AND status = 'Dipesan'"
    );
    foreach ($details as $detail) {
        $stmt->execute([$detail['id_kursi_penerbangan']]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Status salah satu kursi gagal diperbarui.');
        }
    }

    $pdo->commit();

    header(
        'Location: invoice.php?kode='
        . rawurlencode($kode)
        . '&payment=success'
    );
    exit;


} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());
    http_response_code(500);
    exit('Terjadi kesalahan database. Silakan coba kembali.');

} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(409);
    $pageTitle = 'Pembayaran Belum Berhasil — AeroBook';
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <section class="booking-page">
        <div class="booking-container">
            <h1>Pembayaran belum berhasil</h1>
            <p><?= h($e->getMessage()) ?></p>
            <a href="/airline-reservation/index.php">
                Kembali ke beranda
            </a>
        </div>
    </section>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());
    http_response_code(500);
    exit('Terjadi kesalahan. Silakan coba kembali.');
}
