<?php
include 'koneksi.php';
include 'send_wa.php';

function cekKoneksi($url) {
    if (!preg_match('~^https?://~i', $url)) $url = "http://$url";
    $parsed = parse_url($url);
    $host = $parsed['host'];
    $port = isset($parsed['port']) ? $parsed['port'] : (isset($parsed['scheme']) && $parsed['scheme']==='https'?443:80);
    
    // Method 1: fsockopen
    $start = microtime(true);
    $fp = @fsockopen($host,$port,$errno,$errstr,5); // Timeout 5 detik
    $end = microtime(true);
    
    if ($fp) { 
        fclose($fp); 
        $responseTime = round(($end - $start) * 1000, 2);
        return ['status' => true, 'time' => $responseTime];
    }
    
    // Method 2: Ping
    $is_windows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    
    if ($is_windows) {
        $output = [];
        $result_code = 0;
        @exec("ping -n 1 -w 2000 $host 2>&1", $output, $result_code);
        
        if ($result_code === 0) {
            $time_line = implode(' ', $output);
            if (preg_match('/time[=<]([0-9]+)ms/i', $time_line, $matches)) {
                $responseTime = floatval($matches[1]);
            } else {
                $responseTime = 1.0;
            }
            return ['status' => true, 'time' => $responseTime];
        }
    } else {
        $output = [];
        $result_code = 0;
        @exec("ping -c 1 -W 2 $host 2>&1", $output, $result_code);
        
        if ($result_code === 0) {
            $time_line = implode(' ', $output);
            if (preg_match('/time=([0-9.]+)\s*ms/i', $time_line, $matches)) {
                $responseTime = floatval($matches[1]);
            } else {
                $responseTime = 1.0;
            }
            return ['status' => true, 'time' => $responseTime];
        }
    }
    
    // Method 3: Curl
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $start = microtime(true);
    $result = curl_exec($ch);
    $end = microtime(true);
    
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($result !== false && $http_code > 0) {
        $responseTime = round(($end - $start) * 1000, 2);
        return ['status' => true, 'time' => $responseTime];
    }
    
    return ['status' => false, 'time' => 0];
}

// Fungsi untuk menghitung durasi
function hitungDurasi($waktu_mulai, $waktu_selesai) {
    $start = strtotime($waktu_mulai);
    $end = strtotime($waktu_selesai);
    $diff = $end - $start;
    
    $hours = floor($diff / 3600);
    $minutes = floor(($diff % 3600) / 60);
    $seconds = $diff % 60;
    
    if ($hours > 0) {
        return sprintf("%d jam %d menit", $hours, $minutes);
    } elseif ($minutes > 0) {
        return sprintf("%d menit %d detik", $minutes, $seconds);
    } else {
        return sprintf("%d detik", $seconds);
    }
}

// Fungsi untuk log perubahan status
function logStatusChange($conn, $url_id, $nama_koneksi, $base_url, $status_from, $status_to) {
    $waktu_sekarang = date('Y-m-d H:i:s');
    
    if ($status_to == 'offline') {
        // Koneksi menjadi offline - buat log baru
        $query = "INSERT INTO log_koneksi (url_id, nama_koneksi, base_url, status_from, status_to, waktu_mulai) 
                  VALUES ('$url_id', '".mysqli_real_escape_string($conn, $nama_koneksi)."', '".mysqli_real_escape_string($conn, $base_url)."', '$status_from', '$status_to', '$waktu_sekarang')";
        mysqli_query($conn, $query);
    } elseif ($status_to == 'online') {
        // Koneksi kembali online - update log terakhir yang offline
        $query = "SELECT id, waktu_mulai FROM log_koneksi 
                  WHERE url_id = '$url_id' AND status_to = 'offline' AND waktu_selesai IS NULL 
                  ORDER BY id DESC LIMIT 1";
        $result = mysqli_query($conn, $query);
        
        if ($row = mysqli_fetch_assoc($result)) {
            $durasi_detik = strtotime($waktu_sekarang) - strtotime($row['waktu_mulai']);
            $durasi_display = hitungDurasi($row['waktu_mulai'], $waktu_sekarang);
            
            $update = "UPDATE log_koneksi 
                       SET waktu_selesai = '$waktu_sekarang', 
                           durasi_detik = $durasi_detik, 
                           durasi_display = '".mysqli_real_escape_string($conn, $durasi_display)."',
                           status_from = 'offline',
                           status_to = 'online'
                       WHERE id = {$row['id']}";
            mysqli_query($conn, $update);
        }
    }
}

$id_grup_row = mysqli_fetch_assoc(mysqli_query($conn,"SELECT nilai FROM wa_setting WHERE nama='wa_group_it' LIMIT 1"));
$id_grup = $id_grup_row['nilai'] ?? '';

$urls = mysqli_query($conn,"SELECT * FROM master_url ORDER BY nama_koneksi ASC");
$response = [];

while($row=mysqli_fetch_assoc($urls)){
    $checkResult = cekKoneksi($row['base_url']);
    $statusNow = $checkResult['status'] ? 'online' : 'offline';
    $statusLast = $row['status_last'] ?? '';
    
    // Jika status berubah
    if($statusNow != $statusLast && !empty($statusLast)){
        // Log ke database
        logStatusChange($conn, $row['id'], $row['nama_koneksi'], $row['base_url'], $statusLast, $statusNow);
        
        // Kirim WA
        if (!empty($id_grup)) {
            $pesan_wa = "🔔 KONEKSI {$row['nama_koneksi']}\nStatus berubah: *$statusLast* → *$statusNow*\nURL: {$row['base_url']}\nWaktu: ".date('Y-m-d H:i:s');
            sendWA($id_grup,$pesan_wa);
        }
        
        // Update status terakhir
        mysqli_query($conn,"UPDATE master_url SET status_last='$statusNow' WHERE id={$row['id']}");
    }
    
    $response[] = [
        'id' => $row['id'],
        'nama_koneksi' => $row['nama_koneksi'],
        'status' => $statusNow,
        'time' => $checkResult['time']
    ];
}

header('Content-Type: application/json');
echo json_encode($response);
?>