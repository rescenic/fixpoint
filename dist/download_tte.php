<?php
// download_tte.php
session_start();

if (!isset($_GET['file'])) {
    die('File tidak ditemukan');
}

$filename = basename($_GET['file']);
$basePath = __DIR__ . '/uploads/signed/';
$filepath = realpath($basePath . $filename);

// SECURITY CHECK
if (!$filepath || strpos($filepath, realpath($basePath)) !== 0) {
    die('Akses ditolak');
}

if (!file_exists($filepath)) {
    die('File tidak ada');
}

// FORCE DOWNLOAD PDF
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: private');
header('Pragma: public');

readfile($filepath);
exit;
