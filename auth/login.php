
<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: /airline-reservation/admin/dashboard.php');
    exit;
}

$error = '';
$setupSuccess = isset($_GET['setup']) && $_GET['setup'] === 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (
        !is_string($username) ||
        !is_string($password) ||
        $username === '' ||
        $password === ''
    ) {
        $error = 'Username dan password wajib diisi.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT id_admin, username, password, nama_admin
             FROM admin
             WHERE username = :username
             LIMIT 1'
        );

        $stmt->execute(['username' => $username]);
        $admin = $stmt->fetch();

        if (
            $admin &&
            password_verify($password, $admin['password'])
        ) {
            session_regenerate_id(true);

            $_SESSION['admin_id'] = (int) $admin['id_admin'];
            $_SESSION['admin_name'] = $admin['nama_admin'];

            if (
                password_needs_rehash(
                    $admin['password'],
                    PASSWORD_DEFAULT
                )
            ) {
                $hash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $update = $pdo->prepare(
                    'UPDATE admin SET password = :password
                     WHERE id_admin = :id'
                );

                $update->execute([
                    'password' => $hash,
                    'id' => $admin['id_admin'],
                ]);
            }

            unset($_SESSION['csrf_token']);

            header('Location: /airline-reservation/admin/dashboard.php');
            exit;
        }

        $error = 'Username atau password tidak sesuai.';
    }
}

$pageTitle = 'Login Admin | AeroBook';

require __DIR__ . '/../includes/header.php';
?>

<section class="setup-wrapper">
    <div class="setup-card">
        <a href="/airline-reservation/index.php" class="brand">
            <span class="brand-mark">A</span>
            <span>AeroBook<span class="brand-dot">.</span></span>
        </a>

        <h1>Selamat datang kembali</h1>
        <p>Masuk untuk mengelola operasional penerbangan.</p>

        <?php if ($setupSuccess): ?>
            <div class="notice success">Akun admin berhasil dibuat. Silakan login.</div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="notice error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="form-stack">
            <input type="hidden" name="csrf_token"
                value="<?= h(csrfToken()) ?>">

            <div class="field">
                <label for="username">Username</label>
                <input
                    id="username"
                    name="username"
                    autocomplete="username"
                    required
                >
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <button type="submit" class="btn btn-primary">
                Masuk ke Dashboard →
            </button>
        </form>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
