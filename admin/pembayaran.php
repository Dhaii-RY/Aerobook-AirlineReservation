<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

requireAdmin();

$pageTitle = 'Data Pembayaran — AeroBook';

$keyword = trim((string) ($_GET['q'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$allowedStatuses = ['Pending', 'Berhasil', 'Gagal'];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$where = [];
$params = [];

if ($keyword !== '') {
    $where[] = "(
        pb.nomor_invoice LIKE :invoice
        OR pm.kode_booking LIKE :booking
        OR pm.nama_pemesan LIKE :nama
        OR pm.email_pemesan LIKE :email
    )";
    $search = '%' . $keyword . '%';
    $params['invoice'] = $search;
    $params['booking'] = $search;
    $params['nama'] = $search;
    $params['email'] = $search;
}

if ($statusFilter !== '') {
    $where[] = 'pb.status = :status';
    $params['status'] = $statusFilter;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    $countStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM pembayaran pb
         JOIN pemesanan pm ON pm.id_pemesanan = pb.id_pemesanan
         $whereSql"
    );
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $stmt = $pdo->prepare(
        "SELECT pb.nomor_invoice, pb.metode_pembayaran,
                pb.jumlah_bayar, pb.status AS status_pembayaran,
                pb.waktu_pembayaran, pm.kode_booking,
                pm.nama_pemesan, pm.email_pemesan,
                pm.status AS status_pemesanan
         FROM pembayaran pb
         JOIN pemesanan pm ON pm.id_pemesanan = pb.id_pemesanan
         $whereSql
         ORDER BY pb.id_pembayaran DESC
         LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);
    $payments = $stmt->fetchAll();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    http_response_code(500);
    exit('Data pembayaran gagal dimuat. Periksa struktur database.');
}

function paymentStatusClass(string $status): string
{
    return match ($status) {
        'Berhasil' => 'payment-status-success',
        'Pending' => 'payment-status-pending',
        'Gagal' => 'payment-status-failed',
        default => 'payment-status-neutral',
    };
}

function paymentRupiah($amount): string
{
    return 'Rp' . number_format((float) $amount, 0, ',', '.');
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
    <?php $activeAdminPage = 'pembayaran'; require __DIR__ . '/../includes/admin-sidebar.php'; ?>

    <section class="admin-main">
        <header class="admin-topbar">
            <div>
                <p class="eyebrow">MANAJEMEN TRANSAKSI</p>
                <h1>Data Pembayaran</h1>
                <p>Pantau invoice dan status transaksi pemesanan tiket.</p>
            </div>
            <div class="admin-user">
                <span class="admin-avatar">A</span>
                <span><?= h($_SESSION['admin_name'] ?? 'Administrator') ?></span>
            </div>
        </header>

        <section class="admin-panel">
            <div class="panel-heading">
                <div>
                    <h2>Riwayat pembayaran</h2>
                    <p><?= number_format($total, 0, ',', '.') ?> transaksi ditemukan</p>
                </div>
            </div>

            <form method="GET" class="admin-filter">
                <input
                    type="search"
                    name="q"
                    value="<?= h($keyword) ?>"
                    placeholder="Cari invoice, booking, nama, atau email..."
                    aria-label="Cari pembayaran"
                >
                <select name="status" aria-label="Filter status">
                    <option value="">Semua status</option>
                    <?php foreach ($allowedStatuses as $status): ?>
                        <option value="<?= h($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>>
                            <?= h($status) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">Terapkan</button>
                <a class="btn btn-secondary" href="/airline-reservation/admin/pembayaran.php">Reset</a>
            </form>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Invoice / Booking</th>
                            <th>Pemesan</th>
                            <th>Metode</th>
                            <th>Jumlah</th>
                            <th>Status Pembayaran</th>
                            <th>Status Pemesanan</th>
                            <th>Waktu Pembayaran</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$payments): ?>
                            <tr><td colspan="7" class="admin-empty">Belum ada transaksi yang sesuai.</td></tr>
                        <?php else: ?>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td>
                                        <strong><?= h($payment['nomor_invoice']) ?></strong>
                                        <small>Booking: <?= h($payment['kode_booking']) ?></small>
                                    </td>
                                    <td>
                                        <strong><?= h($payment['nama_pemesan']) ?></strong>
                                        <small><?= h($payment['email_pemesan']) ?></small>
                                    </td>
                                    <td><?= h($payment['metode_pembayaran']) ?></td>
                                    <td><strong><?= h(paymentRupiah($payment['jumlah_bayar'])) ?></strong></td>
                                    <td>
                                        <span class="status-badge <?= h(paymentStatusClass($payment['status_pembayaran'])) ?>">
                                            <?= h($payment['status_pembayaran']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= h(paymentStatusClass($payment['status_pemesanan'] === 'Lunas' ? 'Berhasil' : ($payment['status_pemesanan'] === 'Pending' ? 'Pending' : 'Gagal'))) ?>">
                                            <?= h($payment['status_pemesanan']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= $payment['waktu_pembayaran']
                                            ? h(date('d-m-Y H:i', strtotime($payment['waktu_pembayaran'])))
                                            : 'Belum dibayar' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="admin-pagination">
                    <?php $queryBase = ['q' => $keyword, 'status' => $statusFilter]; ?>
                    <?php if ($page > 1): ?>
                        <a href="?<?= h(http_build_query($queryBase + ['page' => $page - 1])) ?>">Sebelumnya</a>
                    <?php endif; ?>
                    <span>Halaman <?= $page ?> dari <?= $totalPages ?></span>
                    <?php if ($page < $totalPages): ?>
                        <a href="?<?= h(http_build_query($queryBase + ['page' => $page + 1])) ?>">Berikutnya</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    </section>
</div>

</body>
</html>
