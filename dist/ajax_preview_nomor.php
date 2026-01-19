<?php
session_start();
include 'koneksi.php';
require_once 'NomorDokumenGenerator.php';

header('Content-Type: application/json');

// Cek login
$user_id = $_SESSION['user_id'] ?? 0;
if ($user_id == 0) {
    echo json_encode([
        'success' => false,
        'error' => 'User tidak terautentikasi'
    ]);
    exit;
}

if(isset($_POST['jenis_dokumen']) && isset($_POST['unit_kerja_id'])) {
    $jenis = mysqli_real_escape_string($conn, $_POST['jenis_dokumen']);
    $unit_id = intval($_POST['unit_kerja_id']);
    
    $generator = new NomorDokumenGenerator($conn);
    
    try {
        $preview = $generator->previewNomorBerikutnya($jenis, $unit_id);
        
        echo json_encode([
            'success' => true,
            'nomor_preview' => $preview,
            'info' => "Nomor ini akan di-generate saat menyimpan dokumen"
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Parameter tidak lengkap. Diperlukan: jenis_dokumen dan unit_kerja_id'
    ]);
}
?>