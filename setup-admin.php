<?php
// File ini seharusnya tidak digunakan lagi, hanya untuk membuat 1 akun admin pertama.
// Setelah itu, file ini dihapus dari server agar tidak disalahgunakan.

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/security.php';

$pageTitle = 'Buat Admin Pertama — AeroBook';
$error = '';
$adminExists = (int) $pdo->query('SELECT COUNT(*) FROM admin')->fetchColumn() > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$adminExists) {
    verifyCsrf();

    $name = trim((string) ($_POST['nama_admin'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $error = 'Nama admin harus terdiri dari 2–100 karakter.';
    } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
        $error = 'Username harus 3–50 karakter dan hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung.';
    } elseif (strlen($password) < 8 || strlen($password) > 200) {
        $error = 'Password harus terdiri dari minimal 8 karakter.';
    } elseif ($password !== $confirm) {
        $error = 'Konfirmasi password tidak sama.';
    } else {
        try {
            // Recheck immediately before insert, so setup cannot be reused later.
            if ((int) $pdo->query('SELECT COUNT(*) FROM admin')->fetchColumn() > 0) {
                $adminExists = true;
                $error = 'Akun admin sudah tersedia. Silakan login.';
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO admin (username, password, nama_admin)
                     VALUES (?, ?, ?)'
                );
                $stmt->execute([
                    $username,
                    password_hash($password, PASSWORD_DEFAULT),
                    $name
                ]);

                header('Location: /airline-reservation/auth/login.php?setup=success');
                exit;
            }
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $error = 'Username sudah digunakan.';
            } else {
                error_log($exception->getMessage());
                $error = 'Admin gagal dibuat. Silakan coba kembali.';
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="setup-wrapper">
    <div class="setup-card">
        <a href="/airline-reservation/index.php" class="brand">
            <span class="brand-mark">A</span>
            <span>AeroBook<span class="brand-dot">.</span></span>
        </a>

        <?php if ($adminExists): ?>
            <h1>Setup admin selesai</h1>
            <p>Akun admin sudah tersedia. Halaman pembuatan akun pertama telah dikunci.</p>
            <a class="btn btn-primary" href="/airline-reservation/auth/login.php">Masuk ke admin</a>
        <?php else: ?>
            <h1>Buat akun admin</h1>
            <p>Buat akun administrator pertama untuk mengelola penerbangan AeroBook.</p>

            <?php if ($error !== ''): ?>
                <div class="notice error"><?= h($error) ?></div>
            <?php endif; ?>

            <form method="POST" class="form-stack">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">

                <div class="field">
                    <label for="nama_admin">Nama admin</label>
                    <input id="nama_admin" name="nama_admin" maxlength="100" autocomplete="name" required>
                </div>

                <div class="field">
                    <label for="username">Username</label>
                    <input id="username" name="username" minlength="3" maxlength="50" autocomplete="username" required>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" minlength="8" maxlength="200" autocomplete="new-password" required>
                    <small>Gunakan minimal 8 karakter.</small>
                </div>

                <div class="field">
                    <label for="confirm_password">Konfirmasi password</label>
                    <input type="password" id="confirm_password" name="confirm_password" minlength="8" maxlength="200" autocomplete="new-password" required>
                </div>

                <button type="submit" class="btn btn-primary">Buat akun admin</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
