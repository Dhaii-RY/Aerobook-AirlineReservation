<?php
$pageTitle = $pageTitle ?? 'AeroBook — Flight Booking';
$extraCss = $extraCss ?? [];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Portal pemesanan tiket pesawat dengan pencarian jadwal dan pilihan kursi yang mudah.">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/airline-reservation/assets/css/style.css">
    <?php foreach ($extraCss as $css): ?>
        <link
            rel="stylesheet"
            href="<?= htmlspecialchars($css, ENT_QUOTES, 'UTF-8') ?>"
        >
    <?php endforeach; ?>
</head>
<body>
<header class="site-header">
    <div class="container nav-wrapper">
        <a class="brand" href="/airline-reservation/index.php">
            <span class="brand-mark">A</span>
            <span>AeroBook<span class="brand-dot">.</span></span>
        </a>

        <nav class="main-nav">
            <a href="/airline-reservation/index.php">Beranda</a>
            <a href="/airline-reservation/index.php#flights">Penerbangan</a>
            <a href="/airline-reservation/booking/cek-booking.php">Cek Booking</a>
        </nav>

    </div>
</header>
<main>
