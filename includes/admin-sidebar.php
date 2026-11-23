<?php
/** Shared admin navigation. Set $activeAdminPage before including this file. */
$adminMenu = [
    'dashboard' => ['Dashboard', 'dashboard.php', '▦'],
    'pesawat' => ['Data Pesawat', 'pesawat.php', '✈'],
    'penerbangan' => ['Penerbangan', 'penerbangan.php', '↗'],
    'pemesanan' => ['Pemesanan', 'pemesanan.php', '▤'],
    'pembayaran' => ['Pembayaran', 'pembayaran.php', '◈'],
];
?>
<aside class="admin-sidebar">
    <a href="/airline-reservation/admin/dashboard.php" class="brand">
        <span class="brand-mark">A</span>
        <span>AeroBook<span class="brand-dot">.</span></span>
    </a>

    <div class="sidebar-section">
        <p class="sidebar-label">Menu utama</p>
        <nav class="sidebar-nav" aria-label="Navigasi admin">
            <?php foreach ($adminMenu as $key => [$label, $url, $icon]): ?>
                <a href="/airline-reservation/admin/<?= h($url) ?>"
                   class="<?= $activeAdminPage === $key ? 'active' : '' ?>"
                   <?= $activeAdminPage === $key ? 'aria-current="page"' : '' ?>>
                    <span class="sidebar-icon" aria-hidden="true"><?= h($icon) ?></span>
                    <span><?= h($label) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>

    <div class="sidebar-bottom">
        <form action="/airline-reservation/auth/logout.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <button type="submit" class="admin-logout">↪ Keluar dari akun</button>
        </form>
    </div>
</aside>
