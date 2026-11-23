<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /airline-reservation/index.php');
    exit;
}
verifyCsrf();

$idPenerbangan = filter_input(INPUT_POST, 'id_penerbangan', FILTER_VALIDATE_INT);
$seatIds = $_POST['id_kursi_penerbangan'] ?? [];
$names = $_POST['nama_penumpang'] ?? [];
$emails = $_POST['email'] ?? [];
$identities = $_POST['nomor_paspor_nik'] ?? [];

if (!$idPenerbangan || $idPenerbangan < 1 || !is_array($seatIds)
    || count($seatIds) < 1 || count($seatIds) > 30
    || !is_array($names) || !is_array($emails) || !is_array($identities)
    || count($names) !== count($seatIds)
    || count($emails) !== count($seatIds)
    || count($identities) !== count($seatIds)) {
    http_response_code(400);
    exit('Data kursi atau penumpang tidak valid.');
}

$seatIds = array_map('intval', $seatIds);
$passengerBySeat = [];
if (in_array(0, $seatIds, true) || count(array_unique($seatIds)) !== count($seatIds)) {
    http_response_code(400);
    exit('Pilihan kursi tidak valid atau duplikat.');
}

$passengers = [];
foreach ($seatIds as $i => $seatId) {
    $name = trim((string) ($names[$i] ?? ''));
    $email = trim((string) ($emails[$i] ?? ''));
    $identity = trim((string) ($identities[$i] ?? ''));
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100
        || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150
        || mb_strlen($identity) < 5 || mb_strlen($identity) > 50) {
        http_response_code(422);
        exit('Data penumpang ke-' . ($i + 1) . ' tidak valid. Periksa nama, email, dan nomor identitas.');
    }
    $passengers[] = compact('name', 'email', 'identity');
    $passengerBySeat[$seatId] = compact('name', 'email', 'identity');
}

try {
    $pdo->beginTransaction();

    // Lepaskan kursi dari booking Pending yang sudah kedaluwarsa.
    $expired = $pdo->query(
        "SELECT id_pemesanan FROM pemesanan
         WHERE status = 'Pending' AND batas_pembayaran <= NOW() FOR UPDATE"
    )->fetchAll();

    foreach ($expired as $booking) {
        $bid = (int) $booking['id_pemesanan'];
        $q = $pdo->prepare('SELECT id_kursi_penerbangan FROM detail_pemesanan WHERE id_pemesanan = ? FOR UPDATE');
        $q->execute([$bid]);
        $release = $pdo->prepare("UPDATE kursi_penerbangan SET status = 'Tersedia' WHERE id_kursi_penerbangan = ? AND status = 'Dipesan'");
        foreach ($q->fetchAll() as $item) $release->execute([$item['id_kursi_penerbangan']]);
        $pdo->prepare("UPDATE pemesanan SET status = 'Kedaluwarsa' WHERE id_pemesanan = ?")->execute([$bid]);
        $pdo->prepare("UPDATE pembayaran SET status = 'Gagal' WHERE id_pemesanan = ? AND status = 'Pending'")->execute([$bid]);
    }

    sort($seatIds);
    $placeholders = implode(',', array_fill(0, count($seatIds), '?'));
    $q = $pdo->prepare(
        "SELECT kp.id_kursi_penerbangan, kp.status AS status_kursi,
                p.harga, p.status AS status_penerbangan,
                p.waktu_keberangkatan, p.id_penerbangan
         FROM kursi_penerbangan kp
         JOIN penerbangan p ON p.id_penerbangan = kp.id_penerbangan
         WHERE kp.id_penerbangan = ? AND kp.id_kursi_penerbangan IN ($placeholders)
         ORDER BY kp.id_kursi_penerbangan FOR UPDATE"
    );
    $q->execute(array_merge([$idPenerbangan], $seatIds));
    $seats = $q->fetchAll();

    if (count($seats) !== count($seatIds)) throw new RuntimeException('Satu atau beberapa kursi tidak ditemukan.');
    foreach ($seats as $seat) {
        if ($seat['status_kursi'] !== 'Tersedia') throw new RuntimeException('Satu atau beberapa kursi sudah dipesan. Silakan pilih ulang.');
        if ($seat['status_penerbangan'] !== 'Terjadwal' || strtotime($seat['waktu_keberangkatan']) <= time()) {
            throw new RuntimeException('Penerbangan sudah tidak dapat dipesan.');
        }
    }

    $total = (float) $seats[0]['harga'] * count($seats);
    $first = $passengers[0];
    $kodeBooking = 'AB-' . strtoupper(bin2hex(random_bytes(6)));

    $q = $pdo->prepare(
        "INSERT INTO pemesanan (kode_booking, nama_pemesan, email_pemesan, total_harga, status, batas_pembayaran)
         VALUES (?, ?, ?, ?, 'Pending', DATE_ADD(NOW(), INTERVAL 30 MINUTE))"
    );
    $q->execute([$kodeBooking, $first['name'], $first['email'], $total]);
    $bookingId = (int) $pdo->lastInsertId();

    $insertPassenger = $pdo->prepare(
        'INSERT INTO penumpang (nomor_paspor_nik, nama_penumpang, email) VALUES (?, ?, ?)'
    );
    $insertDetail = $pdo->prepare(
        'INSERT INTO detail_pemesanan (id_pemesanan, id_penerbangan, id_penumpang, id_kursi_penerbangan, harga_tiket)
         VALUES (?, ?, ?, ?, ?)'
    );
    $updateSeat = $pdo->prepare(
        "UPDATE kursi_penerbangan SET status = 'Dipesan'
         WHERE id_kursi_penerbangan = ? AND status = 'Tersedia'"
    );

    foreach ($seats as $i => $seat) {
        $passenger = $passengerBySeat[(int) $seat['id_kursi_penerbangan']];
        $insertPassenger->execute([$passenger['identity'], $passenger['name'], $passenger['email']]);
        $passengerId = (int) $pdo->lastInsertId();
        $insertDetail->execute([$bookingId, $idPenerbangan, $passengerId, $seat['id_kursi_penerbangan'], $seat['harga']]);
        $updateSeat->execute([$seat['id_kursi_penerbangan']]);
        if ($updateSeat->rowCount() !== 1) throw new RuntimeException('Kursi baru saja dipesan orang lain. Silakan pilih ulang.');
    }

    $invoice = 'INV-' . strtoupper(bin2hex(random_bytes(6)));
    $q = $pdo->prepare(
        "INSERT INTO pembayaran (id_pemesanan, nomor_invoice, metode_pembayaran, jumlah_bayar, status)
         VALUES (?, ?, 'Transfer', ?, 'Pending')"
    );
    $q->execute([$bookingId, $invoice, $total]);

    $pdo->commit();
    header('Location: /airline-reservation/booking/invoice.php?kode=' . rawurlencode($kodeBooking));
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log($e->getMessage());
    http_response_code(500);
    exit('Terjadi kesalahan database. Silakan coba kembali.');
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(409);
    $pageTitle = 'Pemesanan Tidak Berhasil — AeroBook';
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <section class="booking-page"><div class="booking-container">
        <h1>Pemesanan belum berhasil</h1>
        <p><?= h($e->getMessage()) ?></p>
        <a class="btn btn-primary" href="/airline-reservation/booking/seats.php?id=<?= (int) $idPenerbangan ?>">Pilih kursi lain</a>
    </div></section>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log($e->getMessage());
    http_response_code(500);
    exit('Terjadi kesalahan saat memproses pemesanan.');
}
