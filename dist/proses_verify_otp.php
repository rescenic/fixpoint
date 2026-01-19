<?php
session_start();
require 'koneksi.php';

date_default_timezone_set('Asia/Jakarta');

// ================= CSRF
if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    $_POST['csrf_token'] !== $_SESSION['csrf_token']
) {
    $_SESSION['forgot_error'] = "Token tidak valid.";
    header("Location: verify_otp.php");
    exit;
}

$email = trim($_POST['email'] ?? '');
$otp   = trim($_POST['otp'] ?? '');

if ($email === '' || $otp === '' || strlen($otp) !== 6) {
    $_SESSION['forgot_error'] = "OTP tidak lengkap.";
    header("Location: verify_otp.php");
    exit;
}

// ================= AMBIL OTP
$stmt = $conn->prepare("
    SELECT id, expires_at, used
    FROM password_resets
    WHERE email = ? AND token = ?
    ORDER BY id DESC
    LIMIT 1
");
$stmt->bind_param("ss", $email, $otp);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $_SESSION['forgot_error'] = "OTP tidak valid.";
    header("Location: verify_otp.php");
    exit;
}

$data = $result->fetch_assoc();
$stmt->close();

// ================= CEK USED
if ((int)$data['used'] === 1) {
    $_SESSION['forgot_error'] = "OTP sudah digunakan.";
    header("Location: verify_otp.php");
    exit;
}

// ================= CEK EXPIRED (PAKAI PHP)
if (strtotime($data['expires_at']) < time()) {
    $_SESSION['forgot_error'] = "OTP sudah kadaluarsa.";
    header("Location: verify_otp.php");
    exit;
}

// ================= UPDATE USED
$upd = $conn->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
$upd->bind_param("i", $data['id']);
$upd->execute();
$upd->close();

// ================= SUCCESS
$_SESSION['reset_verified'] = true;
$_SESSION['reset_email'] = $email;

header("Location: reset_password.php");
exit;
