
<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

requireAdmin();

$pageTitle = 'Data Pesawat | AeroBook';

$error = '';
$editPesawat = null;

if (isset($_GET['edit'])) {
    $id = filter_var(
        $_GET['edit'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($id) {
        $stmt = $pdo->prepare(
            'SELECT * FROM pesawat WHERE id_pesawat = :id'
        );
        $stmt->execute(['id' => $id]);
        $editPesawat = $stmt->fetch();

        if (!$editPesawat) {
            $error = 'Data pesawat tidak ditemukan.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $action = $_POST['action'] ?? '';
    $id = filter_var(
        $_POST['id_pesawat'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    try {
        if ($action === 'save') {
            $kode = strtoupper(trim($_POST['kode_pesawat'] ?? ''));
            $maskapai = trim($_POST['maskapai'] ?? '');
            $kapasitas = filter_var(
                $_POST['kapasitas'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (
                !preg_match('/^[A-Z0-9-]{2,20}$/', $kode) ||
                $maskapai === '' ||
                strlen($maskapai) > 100 ||
                $kapasitas === false ||
                $kapasitas < 1 ||
                $kapasitas > 1000
            ) {
                throw new DomainException(
                    'Periksa kode pesawat, nama maskapai, dan kapasitas.'
                );
            }

            if ($id) {
                $stmt = $pdo->prepare(
                    'UPDATE pesawat
                     SET kode_pesawat = :kode,
                         maskapai = :maskapai,
                         kapasitas = :kapasitas
                     WHERE id_pesawat = :id'
                );

                $stmt->execute([
                    'kode' => $kode,
                    'maskapai' => $maskapai,
                    'kapasitas' => $kapasitas,
                    'id' => $id,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO pesawat
                     (kode_pesawat, maskapai, kapasitas)
                     VALUES (:kode, :maskapai, :kapasitas)'
                );

                $stmt->execute([
                    'kode' => $kode,
                    'maskapai' => $maskapai,
                    'kapasitas' => $kapasitas,
                ]);
            }

            header('Location: pesawat.php?success=save');
            exit;
        }

        if ($action === 'delete') {
            if (!$id) {
                throw new DomainException('ID pesawat tidak valid.');
            }

            $stmt = $pdo->prepare(
                'DELETE FROM pesawat WHERE id_pesawat = :id'
            );
            $stmt->execute(['id' => $id]);

            header('Location: pesawat.php?success=delete');
            exit;
        }

        throw new DomainException('Aksi tidak dikenal.');
    } catch (DomainException $e) {
        $error = $e->getMessage();
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            $error = 'Kode pesawat sudah digunakan atau pesawat masih terhubung dengan data penerbangan.';
        } else {
            error_log($e->getMessage());
            $error = 'Operasi database gagal. Periksa kembali data.';
        }
    }
}

$pesawatList = $pdo->query(
    'SELECT id_pesawat, kode_pesawat, maskapai, kapasitas
     FROM pesawat
     ORDER BY id_pesawat DESC'
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
    <?php $activeAdminPage = 'pesawat'; require __DIR__ . '/../includes/admin-sidebar.php'; ?>

    <section class="admin-main">
        <div class="admin-topbar">
            <div>
                <p class="eyebrow">MANAJEMEN DATA</p>
                <h1>Data Pesawat</h1>
                <p>Kelola armada yang digunakan untuk penerbangan.</p>
            </div>
            <div class="admin-user">
                <span class="admin-avatar">A</span>
                <span><?= h($_SESSION['admin_name'] ?? 'Administrator') ?></span>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="admin-notice success">
                Operasi data berhasil diproses.
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="admin-notice error"><?= h($error) ?></div>
        <?php endif; ?>

        <section class="admin-panel" style="margin-bottom:24px">
            <div class="panel-heading">
                <h2><?= $editPesawat ? 'Edit Pesawat' : 'Tambah Pesawat' ?></h2>

                <?php if ($editPesawat): ?>
                    <a href="pesawat.php" class="text-link">Batal mengedit</a>
                <?php endif; ?>
            </div>

            <form method="POST" class="admin-form-grid">
                <input type="hidden" name="csrf_token"
                    value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="action" value="save">

                <input type="hidden" name="id_pesawat"
                    value="<?= (int) ($editPesawat['id_pesawat'] ?? 0) ?>">

                <div class="field">
                    <label for="kode_pesawat">Kode pesawat</label>
                    <input
                        id="kode_pesawat"
                        name="kode_pesawat"
                        placeholder="PK-GIA"
                        maxlength="20"
                        pattern="[A-Za-z0-9-]{2,20}"
                        value="<?= h($editPesawat['kode_pesawat'] ?? '') ?>"
                        required
                    >
                </div>

                <div class="field">
                    <label for="maskapai">Nama maskapai</label>
                    <input
                        id="maskapai"
                        name="maskapai"
                        placeholder="Garuda Indonesia"
                        maxlength="100"
                        value="<?= h($editPesawat['maskapai'] ?? '') ?>"
                        required
                    >
                </div>

                <div class="field">
                    <label for="kapasitas">Kapasitas kursi</label>
                    <input
                        id="kapasitas"
                        type="number"
                        name="kapasitas"
                        min="1"
                        max="1000"
                        value="<?= h($editPesawat['kapasitas'] ?? 12) ?>"
                        required
                    >
                </div>

                <div class="field" style="justify-content:end">
                    <button class="btn btn-primary" type="submit">
                        <?= $editPesawat ? 'Simpan Perubahan' : 'Tambah Pesawat' ?>
                    </button>
                </div>
            </form>
        </section>

        <section class="admin-panel">
            <div class="panel-heading">
                <h2>Daftar Armada</h2>
                <span class="status"><?= count($pesawatList) ?> pesawat</span>
            </div>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Kode Pesawat</th>
                            <th>Maskapai</th>
                            <th>Kapasitas</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$pesawatList): ?>
                        <tr><td colspan="4">Belum ada data pesawat.</td></tr>
                    <?php else: ?>
                        <?php foreach ($pesawatList as $pesawat): ?>
                            <tr>
                                <td><?= h($pesawat['kode_pesawat']) ?></td>
                                <td><?= h($pesawat['maskapai']) ?></td>
                                <td><?= (int) $pesawat['kapasitas'] ?> kursi</td>
                                <td>
                                    <div class="admin-actions">
                                        <a class="btn btn-secondary btn-small"
                                            href="pesawat.php?edit=<?= (int) $pesawat['id_pesawat'] ?>">
                                            Edit
                                        </a>

                                        <form method="POST"
                                            onsubmit="return confirm('Hapus pesawat ini?')">
                                            <input type="hidden" name="csrf_token"
                                                value="<?= h(csrfToken()) ?>">
                                            <input type="hidden" name="action"
                                                value="delete">
                                            <input type="hidden" name="id_pesawat"
                                                value="<?= (int) $pesawat['id_pesawat'] ?>">

                                            <button type="submit"
                                                class="btn btn-danger btn-small">
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
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
