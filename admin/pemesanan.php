<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

requireAdmin();

$pageTitle = 'Data Pemesanan — AeroBook';


function e(string|int|float|null $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah(int|float|string $amount): string
{
    return 'Rp' . number_format((float) $amount, 0, ',', '.');
}

$keyword = trim((string) ($_GET['q'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));

$allowedStatuses = ['Pending', 'Lunas', 'Dibatalkan', 'Kedaluwarsa'];
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if ($keyword !== '') {
    $where[] = "(
        pm.kode_booking LIKE :q1
        OR pm.nama_pemesan LIKE :q2
        OR pm.email_pemesan LIKE :q3
        OR EXISTS (
            SELECT 1
            FROM detail_pemesanan d2
            JOIN penerbangan f2
                ON f2.id_penerbangan = d2.id_penerbangan
            WHERE d2.id_pemesanan = pm.id_pemesanan
              AND f2.nomor_penerbangan LIKE :q4
        )
    )";

    $search = '%' . $keyword . '%';
    $params['q1'] = $search;
    $params['q2'] = $search;
    $params['q3'] = $search;
    $params['q4'] = $search;
}

if ($statusFilter !== '') {
    $where[] = 'pm.status = :status';
    $params['status'] = $statusFilter;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    $countStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM pemesanan pm
        $whereSql
    ");
    $countStmt->execute($params);
    $totalBookings = (int) $countStmt->fetchColumn();

    $totalPages = max(1, (int) ceil($totalBookings / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $stmt = $pdo->prepare("
        SELECT
            pm.id_pemesanan,
            pm.kode_booking,
            pm.nama_pemesan,
            pm.email_pemesan,
            pm.total_harga,
            pm.status AS status_booking,
            pm.batas_pembayaran,
            pm.waktu_pemesanan,
            GROUP_CONCAT(
                DISTINCT CONCAT(
                    f.nomor_penerbangan,
                    ' (',
                    f.bandara_asal,
                    ' - ',
                    f.bandara_tujuan,
                    ')'
                )
                ORDER BY f.waktu_keberangkatan
                SEPARATOR ', '
            ) AS daftar_penerbangan,
            GROUP_CONCAT(
                DISTINCT kp.nomor_kursi
                ORDER BY kp.nomor_kursi
                SEPARATOR ', '
            ) AS daftar_kursi,
            COUNT(DISTINCT d.id_detail) AS jumlah_tiket
        FROM pemesanan pm
        LEFT JOIN detail_pemesanan d
            ON d.id_pemesanan = pm.id_pemesanan
        LEFT JOIN penerbangan f
            ON f.id_penerbangan = d.id_penerbangan
        LEFT JOIN kursi_penerbangan kpn
            ON kpn.id_kursi_penerbangan = d.id_kursi_penerbangan
        LEFT JOIN kursi_pesawat kp
            ON kp.id_kursi = kpn.id_kursi
        $whereSql
        GROUP BY
            pm.id_pemesanan,
            pm.kode_booking,
            pm.nama_pemesan,
            pm.email_pemesan,
            pm.total_harga,
            pm.status,
            pm.batas_pembayaran,
            pm.waktu_pemesanan
        ORDER BY pm.waktu_pemesanan DESC
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    $bookings = $stmt->fetchAll();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    http_response_code(500);
    exit('Data pemesanan gagal dimuat. Silakan periksa database.');
}

function bookingStatusClass(string $status): string
{
    return match ($status) {
        'Lunas' => 'status-success',
        'Pending' => 'status-pending',
        'Dibatalkan', 'Kedaluwarsa' => 'status-danger',
        default => 'status-neutral',
    };
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
    <?php $activeAdminPage = 'pemesanan'; require __DIR__ . '/../includes/admin-sidebar.php'; ?>

    <section class="admin-main">
        <header class="admin-topbar">
            <div>
                <p class="eyebrow">MANAJEMEN TRANSAKSI</p>
                <h1>Data Pemesanan</h1>
                <p>Kelola dan pantau seluruh pemesanan tiket AeroBook.</p>
            </div>
        </header>

        <div class="admin-panel">
            <div class="panel-heading">
                <div>
                    <h2>Daftar Pemesanan</h2>
                    <p><?= number_format($totalBookings, 0, ',', '.') ?> pemesanan ditemukan</p>
                </div>
            </div>

            <form method="GET" class="admin-filter">
                <input
                    type="search"
                    name="q"
                    value="<?= e($keyword) ?>"
                    placeholder="Cari kode booking, nama, email, atau penerbangan..."
                    aria-label="Cari pemesanan"
                >

                <select name="status" aria-label="Filter status">
                    <option value="">Semua status</option>
                    <?php foreach ($allowedStatuses as $status): ?>
                        <option
                            value="<?= e($status) ?>"
                            <?= $statusFilter === $status ? 'selected' : '' ?>
                        >
                            <?= e($status) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn btn-primary">Terapkan</button>
                <a class="btn btn-secondary"
                   href="/airline-reservation/admin/pemesanan.php">
                    Reset
                </a>
            </form>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Kode Booking</th>
                            <th>Penumpang</th>
                            <th>Penerbangan / Kursi</th>
                            <th>Total</th>
                            <th>Batas Pembayaran</th>
                            <th>Status</th>
                            <th>Waktu Pemesanan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$bookings): ?>
                            <tr>
                                <td colspan="7" class="admin-empty">
                                    Belum ada data pemesanan yang sesuai.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($booking['kode_booking']) ?></strong>
                                        <small>
                                            <?= (int) $booking['jumlah_tiket'] ?>
                                            tiket
                                        </small>
                                    </td>
                                    <td>
                                        <strong><?= e($booking['nama_pemesan']) ?></strong>
                                        <small><?= e($booking['email_pemesan']) ?></small>
                                    </td>
                                    <td>
                                        <span><?= e($booking['daftar_penerbangan'] ?: 'Data penerbangan tidak tersedia') ?></span>
                                        <small>
                                            Kursi:
                                            <?= e($booking['daftar_kursi'] ?: '—') ?>
                                        </small>
                                    </td>
                                    <td>
                                        <strong><?= e(rupiah($booking['total_harga'])) ?></strong>
                                    </td>
                                    <td>
                                        <?= e(date('d-m-Y H:i', strtotime($booking['batas_pembayaran']))) ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= e(bookingStatusClass($booking['status_booking'])) ?>">
                                            <?= e($booking['status_booking']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= e(date('d-m-Y H:i', strtotime($booking['waktu_pemesanan']))) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="admin-pagination">
                    <?php
                    $queryBase = [
                        'q' => $keyword,
                        'status' => $statusFilter,
                    ];
                    ?>

                    <?php if ($page > 1): ?>
                        <a href="?<?= e(http_build_query($queryBase + ['page' => $page - 1])) ?>">
                            Sebelumnya
                        </a>
                    <?php endif; ?>

                    <span>Halaman <?= $page ?> dari <?= $totalPages ?></span>

                    <?php if ($page < $totalPages): ?>
                        <a href="?<?= e(http_build_query($queryBase + ['page' => $page + 1])) ?>">
                            Berikutnya
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

</body>
</html>
