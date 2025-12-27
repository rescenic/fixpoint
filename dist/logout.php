<?php
// ===== FILE: logout.php =====
// Script untuk logout yang aman

session_start();

// ===== OPTIONAL: LOG LOGOUT ACTIVITY =====
if (isset($_SESSION['user_id'])) {
    require 'koneksi.php';
    
    $user_id = $_SESSION['user_id'];
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    // Log activity (jika tabel activity_log sudah dibuat)
    $stmt = $conn->prepare("INSERT INTO activity_log (user_id, action, description, ip_address, user_agent) VALUES (?, 'logout', 'User logged out', ?, ?)");
    if ($stmt) {
        $stmt->bind_param("iss", $user_id, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
    }
    
    $conn->close();
}

// ===== DESTROY ALL SESSION DATA =====
$_SESSION = array();

// ===== DELETE SESSION COOKIE =====
if (isset($_COOKIE[session_name()])) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// ===== DESTROY SESSION =====
session_destroy();

// ===== REDIRECT TO LOGIN =====
header("Location: login.php?logout=success");
exit;
?>