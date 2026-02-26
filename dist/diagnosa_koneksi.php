<?php
header('Content-Type: application/json');
date_default_timezone_set('Asia/Jakarta');

$target = isset($_POST['target']) ? $_POST['target'] : '';
$count = isset($_POST['count']) ? intval($_POST['count']) : 10;

if (empty($target)) {
    echo json_encode(['error' => 'Target required']);
    exit;
}

// Sanitize target
$target = preg_replace('/[^a-zA-Z0-9\.\-]/', '', $target);

// Detect OS
$is_windows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

// Get IP address
$ip = gethostbyname($target);

// Perform ping tests
$results = [];
$success_count = 0;
$failed_count = 0;
$total_time = 0;
$times = [];

for ($i = 0; $i < $count; $i++) {
    $output = [];
    $result_code = 0;
    
    if ($is_windows) {
        // Windows: ping -n 1 -w 2000 target
        @exec("ping -n 1 -w 2000 $target 2>&1", $output, $result_code);
    } else {
        // Linux: ping -c 1 -W 2 target
        @exec("ping -c 1 -W 2 $target 2>&1", $output, $result_code);
    }
    
    $ping_result = [
        'index' => $i + 1,
        'status' => 'failed',
        'time' => null,
        'ttl' => null
    ];
    
    if ($result_code === 0) {
        // Parse successful ping
        $output_text = implode(' ', $output);
        
        // Extract time
        if (preg_match('/time[=<]([0-9.]+)\s*ms/i', $output_text, $matches)) {
            $time = floatval($matches[1]);
            $ping_result['time'] = $time;
            $total_time += $time;
            $times[] = $time;
        } elseif (preg_match('/time=([0-9.]+)\s*ms/i', $output_text, $matches)) {
            $time = floatval($matches[1]);
            $ping_result['time'] = $time;
            $total_time += $time;
            $times[] = $time;
        }
        
        // Extract TTL
        if (preg_match('/ttl[=\s]+([0-9]+)/i', $output_text, $matches)) {
            $ping_result['ttl'] = intval($matches[1]);
        } elseif (preg_match('/TTL=([0-9]+)/i', $output_text, $matches)) {
            $ping_result['ttl'] = intval($matches[1]);
        }
        
        $ping_result['status'] = 'success';
        $success_count++;
    } else {
        $failed_count++;
    }
    
    $results[] = $ping_result;
    
    // Small delay between pings
    if ($i < $count - 1) {
        usleep(100000); // 100ms delay
    }
}

// Calculate statistics
$success_rate = ($success_count / $count) * 100;
$loss_rate = ($failed_count / $count) * 100;
$avg_time = $success_count > 0 ? $total_time / $success_count : 0;

// Calculate jitter (standard deviation of times)
$jitter = 0;
if (count($times) > 1) {
    $mean = array_sum($times) / count($times);
    $variance_sum = 0;
    foreach ($times as $time) {
        $variance_sum += pow($time - $mean, 2);
    }
    $jitter = sqrt($variance_sum / count($times));
}

$min_time = !empty($times) ? min($times) : 0;
$max_time = !empty($times) ? max($times) : 0;

// Build response
$response = [
    'info' => [
        'target' => $target,
        'ip' => $ip,
        'timestamp' => date('Y-m-d H:i:s'),
        'ping_count' => $count,
        'os' => $is_windows ? 'Windows' : 'Linux'
    ],
    'summary' => [
        'total' => $count,
        'success_count' => $success_count,
        'failed_count' => $failed_count,
        'success_rate' => round($success_rate, 2),
        'loss_rate' => round($loss_rate, 2),
        'avg_time' => round($avg_time, 2),
        'min_time' => round($min_time, 2),
        'max_time' => round($max_time, 2),
        'jitter' => round($jitter, 2)
    ],
    'details' => $results
];

echo json_encode($response, JSON_PRETTY_PRINT);
?>