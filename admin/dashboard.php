<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

requireAdmin();

$pageTitle = 'Dashboard Admin | AeroBook';

$stats = [
    'pesawat' => (int) $pdo->query(
        'SELECT COUNT(*) FROM pesawat'
    )->fetchColumn(),

    'penerbangan' => (int) $pdo->query(
        "SELECT COUNT(*) FROM penerbangan
         WHERE status = 'Terjadwal'
           AND waktu_keberangkatan >= NOW()"
    )->fetchColumn(),

    'pending' => (int) $pdo->query(
        "SELECT COUNT(*) FROM pemesanan
         WHERE status = 'Pending'"
    )->fetchColumn(),
];

$stmt = $pdo->query(
    "SELECT nomor_penerbangan, bandara_asal, bandara_tujuan,
            waktu_keberangkatan, status
     FROM penerbangan
     ORDER BY waktu_keberangkatan DESC
     LIMIT 8"
);

$recentFlights = $stmt->fetchAll();

function dashboardDate($value): string
{
    return date('d M Y, H:i', strtotime($value));
}

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
    <?php $activeAdminPage = 'dashboard'; require __DIR__ . '/../includes/admin-sidebar.php'; ?>

    <section class="admin-main">
        <div class="admin-topbar">
            <div>
                <h1>Dashboard</h1>
                <p>Ringkasan operasional AeroBook.</p>
            </div>

            <div class="admin-user">
                <span class="admin-avatar">A</span>
                <span><?= h($_SESSION['admin_name'] ?? 'Administrator') ?></span>
            </div>
        </div>

        <div class="stat-grid">
            <article class="stat-card">
                <span class="stat-label">Total pesawat</span>
                <div class="stat-number"><?= $stats['pesawat'] ?></div>
                <p class="stat-note">Armada terdaftar dalam sistem</p>
            </article>

            <article class="stat-card">
                <span class="stat-label">Penerbangan aktif</span>
                <div class="stat-number"><?= $stats['penerbangan'] ?></div>
                <p class="stat-note">Jadwal terjadwal yang belum berangkat</p>
            </article>

            <article class="stat-card">
                <span class="stat-label">Menunggu pembayaran</span>
                <div class="stat-number"><?= $stats['pending'] ?></div>
                <p class="stat-note">Pemesanan berstatus Pending</p>
            </article>
        </div>

        <section class="admin-panel">
            <div class="panel-heading">
                <h2>Data penerbangan terbaru</h2>
                <a class="text-link" href="penerbangan.php">
                    Kelola penerbangan →
                </a>
            </div>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Nomor</th>
                            <th>Rute</th>
                            <th>Keberangkatan</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if (!$recentFlights): ?>
                        <tr>
                            <td colspan="4">Belum ada data penerbangan.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentFlights as $flight): ?>
                            <tr>
                                <td><?= h($flight['nomor_penerbangan']) ?></td>
                                <td>
                                    <?= h($flight['bandara_asal']) ?>
                                    → <?= h($flight['bandara_tujuan']) ?>
                                </td>
                                <td><?= h(dashboardDate(
                                    $flight['waktu_keberangkatan']
                                )) ?></td>
                                <td><?= h($flight['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </section>
</div>

</body>
</html>
