<?php
/**
 * File helper untuk cek akses TTE
 * Digunakan di halaman aktivasi saja
 */

if (!function_exists('isTTEActivated')) {
    function isTTEActivated($conn) {
        // Ambil perusahaan ID
        $result = $conn->query("SELECT id FROM perusahaan LIMIT 1");
        if (!$result) return false;
        
        $perusahaan = $result->fetch_assoc();
        if (!$perusahaan) return false;
        
        $pid = $perusahaan['id'];
        
        // Cek lisensi
        $stmt = $conn->prepare("SELECT * FROM tte_licenses 
                                WHERE perusahaan_id = ? 
                                AND status = 'active' 
                                AND (expires_at IS NULL OR expires_at > NOW())");
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $license = $stmt->get_result()->fetch_assoc();
        
        return $license ? true : false;
    }
}
?>