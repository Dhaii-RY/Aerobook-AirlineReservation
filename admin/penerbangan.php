<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

requireAdmin();

$pageTitle = 'Manajemen Penerbangan — AeroBook';

$airports = [
    'DPS' => 'Denpasar (DPS)',
    'CGK' => 'Jakarta (CGK)',
    'SUB' => 'Surabaya (SUB)',
    'YIA' => 'Yogyakarta (YIA)',
    'UPG' => 'Makassar (UPG)',
    'KNO' => 'Medan (KNO)',
    'DJB' => 'Jambi (DJB)',
    'BPN' => 'Balikpapan (BPN)',
];

$statuses = ['Terjadwal', 'Ditunda', 'Dibatalkan'];

$errors = [];
$notice = '';
$editing = null;

$form = [
    'id_penerbangan' => '',
    'nomor_penerbangan' => '',
    'id_pesawat' => '',
    'bandara_asal' => 'DPS',
    'bandara_tujuan' => 'CGK',
    'waktu_keberangkatan' => '',
    'waktu_kedatangan' => '',
    'harga' => '',
    'status' => 'Terjadwal',
];

function redirectFlights(string $query): void
{
    header('Location: penerbangan.php?' . $query);
    exit;
}

// Ambil daftar pesawat untuk pilihan pada formulir.
$planes = $pdo->query(
    'SELECT id_pesawat, kode_pesawat, maskapai, kapasitas
     FROM pesawat
     ORDER BY maskapai, kode_pesawat'
)->fetchAll();

// Tangani formulir tambah, edit, dan hapus.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = filter_var(
            $_POST['id_penerbangan'] ?? '',
            FILTER_VALIDATE_INT
        );

        if (!$id || $id < 1) {
            $errors[] = 'ID penerbangan tidak valid.';
        } else {
            try {
                $stmt = $pdo->prepare(
                    'DELETE FROM penerbangan
                     WHERE id_penerbangan = ?'
                );
                $stmt->execute([$id]);

                if ($stmt->rowCount() > 0) {
                    redirectFlights('deleted=1');
                }

                $errors[] = 'Penerbangan tidak ditemukan.';
            } catch (PDOException $e) {
                error_log($e->getMessage());
                $errors[] =
                    'Penerbangan tidak dapat dihapus karena masih memiliki data terkait, seperti kursi atau pemesanan.';
            }
        }
    } elseif ($action === 'save') {
        $id = filter_var(
            $_POST['id_penerbangan'] ?? '',
            FILTER_VALIDATE_INT
        );

        $form = [
            'id_penerbangan' => $id && $id > 0 ? (string) $id : '',
            'nomor_penerbangan' => trim(
                $_POST['nomor_penerbangan'] ?? ''
            ),
            'id_pesawat' => trim($_POST['id_pesawat'] ?? ''),
            'bandara_asal' => trim($_POST['bandara_asal'] ?? ''),
            'bandara_tujuan' => trim($_POST['bandara_tujuan'] ?? ''),
            'waktu_keberangkatan' => trim(
                $_POST['waktu_keberangkatan'] ?? ''
            ),
            'waktu_kedatangan' => trim(
                $_POST['waktu_kedatangan'] ?? ''
            ),
            'harga' => trim($_POST['harga'] ?? ''),
            'status' => trim($_POST['status'] ?? ''),
        ];

        // Validasi nomor penerbangan.
        if (
            !preg_match(
                '/^[A-Za-z0-9-]{2,20}$/',
                $form['nomor_penerbangan']
            )
        ) {
            $errors[] =
                'Nomor penerbangan harus terdiri dari 2–20 karakter berupa huruf, angka, atau tanda hubung.';
        }

        // Validasi pesawat.
        $planeId = filter_var(
            $form['id_pesawat'],
            FILTER_VALIDATE_INT
        );

        $validPlane = false;

        if ($planeId && $planeId > 0) {
            foreach ($planes as $plane) {
                if ((int) $plane['id_pesawat'] === $planeId) {
                    $validPlane = true;
                    break;
                }
            }
        }

        if (!$validPlane) {
            $errors[] =
                'Pilih pesawat yang tersedia di database.';
        }

        // Validasi bandara.
        if (!isset($airports[$form['bandara_asal']])) {
            $errors[] = 'Bandara asal tidak valid.';
        }

        if (!isset($airports[$form['bandara_tujuan']])) {
            $errors[] = 'Bandara tujuan tidak valid.';
        }

        if (
            $form['bandara_asal'] === $form['bandara_tujuan']
        ) {
            $errors[] =
                'Bandara asal dan tujuan tidak boleh sama.';
        }

        // Validasi waktu lokal dari input datetime-local.
        $departure = DateTime::createFromFormat(
            '!Y-m-d\TH:i',
            $form['waktu_keberangkatan']
        );

        $arrival = DateTime::createFromFormat(
            '!Y-m-d\TH:i',
            $form['waktu_kedatangan']
        );

        $departureValid =
            $departure !== false
            && $departure->format('Y-m-d\TH:i')
                === $form['waktu_keberangkatan'];

        $arrivalValid =
            $arrival !== false
            && $arrival->format('Y-m-d\TH:i')
                === $form['waktu_kedatangan'];

        if (!$departureValid || !$arrivalValid) {
            $errors[] =
                'Format tanggal atau waktu penerbangan tidak valid.';
        } else {

            if ($arrival <= $departure) {
                $errors[] =
                    'Waktu kedatangan harus setelah waktu keberangkatan.';
            }
        }

        // Validasi harga.
        $priceValid = preg_match(
            '/^\d{1,10}(\.\d{1,2})?$/',
            $form['harga']
        );

        if (!$priceValid) {
            $errors[] =
                'Harga harus berupa angka nonnegatif dengan maksimal dua angka desimal.';
        }

        if (!in_array($form['status'], $statuses, true)) {
            $errors[] = 'Status penerbangan tidak valid.';
        }

        // Jika penerbangan sudah memiliki pemesanan,
        // jangan mengubah identitas, rute, pesawat, atau jadwalnya.
        if (!$errors && $form['id_penerbangan'] !== '') {
            $stmt = $pdo->prepare(
                'SELECT p.*,
                    EXISTS (
                        SELECT 1
                        FROM detail_pemesanan d
                        WHERE d.id_penerbangan = p.id_penerbangan
                    ) AS memiliki_pemesanan
                 FROM penerbangan p
                 WHERE p.id_penerbangan = ?'
            );

            $stmt->execute([$form['id_penerbangan']]);
            $existing = $stmt->fetch();

            if (!$existing) {
                $errors[] = 'Penerbangan yang diedit tidak ditemukan.';
            } elseif ($existing['memiliki_pemesanan']) {
                $departureDb = $departure->format('Y-m-d H:i:s');
                $arrivalDb = $arrival->format('Y-m-d H:i:s');

                $coreChanged =
                    $existing['nomor_penerbangan']
                        !== $form['nomor_penerbangan']
                    || (int) $existing['id_pesawat'] !== $planeId
                    || $existing['bandara_asal'] !== $form['bandara_asal']
                    || $existing['bandara_tujuan'] !== $form['bandara_tujuan']
                    || $existing['waktu_keberangkatan'] !== $departureDb
                    || $existing['waktu_kedatangan'] !== $arrivalDb;

                if ($coreChanged) {
                    $errors[] =
                        'Penerbangan ini sudah memiliki pemesanan. Nomor penerbangan, pesawat, rute, dan jadwal tidak dapat diubah.';
                }
            }
        }

        // Simpan data hanya jika seluruh validasi berhasil.
        if (!$errors) {
            try {
                $departureDb = $departure->format('Y-m-d H:i:s');
                $arrivalDb = $arrival->format('Y-m-d H:i:s');
                $price = number_format(
                    (float) $form['harga'],
                    2,
                    '.',
                    ''
                );

                if ($form['id_penerbangan'] !== '') {
                    $stmt = $pdo->prepare(
                        'UPDATE penerbangan
                         SET nomor_penerbangan = ?,
                             id_pesawat = ?,
                             bandara_asal = ?,
                             bandara_tujuan = ?,
                             waktu_keberangkatan = ?,
                             waktu_kedatangan = ?,
                             harga = ?,
                             status = ?
                         WHERE id_penerbangan = ?'
                    );

                    $stmt->execute([
                        $form['nomor_penerbangan'],
                        $planeId,
                        $form['bandara_asal'],
                        $form['bandara_tujuan'],
                        $departureDb,
                        $arrivalDb,
                        $price,
                        $form['status'],
                        $form['id_penerbangan'],
                    ]);

                    redirectFlights('updated=1');
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO penerbangan (
                            nomor_penerbangan,
                            id_pesawat,
                            bandara_asal,
                            bandara_tujuan,
                            waktu_keberangkatan,
                            waktu_kedatangan,
                            harga,
                            status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                    );

                    $stmt->execute([
                        $form['nomor_penerbangan'],
                        $planeId,
                        $form['bandara_asal'],
                        $form['bandara_tujuan'],
                        $departureDb,
                        $arrivalDb,
                        $price,
                        $form['status'],
                    ]);

                    redirectFlights('created=1');
                }
            } catch (PDOException $e) {
                error_log($e->getMessage());

                if ($e->getCode() === '23000') {
                    $errors[] =
                        'Data bertentangan dengan data yang sudah ada. Periksa nomor penerbangan dan relasi database.';
                } else {
                    $errors[] =
                        'Data gagal disimpan. Silakan periksa database dan coba kembali.';
                }
            }
        }
    }
}

// Muat data untuk mode edit.
if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
    && isset($_GET['edit'])
) {
    $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);

    if (!$editId || $editId < 1) {
        $errors[] = 'ID penerbangan tidak valid.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT * FROM penerbangan
             WHERE id_penerbangan = ?'
        );
        $stmt->execute([$editId]);
        $editing = $stmt->fetch();

        if ($editing) {
            $form = [
                'id_penerbangan' => (string) $editing['id_penerbangan'],
                'nomor_penerbangan' => $editing['nomor_penerbangan'],
                'id_pesawat' => (string) $editing['id_pesawat'],
                'bandara_asal' => $editing['bandara_asal'],
                'bandara_tujuan' => $editing['bandara_tujuan'],
                'waktu_keberangkatan' => date(
                    'Y-m-d\TH:i',
                    strtotime($editing['waktu_keberangkatan'])
                ),
                'waktu_kedatangan' => date(
                    'Y-m-d\TH:i',
                    strtotime($editing['waktu_kedatangan'])
                ),
                'harga' => $editing['harga'],
                'status' => $editing['status'],
            ];
        } else {
            $errors[] = 'Penerbangan tidak ditemukan.';
        }
    }
}

// Pesan setelah redirect.
if (isset($_GET['created'])) {
    $notice = 'Penerbangan berhasil ditambahkan.';
} elseif (isset($_GET['updated'])) {
    $notice = 'Data penerbangan berhasil diperbarui.';
} elseif (isset($_GET['deleted'])) {
    $notice = 'Penerbangan berhasil dihapus.';
}

// Ambil seluruh penerbangan.
$flights = $pdo->query(
    'SELECT p.*, ps.kode_pesawat, ps.maskapai
     FROM penerbangan p
     INNER JOIN pesawat ps
        ON ps.id_pesawat = p.id_pesawat
     ORDER BY p.waktu_keberangkatan DESC'
)->fetchAll();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>

    <link rel="stylesheet" href="/airline-reservation/assets/css/style.css">
    <link rel="stylesheet" href="/airline-reservation/assets/css/admin.css">
</head>
<body>

<div class="admin-layout">
    <?php $activeAdminPage = 'penerbangan'; require __DIR__ . '/../includes/admin-sidebar.php'; ?>

    <section class="admin-main">
        <div class="admin-topbar">
            <div>
                <p class="eyebrow">MANAJEMEN DATA</p>
                <h1>Penerbangan</h1>
                <p class="admin-subtitle">
                    Kelola jadwal dan informasi penerbangan.
                </p>
            </div>
        </div>

        <?php if ($notice !== ''): ?>
            <div class="notice success">
                <?= h($notice) ?>
            </div>
        <?php endif; ?>

        <?php foreach ($errors as $error): ?>
            <div class="notice error">
                <?= h($error) ?>
            </div>
        <?php endforeach; ?>

        <div class="admin-panel">
            <div class="panel-heading">
                <div>
                    <h2>
                        <?= $form['id_penerbangan'] !== ''
                            ? 'Edit Penerbangan'
                            : 'Tambah Penerbangan' ?>
                    </h2>
                    <p>
                        Isi informasi penerbangan secara lengkap.
                    </p>
                </div>
            </div>

            <?php if (count($planes) === 0): ?>
                <div class="notice error">
                    Belum ada data pesawat.
                    Tambahkan pesawat terlebih dahulu di menu
                    <a href="pesawat.php">Data Pesawat</a>.
                </div>
            <?php else: ?>
                <form method="POST" class="admin-form">
                    <input type="hidden" name="action" value="save">
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= h(csrfToken()) ?>"
                    >
                    <input
                        type="hidden"
                        name="id_penerbangan"
                        value="<?= h($form['id_penerbangan']) ?>"
                    >

                    <div class="form-grid">
                        <div class="admin-field">
                            <label for="nomor_penerbangan">
                                Nomor Penerbangan
                            </label>
                            <input
                                id="nomor_penerbangan"
                                name="nomor_penerbangan"
                                type="text"
                                maxlength="20"
                                placeholder="Contoh: GA-200"
                                value="<?= h($form['nomor_penerbangan']) ?>"
                                required
                            >
                        </div>

                        <div class="admin-field">
                            <label for="id_pesawat">Pesawat</label>
                            <select id="id_pesawat"
                                    name="id_pesawat"
                                    required>
                                <option value="">Pilih pesawat</option>
                                <?php foreach ($planes as $plane): ?>
                                    <option
                                        value="<?= (int) $plane['id_pesawat'] ?>"
                                        <?= (string) $plane['id_pesawat']
                                            === $form['id_pesawat']
                                            ? 'selected' : '' ?>
                                    >
                                        <?= h(
                                            $plane['maskapai'] . ' — '
                                            . $plane['kode_pesawat']
                                            . ' (' . $plane['kapasitas']
                                            . ' kursi)'
                                        ) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="admin-field">
                            <label for="bandara_asal">Bandara Asal</label>
                            <select id="bandara_asal"
                                    name="bandara_asal"
                                    required>
                                <?php foreach ($airports as $code => $name): ?>
                                    <option
                                        value="<?= h($code) ?>"
                                        <?= $form['bandara_asal'] === $code
                                            ? 'selected' : '' ?>
                                    >
                                        <?= h($name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="admin-field">
                            <label for="bandara_tujuan">
                                Bandara Tujuan
                            </label>
                            <select id="bandara_tujuan"
                                    name="bandara_tujuan"
                                    required>
                                <?php foreach ($airports as $code => $name): ?>
                                    <option
                                        value="<?= h($code) ?>"
                                        <?= $form['bandara_tujuan'] === $code
                                            ? 'selected' : '' ?>
                                    >
                                        <?= h($name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="admin-field">
                            <label for="waktu_keberangkatan">
                                Waktu Keberangkatan
                            </label>
                            <input
                                id="waktu_keberangkatan"
                                name="waktu_keberangkatan"
                                type="datetime-local"
                                value="<?= h($form['waktu_keberangkatan']) ?>"
                                required
                            >
                        </div>

                        <div class="admin-field">
                            <label for="waktu_kedatangan">
                                Waktu Kedatangan
                            </label>
                            <input
                                id="waktu_kedatangan"
                                name="waktu_kedatangan"
                                type="datetime-local"
                                value="<?= h($form['waktu_kedatangan']) ?>"
                                required
                            >
                        </div>

                        <div class="admin-field">
                            <label for="harga">Harga Tiket (Rp)</label>
                            <input
                                id="harga"
                                name="harga"
                                type="number"
                                min="0"
                                max="9999999999.99"
                                step="0.01"
                                placeholder="850000"
                                value="<?= h($form['harga']) ?>"
                                required
                            >
                        </div>

                        <div class="admin-field">
                            <label for="status">Status</label>
                            <select id="status" name="status" required>
                                <?php foreach ($statuses as $status): ?>
                                    <option
                                        value="<?= h($status) ?>"
                                        <?= $form['status'] === $status
                                            ? 'selected' : '' ?>
                                    >
                                        <?= h($status) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <?= $form['id_penerbangan'] !== ''
                                ? 'Simpan Perubahan'
                                : 'Tambah Penerbangan' ?>
                        </button>

                        <?php if ($form['id_penerbangan'] !== ''): ?>
                            <a href="penerbangan.php"
                               class="btn btn-secondary">
                                Batal
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <div class="admin-panel">
            <div class="panel-heading">
                <div>
                    <h2>Daftar Penerbangan</h2>
                    <p>
                        Total <?= count($flights) ?>
                        data penerbangan.
                    </p>
                </div>
            </div>

            <div class="table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Penerbangan</th>
                            <th>Rute</th>
                            <th>Keberangkatan</th>
                            <th>Harga</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$flights): ?>
                            <tr>
                                <td colspan="6" class="empty-state">
                                    Belum ada data penerbangan.
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($flights as $flight): ?>
                            <tr>
                                <td>
                                    <strong>
                                        <?= h($flight['nomor_penerbangan']) ?>
                                    </strong>
                                    <span class="table-subtext">
                                        <?= h($flight['maskapai']) ?>
                                        ·
                                        <?= h($flight['kode_pesawat']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= h($flight['bandara_asal']) ?>
                                    →
                                    <?= h($flight['bandara_tujuan']) ?>
                                </td>
                                <td>
                                    <?= h(date(
                                        'd M Y, H:i',
                                        strtotime(
                                            $flight['waktu_keberangkatan']
                                        )
                                    )) ?>
                                </td>
                                <td>
                                    Rp<?= number_format(
                                        (float) $flight['harga'],
                                        0,
                                        ',',
                                        '.'
                                    ) ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= strtolower(
                                        h($flight['status'])
                                    ) ?>">
                                        <?= h($flight['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <a
                                            href="penerbangan.php?edit=<?= (int) $flight['id_penerbangan'] ?>"
                                            class="btn btn-small btn-secondary"
                                        >
                                            Edit
                                        </a>

                                        <form
                                            method="POST"
                                            onsubmit="return confirm('Yakin ingin menghapus penerbangan ini?');"
                                        >
                                            <input
                                                type="hidden"
                                                name="action"
                                                value="delete"
                                            >
                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= h(csrfToken()) ?>"
                                            >
                                            <input
                                                type="hidden"
                                                name="id_penerbangan"
                                                value="<?= (int) $flight['id_penerbangan'] ?>"
                                            >
                                            <button
                                                type="submit"
                                                class="btn btn-small btn-danger"
                                            >
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

</body>
</html>