<?php
declare(strict_types=1);

// Halaman detail sederhana mengarahkan pengguna ke pemilihan kursi.
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header('Location: /airline-reservation/index.php');
    exit;
}

header('Location: /airline-reservation/booking/seats.php?id=' . $id);
exit;
