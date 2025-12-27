<?php
error_reporting(0); // penting: jangan tampilkan error ke output
include 'koneksi.php';

// PASTIKAN PATH BENAR
require_once __DIR__ . '/phpqrcode/qrlib.php';

$token = $_GET['token'] ?? '';
$download = isset($_GET['download']);

if ($token == '') {
    exit;
}

// Cek token
$q = $conn->prepare("SELECT nama FROM tte_user WHERE token=? AND status='aktif'");
$q->bind_param("s", $token);
$q->execute();
$d = $q->get_result()->fetch_assoc();

if (!$d) {
    exit;
}

// URL verifikasi
$url = "http://localhost/fixpoint/dist/verify_tte.php?token=" . $token;

// HEADER GAMBAR
header("Content-Type: image/png");

if ($download) {
    header("Content-Disposition: attachment; filename=TTE_".$d['nama'].".png");
}

// GENERATE QR LANGSUNG KE OUTPUT
QRcode::png($url, false, QR_ECLEVEL_H, 6, 2);
exit;
