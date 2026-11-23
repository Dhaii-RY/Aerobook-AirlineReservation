
<?php

date_default_timezone_set('Asia/Makassar');

require_once __DIR__ . '/config/database.php';

$pageTitle = 'AeroBook — Pesan Tiket Pesawat';

$asal = $_GET['asal'] ?? 'DPS';
$tujuan = $_GET['tujuan'] ?? 'CGK';
$tanggal = $_GET['tanggal'] ?? date('Y-m-d', strtotime('+2 days'));

$allowedAirports = ['DPS', 'CGK', 'SUB', 'YIA', 'UPG', 'KNO', 'DJB', 'BPN'];

$airportNames = [
    'DPS' => 'Denpasar (DPS)',
    'CGK' => 'Jakarta (CGK)',
    'SUB' => 'Surabaya (SUB)',
    'YIA' => 'Yogyakarta (YIA)',
    'UPG' => 'Makassar (UPG)',
    'KNO' => 'Medan (KNO)',
    'DJB' => 'Jambi (DJB)',
    'BPN' => 'Balikpapan (BPN)',
];

$today = date('Y-m-d');

if (!in_array($asal, $allowedAirports, true)) {
    $asal = 'DPS';
}

if (!in_array($tujuan, $allowedAirports, true)) {
    $tujuan = 'CGK';
}

if ($asal === $tujuan) {
    $asal = 'DPS';
    $tujuan = 'CGK';
}

if (
    !is_string($tanggal) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) ||
    $tanggal < $today ||
    !checkdate(
        (int) substr($tanggal, 5, 2),
        (int) substr($tanggal, 8, 2),
        (int) substr($tanggal, 0, 4)
    )
) {
    $tanggal = $today;
}

$sql = "
    SELECT
        p.id_penerbangan,
        p.nomor_penerbangan,
        p.bandara_asal,
        p.bandara_tujuan,
        p.waktu_keberangkatan,
        p.waktu_kedatangan,
        p.harga,
        p.status,
        ps.maskapai,
        ps.kode_pesawat,
        ps.kapasitas
    FROM penerbangan p
    INNER JOIN pesawat ps
        ON p.id_pesawat = ps.id_pesawat
    WHERE DATE(p.waktu_keberangkatan) = :tanggal
      AND p.waktu_keberangkatan >= NOW()
      AND p.status = 'Terjadwal'
";

$params = [
    'tanggal' => $tanggal,
];

if ($asal !== '') {
    $sql .= ' AND p.bandara_asal = :asal';
    $params['asal'] = $asal;
}

if ($tujuan !== '') {
    $sql .= ' AND p.bandara_tujuan = :tujuan';
    $params['tujuan'] = $tujuan;
}

$sql .= ' ORDER BY p.waktu_keberangkatan ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$flights = $stmt->fetchAll();

function e($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function rupiah($amount): string
{
    return 'Rp ' . number_format(
        (float) $amount,
        0,
        ',',
        '.'
    );
}

function formatDateId($datetime): string
{
    $timestamp = strtotime($datetime);

    return date('d/m/Y', $timestamp);
}

function formatTime($datetime): string
{
    return date('H:i', strtotime($datetime));
}

require __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div class="container">
        <span class="eyebrow">
            <span aria-hidden="true">✦</span>
            Perjalanan dimulai di sini
        </span>

        <h1>
            Temukan penerbangan.<br>
            <span>Jelajahi lebih jauh.</span>
        </h1>

        <p class="hero-description">
            Cari jadwal penerbangan, bandingkan harga,
            dan rencanakan perjalanan Anda dalam satu tempat.
        </p>

        <form
            action="index.php"
            method="GET"
            class="search-panel"
            style="margin-top: 36px; text-align: left;"
        >
            <div class="search-grid">
                <div class="field">
                    <label for="asal">Kota asal</label>

                    <select id="asal" name="asal" required>
                        <?php foreach ($airportNames as $code => $label): ?>
                            <option value="<?= e($code) ?>" <?= $asal === $code ? 'selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="tujuan">Kota tujuan</label>

                    <select id="tujuan" name="tujuan" required>
                        <?php foreach ($airportNames as $code => $label): ?>
                            <option value="<?= e($code) ?>" <?= $tujuan === $code ? 'selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="tanggal">Tanggal keberangkatan</label>

                    <input
                        type="date"
                        id="tanggal"
                        name="tanggal"
                        value="<?= e($tanggal) ?>"
                        min="<?= e($today) ?>"
                        required
                    >
                </div>

                <button type="submit" class="btn btn-primary">
                    Cari Tiket <span aria-hidden="true">→</span>
                </button>
            </div>
        </form>
    </div>
</section>

<section class="results-section" id="flights">
    <div class="container">
        <div class="section-heading">
            <div>
                <h2>Penerbangan tersedia</h2>
                <p>
                    <?= e($airportNames[$asal] ?? 'Semua kota asal') ?>
                    &rarr;
                    <?= e($airportNames[$tujuan] ?? 'Semua kota tujuan') ?>
                    · <?= e(formatDateId($tanggal)) ?>
                </p>
            </div>

            <span class="status">
                <?= count($flights) ?> penerbangan
            </span>
        </div>

        <?php if (count($flights) > 0): ?>
            <div class="flight-list">
                <?php foreach ($flights as $flight): ?>
                    <article class="flight-card">
                        <div>
                            <div class="airline-name">
                                <?= e($flight['maskapai']) ?>
                                · <?= e($flight['nomor_penerbangan']) ?>
                            </div>

                            <div class="flight-route">
                                <div class="airport-time">
                                    <strong>
                                        <?= e(formatTime(
                                            $flight['waktu_keberangkatan']
                                        )) ?>
                                    </strong>

                                    <span>
                                        <?= e($airportNames[$flight['bandara_asal']] ?? $flight['bandara_asal']) ?>
                                    </span>
                                </div>

                                <div class="route-line">
                                    <span>
                                        <?= e(formatDateId(
                                            $flight['waktu_keberangkatan']
                                        )) ?>
                                    </span>

                                    <div class="line"></div>

                                    <span>
                                        <?= e($flight['nomor_penerbangan']) ?>
                                    </span>
                                </div>

                                <div class="airport-time">
                                    <strong>
                                        <?= e(formatTime(
                                            $flight['waktu_kedatangan']
                                        )) ?>
                                    </strong>

                                    <span>
                                        <?= e($airportNames[$flight['bandara_tujuan']] ?? $flight['bandara_tujuan']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="flight-price">
                            <span class="label">Harga per penumpang</span>

                            <strong>
                                <?= e(rupiah($flight['harga'])) ?>
                            </strong>

                            <a
                                class="btn btn-primary"
                                href="booking/seats.php?id=<?= (int) $flight['id_penerbangan'] ?>"
                            >
                                Pilih Kursi <span aria-hidden="true">→</span>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <h3>Belum ada penerbangan tersedia</h3>

                <p>
                    Coba pilih tanggal atau rute perjalanan lainnya.
                </p>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
