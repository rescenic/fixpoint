<?php
// ===== FILE: check_session.php =====
// Include file ini di setiap halaman yang memerlukan login

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===== SECURITY HEADERS =====
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// ===== CHECK IF USER IS LOGGED IN =====
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// ===== SESSION TIMEOUT (30 menit tidak aktif) =====
$timeout_duration = 1800; // 30 menit dalam detik

if (isset($_SESSION['last_activity'])) {
    $elapsed_time = time() - $_SESSION['last_activity'];
    
    if ($elapsed_time > $timeout_duration) {
        session_unset();
        session_destroy();
        header("Location: login.php?timeout=1");
        exit;
    }
}

$_SESSION['last_activity'] = time();

// ===== SESSION HIJACKING PROTECTION =====
// Periksa User Agent (Basic Protection)
if (isset($_SESSION['user_agent'])) {
    if ($_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        session_unset();
        session_destroy();
        header("Location: login.php?security=1");
        exit;
    }
} else {
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
}

// ===== REGENERATE SESSION ID SECARA BERKALA =====
// Regenerate setiap 15 menit untuk mencegah session fixation
if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 900) { // 15 menit
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// ===== CSRF TOKEN GENERATOR =====
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ===== FUNCTION: VALIDATE CSRF TOKEN =====
function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ===== FUNCTION: GET NEW CSRF TOKEN =====
function get_csrf_token() {
    return $_SESSION['csrf_token'] ?? '';
}
?>