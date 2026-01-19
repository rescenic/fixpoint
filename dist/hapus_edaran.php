<?php
include 'security.php'; 
include 'koneksi.php';

$user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;

// Cek akses user
$current_file = 'surat_edaran.php'; // File induk
$rAkses = mysqli_query($conn, "SELECT 1 FROM akses_menu 
           JOIN menu ON akses_menu.menu_id = menu.id 
           WHERE akses_menu.user_id = '$user_id' AND menu.file_menu = '$current_file'");
if (!$rAkses || mysqli_num_rows($rAkses) == 0) {
    echo "<script>alert('Anda tidak memiliki akses ke halaman ini.'); window.location.href='dashboard.php';</script>";
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if($id > 0) {
    // Ambil data file untuk dihapus
    $qFile = mysqli_query($conn, "SELECT file_path FROM surat_edaran WHERE id = $id LIMIT 1");
    if($qFile && mysqli_num_rows($qFile) > 0) {
        $data = mysqli_fetch_assoc($qFile);
        
        // Hapus file PDF jika ada
        if(!empty($data['file_path']) && file_exists($data['file_path'])) {
            unlink($data['file_path']);
        }
        
        // Hapus dari database
        if(mysqli_query($conn, "DELETE FROM surat_edaran WHERE id = $id")) {
            $_SESSION['flash_message'] = 'Surat Edaran berhasil dihapus.';
        } else {
            $_SESSION['flash_message'] = 'Error: ' . mysqli_error($conn);
        }
    } else {
        $_SESSION['flash_message'] = 'Data tidak ditemukan.';
    }
} else {
    $_SESSION['flash_message'] = 'ID tidak valid.';
}

header("Location: surat_edaran.php?tab=data");
exit;
?>