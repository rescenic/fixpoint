<?php
include 'security.php';
include 'koneksi.php';
include 'send_wa.php'; // Fungsi sendWA()
date_default_timezone_set('Asia/Jakarta');

$user_id = $_SESSION['user_id'];
$current_file = basename(__FILE__);

// Cek akses user
$query = "SELECT 1 FROM akses_menu 
          JOIN menu ON akses_menu.menu_id = menu.id 
          WHERE akses_menu.user_id = '$user_id' AND menu.file_menu = '$current_file'";
$result = mysqli_query($conn, $query);
if (mysqli_num_rows($result) == 0) {
    echo "<script>alert('Anda tidak memiliki akses ke halaman ini.'); window.location.href='dashboard.php';</script>";
    exit;
}

// Fungsi cek koneksi URL dengan response time
function cekKoneksi($url) {
    if (!preg_match('~^https?://~i', $url)) $url = "http://" . $url;
    $parsed = parse_url($url);
    $host = $parsed['host'];
    $port = isset($parsed['port']) ? $parsed['port'] : (isset($parsed['scheme']) && $parsed['scheme'] === 'https' ? 443 : 80);
    
    // Method 1: fsockopen (cepat tapi kadang gagal untuk IP lokal)
    $start = microtime(true);
    $fp = @fsockopen($host, $port, $errno, $errstr, 5); // Timeout ditambah jadi 5 detik
    $end = microtime(true);
    
    if ($fp) { 
        fclose($fp); 
        $responseTime = round(($end - $start) * 1000, 2);
        return ['status' => true, 'time' => $responseTime];
    }
    
    // Method 2: Jika fsockopen gagal, coba ping (khusus untuk Windows/Linux lokal)
    $start = microtime(true);
    
    // Detect OS
    $is_windows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    
    if ($is_windows) {
        // Windows: ping -n 1 -w 1000
        $output = [];
        $result_code = 0;
        @exec("ping -n 1 -w 2000 $host 2>&1", $output, $result_code);
        
        if ($result_code === 0) {
            $time_line = implode(' ', $output);
            // Parse: time=15ms atau time<1ms
            if (preg_match('/time[=<]([0-9]+)ms/i', $time_line, $matches)) {
                $responseTime = floatval($matches[1]);
            } else {
                $responseTime = 1.0;
            }
            return ['status' => true, 'time' => $responseTime];
        }
    } else {
        // Linux: ping -c 1 -W 2
        $output = [];
        $result_code = 0;
        @exec("ping -c 1 -W 2 $host 2>&1", $output, $result_code);
        
        if ($result_code === 0) {
            $time_line = implode(' ', $output);
            // Parse: time=15.2 ms
            if (preg_match('/time=([0-9.]+)\s*ms/i', $time_line, $matches)) {
                $responseTime = floatval($matches[1]);
            } else {
                $responseTime = 1.0;
            }
            return ['status' => true, 'time' => $responseTime];
        }
    }
    
    // Method 3: Jika ping juga gagal, coba curl
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request saja
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $start = microtime(true);
    $result = curl_exec($ch);
    $end = microtime(true);
    
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    // Anggap online jika ada response (walaupun error 404, 500, dll tetap dianggap online)
    if ($result !== false && $http_code > 0) {
        $responseTime = round(($end - $start) * 1000, 2);
        return ['status' => true, 'time' => $responseTime];
    }
    
    return ['status' => false, 'time' => 0];
}

// Fungsi sensor URL/IP
function sensorUrl($url) {
    // Hilangkan protokol
    $url = preg_replace('~^https?://~i', '', $url);
    
    // Pecah berdasarkan /
    $parts = explode('/', $url);
    
    // Ambil domain/IP
    $domain = $parts[0];
    
    // Cek apakah IP address
    if (preg_match('/^\d+\.\d+\.\d+\.\d+/', $domain)) {
        // Sensor IP: 172.***.***.**
        $ipParts = explode('.', $domain);
        if (count($ipParts) == 4) {
            return $ipParts[0] . '.***.***.***';
        }
    } else {
        // Sensor domain
        // Pecah domain berdasarkan titik
        $domainParts = explode('.', $domain);
        
        if (count($domainParts) >= 3) {
            // Contoh: api-satusehat.kemkes.go.id -> api-***.***.***/******
            $sensored = $domainParts[0] . '-***';
            for ($i = 1; $i < count($domainParts); $i++) {
                $sensored .= '.***';
            }
            return $sensored . '/******';
        } elseif (count($domainParts) == 2) {
            // Contoh: example.com -> exa***.***/***** 
            $firstPart = substr($domainParts[0], 0, 3);
            return $firstPart . '***.' . '***' . '/******';
        } else {
            return '***.***/******';
        }
    }
    
    return $domain;
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
                  VALUES ('$url_id', '$nama_koneksi', '$base_url', '$status_from', '$status_to', '$waktu_sekarang')";
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
                           durasi_display = '$durasi_display',
                           status_from = 'offline',
                           status_to = 'online'
                       WHERE id = {$row['id']}";
            mysqli_query($conn, $update);
        }
    }
}

// Ambil ID grup WA
$row_grup = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT nilai FROM wa_setting WHERE nama='wa_group_it' LIMIT 1")
);
$id_grup = $row_grup['nilai'] ?? '';

// Tab aktif
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'monitoring';

// Pencarian
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$query_url = "SELECT * FROM master_url";
if (!empty($keyword)) {
    $keywordEscaped = mysqli_real_escape_string($conn, $keyword);
    $query_url .= " WHERE nama_koneksi LIKE '%$keywordEscaped%' OR base_url LIKE '%$keywordEscaped%'";
}
$query_url .= " ORDER BY nama_koneksi ASC";
$result_url = mysqli_query($conn, $query_url);

// Hitung statistik
$total = 0;
$online_count = 0;
$offline_count = 0;
$connections_data = [];

while ($row = mysqli_fetch_assoc($result_url)) {
    $checkResult = cekKoneksi($row['base_url']);
    $statusOnline = $checkResult['status'];
    $responseTime = $checkResult['time'];
    $statusNow = $statusOnline ? 'online' : 'offline';
    $statusLast = $row['status_last'] ?? '';

    // Jika status berubah
    if ($statusLast !== $statusNow && !empty($statusLast)) {
        // Log ke database
        logStatusChange($conn, $row['id'], $row['nama_koneksi'], $row['base_url'], $statusLast, $statusNow);
        
        // Notifikasi WA
        if (!empty($id_grup)) {
            $pesan_wa = "🔔 KONEKSI {$row['nama_koneksi']}\nStatus berubah: *$statusLast* → *$statusNow*\nURL: {$row['base_url']}\nWaktu: ".date('Y-m-d H:i:s');
            $waResult = sendWA($id_grup, $pesan_wa);
            if (!$waResult) {
                error_log("Gagal kirim WA ke grup $id_grup untuk {$row['nama_koneksi']}");
            }
        }
        
        // Update status_last di DB
        mysqli_query($conn, "UPDATE master_url SET status_last='$statusNow' WHERE id={$row['id']}");
    }
    
    // Auto log untuk koneksi lambat (response time > 200ms = merah/slow)
    if ($statusOnline && $responseTime > 200) {
        // Cek apakah sudah ada log slow untuk koneksi ini dalam 5 menit terakhir
        $check_recent = mysqli_query($conn, 
            "SELECT id FROM log_koneksi 
             WHERE url_id = {$row['id']} 
             AND status_to = 'slow' 
             AND waktu_mulai > DATE_SUB(NOW(), INTERVAL 5 MINUTE) 
             LIMIT 1"
        );
        
        // Jika belum ada log slow dalam 5 menit terakhir, buat log baru
        if (mysqli_num_rows($check_recent) == 0) {
            $waktu_sekarang = date('Y-m-d H:i:s');
            $nama_escaped = mysqli_real_escape_string($conn, $row['nama_koneksi']);
            $url_escaped = mysqli_real_escape_string($conn, $row['base_url']);
            
            mysqli_query($conn, 
                "INSERT INTO log_koneksi (url_id, nama_koneksi, base_url, status_from, status_to, waktu_mulai, durasi_display) 
                 VALUES ({$row['id']}, '$nama_escaped', '$url_escaped', 'online', 'slow', '$waktu_sekarang', '{$responseTime}ms')"
            );
        }
    }

    $total++;
    if ($statusOnline === true) {
        $online_count++;
    } else {
        $offline_count++;
    }
    
    $connections_data[] = [
        'id' => $row['id'],
        'nama' => $row['nama_koneksi'],
        'url' => $row['base_url'],
        'status' => $statusOnline,
        'time' => $responseTime
    ];
}

$online_percent = $total > 0 ? round(($online_count / $total) * 100) : 0;

// Query untuk data monitoring (log)
$log_query = "SELECT * FROM log_koneksi ORDER BY waktu_mulai DESC LIMIT 100";
$log_result = mysqli_query($conn, $log_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="refresh" content="30">
<title>Monitoring Koneksi Bridging</title>
<link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css" />
<link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css" />
<link rel="stylesheet" href="assets/css/style.css" />
<link rel="stylesheet" href="assets/css/components.css" />
<style>
.nav-tabs {
    border-bottom: 2px solid #e9ecef;
    margin-bottom: 20px;
}

.nav-tabs .nav-link {
    border: none;
    color: #6c757d;
    font-weight: 500;
    padding: 12px 24px;
    border-bottom: 3px solid transparent;
}

.nav-tabs .nav-link:hover {
    color: #495057;
    border-bottom-color: #dee2e6;
}

.nav-tabs .nav-link.active {
    color: #667eea;
    border-bottom-color: #667eea;
    background: none;
}

.stats-row {
    display: flex;
    gap: 12px;
    margin-bottom: 20px;
}

.stat-box {
    flex: 1;
    background: white;
    border-radius: 8px;
    padding: 12px 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    display: flex;
    align-items: center;
    gap: 12px;
}

.stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    color: white;
}

.stat-icon.total {
    background: linear-gradient(135deg, #667eea, #764ba2);
}

.stat-icon.online {
    background: linear-gradient(135deg, #28a745, #20c997);
}

.stat-icon.offline {
    background: linear-gradient(135deg, #dc3545, #c82333);
}

.stat-icon.uptime {
    background: linear-gradient(135deg, #17a2b8, #138496);
}

.stat-content h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
    color: #2c3e50;
    line-height: 1;
}

.stat-content p {
    margin: 0;
    font-size: 11px;
    color: #6c757d;
    text-transform: uppercase;
}

.connection-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 12px;
}

.conn-card {
    background: white;
    border-radius: 8px;
    padding: 12px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    border-left: 3px solid #ddd;
}

.conn-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.12);
    transform: translateY(-2px);
}

.conn-card.online {
    border-left-color: #28a745;
}

.conn-card.offline {
    border-left-color: #dc3545;
}

.conn-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
}

.conn-icon {
    width: 38px;
    height: 38px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.conn-icon.online {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
}

.conn-icon.offline {
    background: linear-gradient(135deg, #dc3545, #c82333);
    color: white;
}

.conn-title {
    flex: 1;
    font-size: 14px;
    font-weight: 600;
    color: #2c3e50;
    line-height: 1.3;
}

.conn-url {
    font-size: 10px;
    color: #999;
    margin-bottom: 8px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.conn-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
}

.conn-status {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.conn-status.online {
    background: #d4edda;
    color: #155724;
}

.conn-status.offline {
    background: #f8d7da;
    color: #721c24;
}

.conn-time {
    font-size: 16px;
    font-weight: 700;
}

.conn-time.fast {
    color: #28a745;
}

.conn-time.medium {
    color: #ffc107;
}

.conn-time.slow {
    color: #dc3545;
}

.conn-time-label {
    font-size: 9px;
    color: #6c757d;
    margin-left: 2px;
}

.pulse {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.log-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}

.log-badge.down {
    background: #f8d7da;
    color: #721c24;
}

.log-badge.up {
    background: #d4edda;
    color: #155724;
}

.response-box {
    background: #1e1e1e;
    color: #d4d4d4;
    border-radius: 8px;
    padding: 15px;
    font-family: 'Courier New', monospace;
    font-size: 13px;
    max-height: 400px;
    overflow-y: auto;
    white-space: pre-wrap;
    word-wrap: break-word;
}

.header-item {
    background: white;
    padding: 8px 12px;
    border-radius: 4px;
    margin-bottom: 5px;
    font-size: 13px;
    border-left: 3px solid #667eea;
}

.loading-overlay {
    display: none;
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255,255,255,0.95);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
}

.loading-overlay.active {
    display: flex;
}

.history-item {
    padding: 10px;
    border-left: 3px solid #ddd;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all 0.2s;
    border-radius: 4px;
}

.history-item:hover {
    background: #f8f9fa;
    border-left-color: #667eea;
}

.history-item.success {
    border-left-color: #28a745;
}

.history-item.error {
    border-left-color: #dc3545;
}

.method-badge {
    padding: 2px 8px;
    border-radius: 3px;
    font-weight: 600;
    font-size: 11px;
}

.method-GET { background: #28a745; color: white; }
.method-POST { background: #007bff; color: white; }
.method-PUT { background: #ffc107; color: #000; }
.method-DELETE { background: #dc3545; color: white; }

@media (max-width: 768px) {
    .stats-row {
        flex-wrap: wrap;
    }
    
    .stat-box {
        flex: 1 1 calc(50% - 6px);
    }
    
    .connection-grid {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    }
}
</style>
</head>
<body>
<div id="app">
<div class="main-wrapper main-wrapper-1">
<?php include 'navbar.php'; ?>
<?php include 'sidebar.php'; ?>
<div class="main-content">
<section class="section">
<div class="section-body">
<div class="card">
<div class="card-header d-flex justify-content-between align-items-center">
<h4>Monitoring Koneksi Bridging <span id="refresh-indicator" class="ml-2" style="display:none;"><i class="fas fa-sync-alt fa-spin text-primary"></i></span></h4>
<form method="GET" class="form-inline">
<input type="hidden" name="tab" value="<?= $active_tab ?>">
<input type="text" name="keyword" value="<?= htmlspecialchars($keyword) ?>" class="form-control form-control-sm mr-2" placeholder="Cari URL / IP" style="width: 200px;" />
<button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Cari</button>
</form>
</div>
<div class="card-body">
    
    <!-- Tabs -->
    <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link <?= $active_tab == 'monitoring' ? 'active' : '' ?>" href="?tab=monitoring">
                <i class="fas fa-desktop"></i> Monitoring Koneksi
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab == 'data' ? 'active' : '' ?>" href="?tab=data">
                <i class="fas fa-database"></i> Data Monitoring
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab == 'tester' ? 'active' : '' ?>" href="?tab=tester">
                <i class="fas fa-vial"></i> API Tester
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab == 'diagnosa' ? 'active' : '' ?>" href="?tab=diagnosa">
                <i class="fas fa-stethoscope"></i> Diagnosa Koneksi
            </a>
        </li>
    </ul>
    
    <!-- Tab Content -->
    <div class="tab-content">
        
        <!-- Tab Monitoring -->
        <?php if ($active_tab == 'monitoring'): ?>
        <div class="tab-pane active">
            <!-- Statistics -->
            <div class="stats-row">
                <div class="stat-box">
                    <div class="stat-icon total">
                        <i class="fas fa-network-wired"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= $total ?></h3>
                        <p>Total Koneksi</p>
                    </div>
                </div>
                
                <div class="stat-box">
                    <div class="stat-icon online">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= $online_count ?></h3>
                        <p>Terhubung</p>
                    </div>
                </div>
                
                <div class="stat-box">
                    <div class="stat-icon offline">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= $offline_count ?></h3>
                        <p>Terputus</p>
                    </div>
                </div>
                
                <div class="stat-box">
                    <div class="stat-icon uptime">
                        <i class="fas fa-signal"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= $online_percent ?>%</h3>
                        <p>Uptime</p>
                    </div>
                </div>
            </div>

            <!-- Connection Cards -->
            <div class="connection-grid">
            <?php
            if (count($connections_data) > 0) {
                foreach ($connections_data as $conn) {
                    $statusClass = $conn['status'] ? 'online' : 'offline';
                    $statusText = $conn['status'] ? 'Online' : 'Offline';
                    $iconClass = $conn['status'] ? 'fa-check-circle' : 'fa-times-circle';
                    $pulseClass = $conn['status'] ? '' : 'pulse';
                    
                    $timeClass = '';
                    if ($conn['status']) {
                        if ($conn['time'] < 100) $timeClass = 'fast';
                        elseif ($conn['time'] < 200) $timeClass = 'medium';
                        else $timeClass = 'slow';
                    }
                    ?>
                    <div class="conn-card <?= $statusClass ?>" data-id="<?= $conn['id'] ?>">
                        <div class="conn-header">
                            <div class="conn-icon <?= $statusClass ?> <?= $pulseClass ?>">
                                <i class="fas <?= $iconClass ?>"></i>
                            </div>
                            <div class="conn-title"><?= htmlspecialchars($conn['nama']) ?></div>
                        </div>
                        
                        <div class="conn-url">
                            <i class="fas fa-link"></i> <?= htmlspecialchars(sensorUrl($conn['url'])) ?>
                        </div>
                        
                        <div class="conn-footer">
                            <span class="conn-status <?= $statusClass ?>">
                                <i class="fas <?= $iconClass ?>"></i> <?= $statusText ?>
                            </span>
                            
                            <?php if ($conn['status']): ?>
                                <span class="conn-time <?= $timeClass ?>">
                                    <?= $conn['time'] ?><span class="conn-time-label">ms</span>
                                </span>
                            <?php else: ?>
                                <span class="conn-time">-<span class="conn-time-label">ms</span></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php
                }
            } else {
                ?>
                <div class="text-center py-5" style="grid-column: 1 / -1;">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h5>Tidak ada data ditemukan</h5>
                    <p class="text-muted">Coba kata kunci pencarian lain</p>
                </div>
                <?php
            }
            ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Tab Data Monitoring -->
        <?php if ($active_tab == 'data'): ?>
        <div class="tab-pane active">
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-sm">
                    <thead class="thead-light">
                        <tr>
                            <th width="5%">No</th>
                            <th width="18%">Nama Koneksi</th>
                            <th width="12%">Status</th>
                            <th width="18%">Waktu Mulai</th>
                            <th width="18%">Waktu Selesai</th>
                            <th width="15%">Durasi</th>
                            <th width="14%">Grafik</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    if (mysqli_num_rows($log_result) > 0) {
                        $no = 1;
                        while ($log = mysqli_fetch_assoc($log_result)) {
                            $status_display = '';
                            if ($log['status_to'] == 'offline') {
                                $status_display = '<span class="log-badge down"><i class="fas fa-arrow-down"></i> Offline</span>';
                            } elseif ($log['status_to'] == 'slow') {
                                $status_display = '<span class="log-badge" style="background:#fff3cd;color:#856404;"><i class="fas fa-exclamation-triangle"></i> Lambat</span>';
                            } else {
                                $status_display = '<span class="log-badge up"><i class="fas fa-arrow-up"></i> Online Kembali</span>';
                            }
                            
                            $waktu_selesai = $log['waktu_selesai'] ? date('d/m/Y H:i:s', strtotime($log['waktu_selesai'])) : '<span class="badge badge-warning">Masih Offline</span>';
                            $durasi = $log['durasi_display'] ? $log['durasi_display'] : '-';
                            ?>
                            <tr>
                                <td class="text-center"><?= $no++ ?></td>
                                <td><strong><?= htmlspecialchars($log['nama_koneksi']) ?></strong></td>
                                <td><?= $status_display ?></td>
                                <td><?= date('d/m/Y H:i:s', strtotime($log['waktu_mulai'])) ?></td>
                                <td><?= $waktu_selesai ?></td>
                                <td><?= $durasi ?></td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-info" onclick="showChart(<?= $log['url_id'] ?>, '<?= addslashes($log['nama_koneksi']) ?>')">
                                        <i class="fas fa-chart-line"></i> Lihat Grafik
                                    </button>
                                </td>
                            </tr>
                            <?php
                        }
                    } else {
                        ?>
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-2"></i>
                                <p class="mb-0">Belum ada data log monitoring</p>
                            </td>
                        </tr>
                        <?php
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Tab API Tester -->
        <?php if ($active_tab == 'tester'): ?>
        <div class="tab-pane active">
            <div class="row">
                <div class="col-md-8">
                    <!-- API Request Form -->
                    <div class="card mb-3">
                        <div class="card-body" style="position: relative;">
                            <div class="loading-overlay" id="loadingOverlay">
                                <div class="text-center">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="sr-only">Loading...</span>
                                    </div>
                                    <p class="mt-2">Testing API...</p>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label><strong>Pilih Koneksi dari Database</strong></label>
                                <select class="form-control" id="urlSelect">
                                    <option value="">-- Pilih URL --</option>
                                    <?php 
                                    mysqli_data_seek($result_url, 0);
                                    while($url = mysqli_fetch_assoc($result_url)): 
                                    ?>
                                    <option value="<?= htmlspecialchars($url['base_url']) ?>">
                                        <?= htmlspecialchars($url['nama_koneksi']) ?> - <?= htmlspecialchars($url['base_url']) ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="form-row">
                                <div class="col-md-3">
                                    <label><strong>Method</strong></label>
                                    <select class="form-control" id="method">
                                        <option value="GET">GET</option>
                                        <option value="POST">POST</option>
                                        <option value="PUT">PUT</option>
                                        <option value="DELETE">DELETE</option>
                                    </select>
                                </div>
                                <div class="col-md-9">
                                    <label><strong>URL / Endpoint</strong></label>
                                    <input type="text" class="form-control" id="apiUrl" 
                                           placeholder="https://api.example.com/endpoint" />
                                </div>
                            </div>
                            
                            <div class="form-group mt-3">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="useBpjsAuth">
                                    <label class="custom-control-label" for="useBpjsAuth">
                                        <strong>Gunakan Authentikasi BPJS</strong>
                                    </label>
                                </div>
                            </div>
                            
                            <div id="bpjsAuthFields" style="display: none;">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Cons ID</label>
                                            <input type="text" class="form-control form-control-sm" id="consId" />
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Secret Key</label>
                                            <input type="text" class="form-control form-control-sm" id="secretKey" />
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>User Key</label>
                                            <input type="text" class="form-control form-control-sm" id="userKey" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label><strong>Headers (JSON)</strong></label>
                                <textarea class="form-control" id="apiHeaders" rows="2" 
                                          placeholder='{"Content-Type": "application/json"}'></textarea>
                            </div>
                            
                            <div class="form-group" id="bodyGroup" style="display:none;">
                                <label><strong>Request Body (JSON)</strong></label>
                                <textarea class="form-control" id="apiBody" rows="3" 
                                          placeholder='{"key": "value"}'></textarea>
                            </div>
                            
                            <button class="btn btn-primary btn-lg btn-block" onclick="testApi()">
                                <i class="fas fa-paper-plane"></i> Send Request
                            </button>
                        </div>
                    </div>
                    
                    <!-- Response Panel -->
                    <div id="responsePanel" style="display:none;">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-reply"></i> Response</h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center mb-3">
                                    <div class="col-md-4">
                                        <div class="border-right">
                                            <small class="text-muted">Status Code</small>
                                            <h3 class="mb-0" id="statusCode">-</h3>
                                            <small id="statusText" class="text-muted">-</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="border-right">
                                            <small class="text-muted">Response Time</small>
                                            <h3 class="mb-0" id="responseTime">-</h3>
                                            <small class="text-muted">milliseconds</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted">Response Size</small>
                                        <h3 class="mb-0" id="responseSize">-</h3>
                                        <small class="text-muted">bytes</small>
                                    </div>
                                </div>
                                
                                <ul class="nav nav-pills mb-3" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active" data-toggle="tab" href="#bodyTab">Body</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-toggle="tab" href="#headersTab">Headers</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-toggle="tab" href="#rawTab">Raw</a>
                                    </li>
                                    <li class="nav-item" id="decryptedTab" style="display:none;">
                                        <a class="nav-link" data-toggle="tab" href="#decryptedContent">
                                            <i class="fas fa-unlock"></i> Decrypted
                                        </a>
                                    </li>
                                </ul>
                                
                                <div class="tab-content">
                                    <div class="tab-pane active" id="bodyTab">
                                        <div class="response-box" id="responseBody"></div>
                                    </div>
                                    <div class="tab-pane" id="headersTab">
                                        <div id="responseHeaders"></div>
                                    </div>
                                    <div class="tab-pane" id="rawTab">
                                        <div class="response-box" id="responseRaw"></div>
                                    </div>
                                    <div class="tab-pane" id="decryptedContent">
                                        <div class="response-box" id="responseDecrypted"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-history"></i> History</h5>
                        </div>
                        <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                            <div id="historyList">
                                <p class="text-muted text-center"><i class="fas fa-info-circle"></i> Belum ada history</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Tab Diagnosa Koneksi -->
        <?php if ($active_tab == 'diagnosa'): ?>
        <div class="tab-pane active">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> <strong>Tujuan Diagnosa:</strong> Membuktikan secara teknis apakah masalah ada di koneksi RS atau di server BPJS
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-play-circle"></i> Mulai Diagnosa</h5>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label><strong>Pilih Target Server</strong></label>
                                <select class="form-control" id="diagnosaTarget">
                                    <option value="">-- Pilih Server --</option>
                                    <option value="new-api.bpjs-kesehatan.go.id">BPJS VClaim (new-api)</option>
                                    <option value="apijkn.bpjs-kesehatan.go.id">BPJS JKN Mobile (apijkn)</option>
                                    <option value="pcare.bpjs-kesehatan.go.id">BPJS PCare</option>
                                    <option value="dvlp.bpjs-kesehatan.go.id">BPJS Development</option>
                                    <option value="api-satusehat.kemkes.go.id">Kemenkes Satu Sehat</option>
                                    <option value="custom">Custom URL/IP</option>
                                </select>
                            </div>
                            
                            <div class="form-group" id="customUrlGroup" style="display:none;">
                                <label><strong>Custom URL/IP</strong></label>
                                <input type="text" class="form-control" id="customUrl" placeholder="contoh: 8.8.8.8 atau google.com">
                            </div>
                            
                            <div class="form-group">
                                <label><strong>Jumlah Test Ping</strong></label>
                                <select class="form-control" id="pingCount">
                                    <option value="4">4 kali (Quick)</option>
                                    <option value="10" selected>10 kali (Standard)</option>
                                    <option value="20">20 kali (Thorough)</option>
                                    <option value="50">50 kali (Extensive)</option>
                                </select>
                            </div>
                            
                            <button class="btn btn-lg btn-primary btn-block" onclick="startDiagnosa()">
                                <i class="fas fa-stethoscope"></i> Mulai Diagnosa
                            </button>
                            
                            <button class="btn btn-sm btn-success btn-block mt-2" onclick="exportDiagnosa()" id="exportBtn" style="display:none;">
                                <i class="fas fa-file-pdf"></i> Export PDF untuk Bukti ke BPJS
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Hasil Diagnosa</h5>
                        </div>
                        <div class="card-body">
                            <div id="diagnosaLoading" style="display:none; text-align:center; padding:50px;">
                                <div class="spinner-border text-primary mb-3" role="status"></div>
                                <p>Sedang melakukan diagnosa...</p>
                                <small class="text-muted">Mohon tunggu, ini membutuhkan waktu</small>
                            </div>
                            
                            <div id="diagnosaResult" style="display:none;">
                                <!-- Verdict -->
                                <div class="alert mb-3" id="verdictBox">
                                    <h5 class="mb-2"><i class="fas fa-gavel"></i> <strong>KESIMPULAN:</strong></h5>
                                    <p class="mb-0" id="verdictText"></p>
                                </div>
                                
                                <!-- Stats Summary -->
                                <div class="row text-center mb-3">
                                    <div class="col-4">
                                        <div class="card bg-light">
                                            <div class="card-body p-2">
                                                <h4 class="mb-0" id="statSuccess">-</h4>
                                                <small>Sukses</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="card bg-light">
                                            <div class="card-body p-2">
                                                <h4 class="mb-0" id="statFailed">-</h4>
                                                <small>Gagal</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="card bg-light">
                                            <div class="card-body p-2">
                                                <h4 class="mb-0" id="statAvgTime">-</h4>
                                                <small>Avg (ms)</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Detailed Results -->
                                <div class="card">
                                    <div class="card-header">
                                        <strong>Detail Pengujian</strong>
                                    </div>
                                    <div class="card-body p-2" style="max-height: 300px; overflow-y: auto;">
                                        <table class="table table-sm table-bordered mb-0">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th width="15%">#</th>
                                                    <th width="25%">Status</th>
                                                    <th width="30%">Time (ms)</th>
                                                    <th width="30%">TTL</th>
                                                </tr>
                                            </thead>
                                            <tbody id="detailTable"></tbody>
                                        </table>
                                    </div>
                                </div>
                                
                                <!-- Technical Info -->
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <strong>Info Teknis:</strong><br>
                                        Target: <span id="infoTarget">-</span><br>
                                        IP Address: <span id="infoIP">-</span><br>
                                        Timestamp: <span id="infoTime">-</span><br>
                                        Loss Rate: <span id="infoLoss">-</span>
                                    </small>
                                </div>
                            </div>
                            
                            <div id="diagnosaEmpty" class="text-center text-muted" style="padding:50px;">
                                <i class="fas fa-clipboard-list fa-3x mb-3"></i>
                                <p>Pilih target dan klik "Mulai Diagnosa"</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Info Box -->
            <div class="card mt-3">
                <div class="card-header bg-warning">
                    <h5 class="mb-0"><i class="fas fa-lightbulb"></i> Cara Membaca Hasil</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-check-circle text-success"></i> Koneksi RS BAGUS jika:</h6>
                            <ul class="small">
                                <li>✅ Success rate > 95% (hampir semua ping berhasil)</li>
                                <li>✅ Rata-rata response time < 100ms</li>
                                <li>✅ Tidak ada packet loss atau minimal (< 5%)</li>
                                <li>✅ Response time konsisten (tidak naik-turun drastis)</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-exclamation-triangle text-danger"></i> Server BPJS BERMASALAH jika:</h6>
                            <ul class="small">
                                <li>❌ Success rate < 80% (banyak ping gagal/timeout)</li>
                                <li>❌ Rata-rata response time > 200ms</li>
                                <li>❌ Packet loss tinggi (> 10%)</li>
                                <li>❌ Response time tidak stabil (beda 100ms+ tiap ping)</li>
                            </ul>
                        </div>
                    </div>
                    <hr>
                    <p class="mb-0 small"><strong>💡 Tips:</strong> Export hasil dalam PDF dan kirim ke BPJS sebagai bukti jika mereka claim "koneksi RS bermasalah". Data ini menunjukkan secara teknis dari mana masalahnya.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
    </div>
    
</div>
</div>
</div>
</section>
</div>
</div>
</div>

<!-- Modal Grafik -->
<div class="modal fade" id="chartModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-chart-line"></i> Grafik Monitoring - <span id="modalChartTitle"></span>
                </h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label>Periode:</label>
                        <select class="form-control form-control-sm" id="chartPeriod">
                            <option value="7">7 Hari Terakhir</option>
                            <option value="30" selected>30 Hari Terakhir</option>
                            <option value="90">90 Hari Terakhir</option>
                        </select>
                    </div>
                    <div class="col-md-8 text-right">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-outline-primary active" data-chart-type="downtime">
                                <i class="fas fa-clock"></i> Downtime
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-success" data-chart-type="uptime">
                                <i class="fas fa-check-circle"></i> Uptime
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-warning" data-chart-type="response">
                                <i class="fas fa-tachometer-alt"></i> Response Time
                            </button>
                        </div>
                    </div>
                </div>
                
                <canvas id="connectionChart" height="80"></canvas>
                
                <div class="mt-3">
                    <div class="row text-center">
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body py-2">
                                    <small class="text-muted">Total Downtime</small>
                                    <h5 class="mb-0" id="totalDowntime">-</h5>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body py-2">
                                    <small class="text-muted">Rata-rata Response</small>
                                    <h5 class="mb-0" id="avgResponse">-</h5>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body py-2">
                                    <small class="text-muted">Uptime Percentage</small>
                                    <h5 class="mb-0" id="uptimePercent">-</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="assets/modules/jquery.min.js"></script>
<script src="assets/modules/popper.js"></script>
<script src="assets/modules/bootstrap/js/bootstrap.min.js"></script>
<script src="assets/modules/nicescroll/jquery.nicescroll.min.js"></script>
<script src="assets/modules/moment.min.js"></script>
<script src="assets/js/stisla.js"></script>
<script src="assets/js/scripts.js"></script>
<script src="assets/js/custom.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
let myChart = null;
let currentUrlId = null;
let currentChartType = 'downtime';

function showChart(urlId, namaKoneksi) {
    currentUrlId = urlId;
    $('#modalChartTitle').text(namaKoneksi);
    $('#chartModal').modal('show');
    loadChartData();
}

function loadChartData() {
    const period = $('#chartPeriod').val();
    
    $.ajax({
        url: 'get_chart_data.php',
        method: 'GET',
        data: {
            url_id: currentUrlId,
            period: period,
            type: currentChartType
        },
        dataType: 'json',
        success: function(response) {
            renderChart(response);
            updateStats(response.stats);
        },
        error: function() {
            alert('Gagal memuat data grafik');
        }
    });
}

function renderChart(data) {
    const ctx = document.getElementById('connectionChart').getContext('2d');
    
    if (myChart) {
        myChart.destroy();
    }
    
    let chartConfig = {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [{
                label: data.label,
                data: data.values,
                borderColor: data.color,
                backgroundColor: data.bgColor,
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (currentChartType === 'uptime') {
                                label += context.parsed.y + '%';
                            } else if (currentChartType === 'response') {
                                label += context.parsed.y + ' ms';
                            } else {
                                label += context.parsed.y + ' menit';
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            if (currentChartType === 'uptime') {
                                return value + '%';
                            } else if (currentChartType === 'response') {
                                return value + ' ms';
                            } else {
                                return value + ' min';
                            }
                        }
                    }
                }
            }
        }
    };
    
    myChart = new Chart(ctx, chartConfig);
}

function updateStats(stats) {
    $('#totalDowntime').text(stats.total_downtime);
    $('#avgResponse').text(stats.avg_response);
    $('#uptimePercent').text(stats.uptime_percent);
}

// Event handlers
$('#chartPeriod').change(function() {
    loadChartData();
});

$('[data-chart-type]').click(function() {
    $('[data-chart-type]').removeClass('active');
    $(this).addClass('active');
    currentChartType = $(this).data('chart-type');
    loadChartData();
});

function refreshStatus(){
    // Show loading indicator
    $('#refresh-indicator').fadeIn();
    
    $.getJSON('cek_status_ajax.php', function(data){
        var total = data.length;
        var online = 0;
        var offline = 0;
        
        data.forEach(function(row){
            // Hitung online dan offline
            if (row.status === 'online') {
                online++;
            } else {
                offline++;
            }
            
            var $card = $(".conn-card[data-id='" + row.id + "']");
            if ($card.length === 0) return;
            
            var statusClass = row.status === 'online' ? 'online' : 'offline';
            var iconClass = row.status === 'online' ? 'fa-check-circle' : 'fa-times-circle';
            var statusText = row.status === 'online' ? 'Online' : 'Offline';
            var pulseClass = row.status === 'online' ? '' : 'pulse';
            
            // Update card class
            $card.removeClass('online offline').addClass(statusClass);
            
            // Update icon
            var $icon = $card.find('.conn-icon');
            $icon.removeClass('online offline pulse').addClass(statusClass + ' ' + pulseClass);
            $icon.find('i').removeClass().addClass('fas ' + iconClass);
            
            // Update status
            var $status = $card.find('.conn-status');
            $status.removeClass('online offline').addClass(statusClass);
            $status.html('<i class="fas ' + iconClass + '"></i> ' + statusText);
            
            // Update time
            var $time = $card.find('.conn-time');
            if (row.status === 'online' && row.time) {
                var timeClass = '';
                if (row.time < 100) timeClass = 'fast';
                else if (row.time < 200) timeClass = 'medium';
                else timeClass = 'slow';
                
                $time.removeClass('fast medium slow').addClass(timeClass);
                $time.html(row.time + '<span class="conn-time-label">ms</span>');
            } else {
                $time.removeClass('fast medium slow');
                $time.html('-<span class="conn-time-label">ms</span>');
            }
        });
        
        // Update stats
        var uptime = total > 0 ? Math.round((online / total) * 100) : 0;
        $('.stat-box').eq(0).find('h3').text(total);
        $('.stat-box').eq(1).find('h3').text(online);
        $('.stat-box').eq(2).find('h3').text(offline);
        $('.stat-box').eq(3).find('h3').text(uptime + '%');
        
        // Hide loading indicator
        $('#refresh-indicator').fadeOut();
    }).fail(function(){
        console.error('Failed to fetch status');
        $('#refresh-indicator').fadeOut();
    });
}

// Auto refresh setiap 10 detik (hanya di tab monitoring)
<?php if ($active_tab == 'monitoring'): ?>
setInterval(refreshStatus, 10000);

// Initial load
$(document).ready(function(){
    setTimeout(refreshStatus, 1000);
});
<?php endif; ?>

// ========== API TESTER FUNCTIONS ==========
<?php if ($active_tab == 'tester'): ?>
// Show/hide body field based on method
$('#method').change(function() {
    if ($(this).val() === 'POST' || $(this).val() === 'PUT') {
        $('#bodyGroup').slideDown();
    } else {
        $('#bodyGroup').slideUp();
    }
});

// Auto-fill URL from select
$('#urlSelect').change(function() {
    $('#apiUrl').val($(this).val());
});

// Toggle BPJS auth fields
$('#useBpjsAuth').change(function() {
    if ($(this).is(':checked')) {
        $('#bpjsAuthFields').slideDown();
    } else {
        $('#bpjsAuthFields').slideUp();
    }
});

function testApi() {
    const url = $('#apiUrl').val();
    const method = $('#method').val();
    const headers = $('#apiHeaders').val();
    const body = $('#apiBody').val();
    const useBpjsAuth = $('#useBpjsAuth').is(':checked');
    
    if (!url) {
        alert('URL harus diisi!');
        return;
    }
    
    $('#loadingOverlay').addClass('active');
    $('#responsePanel').hide();
    
    const data = {
        url: url,
        method: method,
        headers: headers,
        body: body,
        useBpjsAuth: useBpjsAuth ? 1 : 0,
        consId: $('#consId').val(),
        secretKey: $('#secretKey').val(),
        userKey: $('#userKey').val()
    };
    
    $.ajax({
        url: 'api_tester_execute.php',
        method: 'POST',
        data: data,
        dataType: 'json',
        success: function(response) {
            $('#loadingOverlay').removeClass('active');
            displayResponse(response);
            addToHistory(response);
        },
        error: function(xhr) {
            $('#loadingOverlay').removeClass('active');
            alert('Error: ' + (xhr.responseText || 'Unknown error'));
        }
    });
}

function displayResponse(response) {
    $('#responsePanel').show();
    
    // Status
    const statusClass = response.response.status >= 200 && response.response.status < 300 ? 'text-success' : 'text-danger';
    $('#statusCode').html('<span class="' + statusClass + '">' + response.response.status + '</span>');
    $('#statusText').text(response.response.statusText);
    
    // Time & Size
    let timeClass = 'text-success';
    if (response.response.time > 200) timeClass = 'text-danger';
    else if (response.response.time > 100) timeClass = 'text-warning';
    
    $('#responseTime').html('<span class="' + timeClass + '">' + response.response.time + '</span>');
    $('#responseSize').text(response.response.size.toLocaleString());
    
    // Body
    try {
        const jsonBody = JSON.parse(response.response.body);
        $('#responseBody').text(JSON.stringify(jsonBody, null, 2));
    } catch(e) {
        $('#responseBody').text(response.response.body);
    }
    
    // Headers
    let headersHtml = '';
    for (const [key, value] of Object.entries(response.response.headers)) {
        headersHtml += '<div class="header-item"><strong>' + key + ':</strong> ' + value + '</div>';
    }
    $('#responseHeaders').html(headersHtml || '<p class="text-muted">No headers</p>');
    
    // Raw
    $('#responseRaw').text(response.response.body);
    
    // Decrypted (jika ada)
    if (response.decrypted) {
        $('#decryptedTab').show();
        try {
            if (typeof response.decrypted === 'object') {
                $('#responseDecrypted').text(JSON.stringify(response.decrypted, null, 2));
            } else {
                $('#responseDecrypted').text(response.decrypted);
            }
        } catch(e) {
            $('#responseDecrypted').text(response.decrypted);
        }
    } else {
        $('#decryptedTab').hide();
    }
    
    // Scroll to response
    $('html, body').animate({
        scrollTop: $("#responsePanel").offset().top - 100
    }, 500);
}

function addToHistory(response) {
    const method = response.request.method;
    const url = response.request.url;
    const status = response.response.status;
    const time = response.response.time;
    const timestamp = response.request.timestamp;
    
    const statusClass = status >= 200 && status < 300 ? 'success' : 'error';
    const methodClass = 'method-' + method;
    
    const historyHtml = '<div class="history-item ' + statusClass + '">' +
        '<div class="d-flex justify-content-between align-items-start mb-1">' +
        '<div>' +
        '<span class="method-badge ' + methodClass + '">' + method + '</span> ' +
        '<span class="badge badge-' + (statusClass === 'success' ? 'success' : 'danger') + '">' + status + '</span>' +
        '</div>' +
        '<small class="text-muted">' + time + 'ms</small>' +
        '</div>' +
        '<div style="font-size:11px;"><small>' + url.substring(0, 50) + (url.length > 50 ? '...' : '') + '</small></div>' +
        '<div class="mt-1"><small class="text-muted"><i class="far fa-clock"></i> ' + timestamp + '</small></div>' +
        '</div>';
    
    if ($('#historyList p').length) {
        $('#historyList').html(historyHtml);
    } else {
        $('#historyList').prepend(historyHtml);
    }
    
    // Limit history to 10 items
    if ($('#historyList .history-item').length > 10) {
        $('#historyList .history-item:last').remove();
    }
}
<?php endif; ?>

// ========== DIAGNOSA KONEKSI FUNCTIONS ==========
<?php if ($active_tab == 'diagnosa'): ?>
let diagnosaData = null;

$('#diagnosaTarget').change(function() {
    if ($(this).val() === 'custom') {
        $('#customUrlGroup').slideDown();
    } else {
        $('#customUrlGroup').slideUp();
    }
});

function startDiagnosa() {
    let target = $('#diagnosaTarget').val();
    
    if (!target) {
        alert('Pilih target server terlebih dahulu!');
        return;
    }
    
    if (target === 'custom') {
        target = $('#customUrl').val();
        if (!target) {
            alert('Masukkan URL/IP custom!');
            return;
        }
    }
    
    const count = $('#pingCount').val();
    
    $('#diagnosaEmpty').hide();
    $('#diagnosaResult').hide();
    $('#diagnosaLoading').show();
    $('#exportBtn').hide();
    
    $.ajax({
        url: 'diagnosa_koneksi.php',
        method: 'POST',
        data: {
            target: target,
            count: count
        },
        dataType: 'json',
        success: function(response) {
            $('#diagnosaLoading').hide();
            diagnosaData = response;
            displayDiagnosaResult(response);
        },
        error: function(xhr) {
            $('#diagnosaLoading').hide();
            alert('Error: ' + (xhr.responseText || 'Gagal melakukan diagnosa'));
        }
    });
}

function displayDiagnosaResult(data) {
    $('#diagnosaResult').show();
    $('#exportBtn').show();
    
    // Verdict
    const successRate = data.summary.success_rate;
    const avgTime = data.summary.avg_time;
    const lossRate = data.summary.loss_rate;
    
    let verdict = '';
    let verdictClass = '';
    
    if (successRate >= 95 && avgTime < 100 && lossRate < 5) {
        verdict = '✅ <strong>KONEKSI RS BAGUS</strong> - Jika BPJS error, masalahnya ada di server BPJS, bukan koneksi Rumah Sakit!';
        verdictClass = 'alert-success';
    } else if (successRate >= 80 && avgTime < 200) {
        verdict = '⚠️ <strong>KONEKSI RS CUKUP BAIK</strong> - Ada sedikit kendala tapi masih dalam batas wajar. Periksa lebih lanjut jika masalah berlanjut.';
        verdictClass = 'alert-warning';
    } else {
        verdict = '❌ <strong>ADA MASALAH KONEKSI</strong> - Success rate rendah atau response time tinggi. Kemungkinan: 1) Server target bermasalah, 2) Jaringan internet RS bermasalah, 3) Routing bermasalah.';
        verdictClass = 'alert-danger';
    }
    
    $('#verdictBox').removeClass().addClass('alert ' + verdictClass);
    $('#verdictText').html(verdict);
    
    // Stats
    $('#statSuccess').text(data.summary.success_count + '/' + data.summary.total);
    $('#statFailed').text(data.summary.failed_count);
    
    const avgTimeClass = avgTime < 100 ? 'text-success' : (avgTime < 200 ? 'text-warning' : 'text-danger');
    $('#statAvgTime').html('<span class="' + avgTimeClass + '">' + avgTime.toFixed(2) + '</span>');
    
    // Detail table
    let tableHtml = '';
    data.details.forEach(function(item, index) {
        const statusBadge = item.status === 'success' ? 
            '<span class="badge badge-success">OK</span>' : 
            '<span class="badge badge-danger">FAIL</span>';
        
        const timeDisplay = item.time ? item.time + ' ms' : '-';
        const ttlDisplay = item.ttl || '-';
        
        tableHtml += '<tr>' +
            '<td>' + (index + 1) + '</td>' +
            '<td>' + statusBadge + '</td>' +
            '<td>' + timeDisplay + '</td>' +
            '<td>' + ttlDisplay + '</td>' +
            '</tr>';
    });
    $('#detailTable').html(tableHtml);
    
    // Technical info
    $('#infoTarget').text(data.info.target);
    $('#infoIP').text(data.info.ip || 'N/A');
    $('#infoTime').text(data.info.timestamp);
    $('#infoLoss').text(lossRate.toFixed(1) + '%');
}

function exportDiagnosa() {
    if (!diagnosaData) {
        alert('Belum ada data diagnosa!');
        return;
    }
    
    // Generate PDF via backend
    const form = $('<form>', {
        'method': 'POST',
        'action': 'export_diagnosa_pdf.php',
        'target': '_blank'
    });
    
    $('<input>').attr({
        'type': 'hidden',
        'name': 'data',
        'value': JSON.stringify(diagnosaData)
    }).appendTo(form);
    
    form.appendTo('body').submit().remove();
}
<?php endif; ?>
</script>

</body>
</html>