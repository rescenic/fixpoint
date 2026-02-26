<?php
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

$url_id = isset($_GET['url_id']) ? intval($_GET['url_id']) : 0;
$period = isset($_GET['period']) ? intval($_GET['period']) : 30;
$type = isset($_GET['type']) ? $_GET['type'] : 'downtime';

if ($url_id == 0) {
    echo json_encode(['error' => 'Invalid URL ID']);
    exit;
}

// Ambil data berdasarkan periode
$date_from = date('Y-m-d', strtotime("-$period days"));

// Inisialisasi array untuk semua tanggal dalam periode
$labels = [];
$values = [];
$current_date = strtotime($date_from);
$end_date = strtotime(date('Y-m-d'));

while ($current_date <= $end_date) {
    $date_str = date('Y-m-d', $current_date);
    $labels[] = date('d M', $current_date);
    $values[$date_str] = 0;
    $current_date = strtotime('+1 day', $current_date);
}

$response = [
    'labels' => $labels,
    'values' => [],
    'label' => '',
    'color' => '',
    'bgColor' => '',
    'stats' => [
        'total_downtime' => '0 menit',
        'avg_response' => '0 ms',
        'uptime_percent' => '100%'
    ]
];

if ($type == 'downtime') {
    // Grafik Downtime (dalam menit per hari)
    $query = "SELECT DATE(waktu_mulai) as tanggal, 
                     SUM(durasi_detik) / 60 as total_downtime_menit
              FROM log_koneksi 
              WHERE url_id = $url_id 
              AND status_to = 'offline'
              AND waktu_mulai >= '$date_from'
              GROUP BY DATE(waktu_mulai)
              ORDER BY tanggal ASC";
    
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $values[$row['tanggal']] = round($row['total_downtime_menit'], 2);
    }
    
    $response['label'] = 'Downtime (menit)';
    $response['color'] = 'rgb(220, 53, 69)';
    $response['bgColor'] = 'rgba(220, 53, 69, 0.1)';
    
    // Stats: Total downtime
    $total_query = "SELECT SUM(durasi_detik) as total 
                    FROM log_koneksi 
                    WHERE url_id = $url_id 
                    AND status_to = 'offline'
                    AND waktu_mulai >= '$date_from'";
    $total_result = mysqli_fetch_assoc(mysqli_query($conn, $total_query));
    $total_seconds = $total_result['total'] ?? 0;
    
    $hours = floor($total_seconds / 3600);
    $minutes = floor(($total_seconds % 3600) / 60);
    $response['stats']['total_downtime'] = $hours > 0 ? "$hours jam $minutes menit" : "$minutes menit";
    
} elseif ($type == 'uptime') {
    // Grafik Uptime Percentage per hari
    $query = "SELECT DATE(waktu_mulai) as tanggal,
                     COUNT(CASE WHEN status_to = 'online' THEN 1 END) as online_count,
                     COUNT(*) as total_count
              FROM log_koneksi
              WHERE url_id = $url_id
              AND waktu_mulai >= '$date_from'
              GROUP BY DATE(waktu_mulai)
              ORDER BY tanggal ASC";
    
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $uptime_percent = $row['total_count'] > 0 ? 
                         round(($row['online_count'] / $row['total_count']) * 100, 2) : 100;
        $values[$row['tanggal']] = $uptime_percent;
    }
    
    // Fill tanggal tanpa data dengan 100%
    foreach ($values as $date => $value) {
        if ($value == 0) {
            $values[$date] = 100;
        }
    }
    
    $response['label'] = 'Uptime (%)';
    $response['color'] = 'rgb(40, 167, 69)';
    $response['bgColor'] = 'rgba(40, 167, 69, 0.1)';
    
    // Stats: Average uptime
    $avg_uptime = array_sum($values) / count($values);
    $response['stats']['uptime_percent'] = round($avg_uptime, 2) . '%';
    
} elseif ($type == 'response') {
    // Grafik Response Time (belum ada implementasi, pakai data dummy atau dari log slow)
    $query = "SELECT DATE(waktu_mulai) as tanggal,
                     AVG(CAST(REPLACE(durasi_display, 'ms', '') AS DECIMAL(10,2))) as avg_response
              FROM log_koneksi
              WHERE url_id = $url_id
              AND status_to = 'slow'
              AND waktu_mulai >= '$date_from'
              GROUP BY DATE(waktu_mulai)
              ORDER BY tanggal ASC";
    
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $values[$row['tanggal']] = round($row['avg_response'], 2);
    }
    
    $response['label'] = 'Response Time (ms)';
    $response['color'] = 'rgb(255, 193, 7)';
    $response['bgColor'] = 'rgba(255, 193, 7, 0.1)';
    
    // Stats: Average response
    $non_zero_values = array_filter($values);
    $avg_response = count($non_zero_values) > 0 ? array_sum($non_zero_values) / count($non_zero_values) : 0;
    $response['stats']['avg_response'] = round($avg_response, 2) . ' ms';
}

// Convert values array to indexed array
$response['values'] = array_values($values);

// Calculate overall uptime percentage
$uptime_query = "SELECT 
                    SUM(CASE WHEN status_to = 'online' THEN 1 ELSE 0 END) as online_count,
                    COUNT(*) as total_count
                 FROM log_koneksi
                 WHERE url_id = $url_id
                 AND waktu_mulai >= '$date_from'";
$uptime_result = mysqli_fetch_assoc(mysqli_query($conn, $uptime_query));
$total_checks = $uptime_result['total_count'] ?? 0;
$online_checks = $uptime_result['online_count'] ?? 0;

if ($total_checks > 0) {
    $uptime_percent = round(($online_checks / $total_checks) * 100, 2);
    $response['stats']['uptime_percent'] = $uptime_percent . '%';
}

header('Content-Type: application/json');
echo json_encode($response);
?>