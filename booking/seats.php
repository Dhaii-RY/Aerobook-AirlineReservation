
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

$pageTitle = 'Pilih Kursi — AeroBook';
$extraCss = ['/airline-reservation/assets/css/booking.css'];

$idPenerbangan = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$idPenerbangan || $idPenerbangan < 1) {
    http_response_code(400);
    exit('ID penerbangan tidak valid.');
}

// Ambil penerbangan yang masih bisa dipesan.
$stmt = $pdo->prepare(
    "SELECT p.*, ps.maskapai, ps.kode_pesawat, ps.kapasitas
     FROM penerbangan p
     JOIN pesawat ps ON ps.id_pesawat = p.id_pesawat
     WHERE p.id_penerbangan = ?
       AND p.status = 'Terjadwal'
       AND p.waktu_keberangkatan > NOW()
     LIMIT 1"
);
$stmt->execute([$idPenerbangan]);
$flight = $stmt->fetch();

if (!$flight) {
    http_response_code(404);
    exit('Penerbangan tidak ditemukan atau sudah tidak tersedia.');
}

$capacity = (int) $flight['kapasitas'];

if ($capacity < 1 || $capacity > 1000) {
    http_response_code(500);
    exit('Kapasitas pesawat tidak valid.');
}

// Buat susunan kursi berdasarkan kapasitas pesawat.
// Setiap baris memiliki maksimal enam kursi: A sampai F.
try {
    $pdo->beginTransaction();

    $columns = ['A', 'B', 'C', 'D', 'E', 'F'];

    for ($i = 0; $i < $capacity; $i++) {
        $rowNumber = intdiv($i, 6) + 1;
        $column = $columns[$i % 6];
        $seatNumber = $rowNumber . $column;

        $insertSeat = $pdo->prepare(
            "INSERT IGNORE INTO kursi_pesawat
                (id_pesawat, nomor_kursi, kelas)
             VALUES (?, ?, 'Ekonomi')"
        );

        $insertSeat->execute([
            $flight['id_pesawat'],
            $seatNumber
        ]);
    }

    // Ambil kursi sesuai kapasitas pesawat.
    $seatQuery = $pdo->prepare(
        "SELECT id_kursi, nomor_kursi
         FROM kursi_pesawat
         WHERE id_pesawat = ?
         ORDER BY id_kursi
         LIMIT " . $capacity
    );

    $seatQuery->execute([$flight['id_pesawat']]);
    $planeSeats = $seatQuery->fetchAll();

    // Daftarkan kursi tersebut untuk penerbangan ini.
    $insertFlightSeat = $pdo->prepare(
        "INSERT IGNORE INTO kursi_penerbangan
            (id_penerbangan, id_kursi, status)
         VALUES (?, ?, 'Tersedia')"
    );

    foreach ($planeSeats as $seat) {
        $insertFlightSeat->execute([
            $idPenerbangan,
            $seat['id_kursi']
        ]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());
    http_response_code(500);
    exit('Gagal menyiapkan data kursi. Silakan coba kembali.');
}

// Ambil status setiap kursi.
$stmt = $pdo->prepare(
    "SELECT kp.id_kursi_penerbangan,
            kp.status,
            k.nomor_kursi,
            k.kelas
     FROM kursi_penerbangan kp
     JOIN kursi_pesawat k ON k.id_kursi = kp.id_kursi
     WHERE kp.id_penerbangan = ?
     ORDER BY k.id_kursi"
);
$stmt->execute([$idPenerbangan]);
$seats = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<section class="booking-page">
    <div class="booking-container">
        <a class="back-link" href="/airline-reservation/index.php">
            &larr; Kembali ke pencarian
        </a>

        <div class="booking-heading">
            <p class="eyebrow">LANGKAH 1 DARI 3</p>
            <h1>Pilih kursi Anda</h1>
            <p>
                Pilih satu atau beberapa kursi yang masih tersedia. Setiap kursi memiliki data penumpang masing-masing.
            </p>
        </div>

        <div class="flight-summary">
            <div>
                <span class="summary-label">Maskapai</span>
                <strong><?= h($flight['maskapai']) ?></strong>
                <span class="summary-muted">
                    <?= h($flight['nomor_penerbangan']) ?>
                </span>
            </div>

            <div>
                <span class="summary-label">Rute</span>
                <strong>
                    <?= h($flight['bandara_asal']) ?>
                    &rarr;
                    <?= h($flight['bandara_tujuan']) ?>
                </strong>
                <span class="summary-muted">
                    <?= h(date(
                        'd M Y, H:i',
                        strtotime($flight['waktu_keberangkatan'])
                    )) ?>
                </span>
            </div>

            <div>
                <span class="summary-label">Harga per tiket</span>
                <strong>
                    Rp<?= number_format(
                        (float) $flight['harga'],
                        0,
                        ',',
                        '.'
                    ) ?>
                </strong>
            </div>
        </div>

        <div class="seat-layout">
            <div class="seat-legend">
                <span><i class="seat-key available"></i> Tersedia</span>
                <span><i class="seat-key selected"></i> Pilihan Anda</span>
                <span><i class="seat-key occupied"></i> Tidak tersedia</span>
            </div>

            <div class="aircraft-label">BAGIAN DEPAN PESAWAT</div>

            <div class="seat-grid">
                <?php
                $seatRows = [];
                foreach ($seats as $seat) {
                    $row = (int) preg_replace(
                        '/[^0-9]/',
                        '',
                        $seat['nomor_kursi']
                    );

                    $seatRows[$row][] = $seat;
                }
                ksort($seatRows);
                ?>

                <?php foreach ($seatRows as $rowNumber => $rowSeats): ?>
                    <div class="seat-row">
                        <span class="row-number">
                            <?= (int) $rowNumber ?>
                        </span>

                        <?php foreach ($rowSeats as $index => $seat): ?>
                            <?php if ($index === 3): ?>
                                <span class="seat-aisle"></span>
                            <?php endif; ?>

                            <?php
                            $available = $seat['status'] === 'Tersedia';
                            $seatId = (int) $seat['id_kursi_penerbangan'];
                            ?>

                            <button
                                type="button"
                                class="seat-button <?= $available
                                    ? 'is-available'
                                    : 'is-occupied' ?>"
                                <?= !$available ? 'disabled' : '' ?>
                                data-seat-id="<?= $seatId ?>"
                                data-seat-number="<?= h($seat['nomor_kursi']) ?>"
                                aria-label="Kursi <?= h($seat['nomor_kursi']) ?>, <?= $available ? 'tersedia' : 'tidak tersedia' ?>"
                            >
                                <?= h($seat['nomor_kursi']) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="aircraft-label">BAGIAN BELAKANG PESAWAT</div>
            
        </div>

        <form
            action="/airline-reservation/booking/checkout.php"
            method="POST"
            class="seat-checkout"
            id="seatForm"
        >
            <input
                type="hidden"
                name="csrf_token"
                value="<?= h(csrfToken()) ?>"
            >

            <input
                type="hidden"
                name="id_penerbangan"
                value="<?= (int) $idPenerbangan ?>"
            >


            <input
                type="hidden"
                name="id_kursi_penerbangan[]"
                id="selectedSeatIds"
                value=""
            >

            <div class="passenger-form" id="passengerForms">
                <h2>Data penumpang</h2>
                <p>Isi identitas setiap orang sesuai kursi yang dipilih.</p>
                <div id="passengerFields">
                    <p class="summary-muted">Belum ada kursi yang dipilih.</p>
                </div>
            </div>

            <div>
                <span class="summary-label">Kursi pilihan</span>
                <strong id="selectedSeatLabel">Belum dipilih</strong>
                <span class="summary-muted" id="selectedSeatTotal">0 kursi dipilih</span>
            </div>

            <button type="submit" class="btn btn-primary" id="continueButton" disabled>
                Lanjutkan pemesanan
            </button>
        </form>
    </div>
</section>

<script src="/airline-reservation/assets/js/seats.js" defer></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
