<?php
session_start();
require 'koneksi.php';

// ===== CHECK IF USER IS LOGGED IN =====
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$action = $_GET['action'] ?? '';

// ===== RESET SPECIFIC IP =====
if ($action === 'reset_ip') {
    $ip = $_GET['ip'] ?? '';
    if ($ip !== '') {
        $ip = mysqli_real_escape_string($conn, $ip);
        $sql = "DELETE FROM login_attempts WHERE ip_address = '$ip'";
        if ($conn->query($sql)) {
            $deleted = $conn->affected_rows;
            echo "<script>alert('Berhasil reset blokir untuk IP: $ip ($deleted record dihapus)'); window.location.href='log_login.php';</script>";
        } else {
            echo "<script>alert('Gagal reset IP.'); window.location.href='log_login.php';</script>";
        }
    } else {
        echo "<script>alert('IP tidak valid.'); window.location.href='log_login.php';</script>";
    }
    exit;
}

// ===== RESET ALL BLOCKS =====
if ($action === 'reset_all_blocks') {
    // Hapus semua failed attempts dalam 3 menit terakhir
    $sql = "DELETE FROM login_attempts 
            WHERE success = 0 
            AND attempt_time > DATE_SUB(NOW(), INTERVAL 3 MINUTE)";
    if ($conn->query($sql)) {
        $deleted = $conn->affected_rows;
        echo "<script>alert('Berhasil reset semua blokir ($deleted record dihapus)'); window.location.href='log_login.php';</script>";
    } else {
        echo "<script>alert('Gagal reset blokir.'); window.location.href='log_login.php';</script>";
    }
    exit;
}

// ===== CLEAR OLD LOGS =====
if ($action === 'clear_old') {
    $sql = "DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 30 DAY)";
    if ($conn->query($sql)) {
        $deleted = $conn->affected_rows;
        echo "<script>alert('Berhasil menghapus $deleted log lama.'); window.location.href='log_login.php';</script>";
    } else {
        echo "<script>alert('Gagal menghapus log.'); window.location.href='log_login.php';</script>";
    }
    exit;
}

// ===== EXPORT TO CSV =====
if ($action === 'export') {
    $filename = 'login_attempts_' . date('Y-m-d_His') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    
    $output = fopen('php://output', 'w');
    
    // Header CSV
    fputcsv($output, ['ID', 'IP Address', 'Email', 'Status', 'Waktu']);
    
    // Data
    $sql = "SELECT id, ip_address, email, success, attempt_time FROM login_attempts ORDER BY attempt_time DESC";
    $result = $conn->query($sql);
    
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['id'],
            $row['ip_address'],
            $row['email'],
            $row['success'] == 1 ? 'Berhasil' : 'Gagal',
            $row['attempt_time']
        ]);
    }
    
    fclose($output);
    exit;
}

// Default redirect
header('Location: log_login.php');
exit;
?>