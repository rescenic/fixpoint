<?php
// ===================================================
// PHPMailer NAMESPACE (WAJIB DI PALING ATAS)
// ===================================================
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ===================================================
// DEBUG MODE
// ===================================================
define('DEBUG', true);
error_reporting(E_ALL);
ini_set('display_errors', DEBUG ? 1 : 0);
ini_set('display_startup_errors', DEBUG ? 1 : 0);

// ===================================================
// SESSION + TIMEZONE (INI KUNCI MASALAH)
// ===================================================
session_start();
date_default_timezone_set('Asia/Jakarta'); // 🔥 WAJIB

require 'koneksi.php';

// ===================================================
// VALIDASI CSRF
// ===================================================
if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    $_POST['csrf_token'] !== $_SESSION['csrf_token']
) {
    $_SESSION['forgot_error'] = "Token keamanan tidak valid.";
    header("Location: login.php");
    exit;
}

// ===================================================
// PROSES POST
// ===================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit;
}

$email = trim($_POST['email'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['forgot_error'] = "Format email tidak valid.";
    header("Location: login.php");
    exit;
}

// ===================================================
// CEK USER
// ===================================================
$stmt = $conn->prepare("SELECT id, nama, email, status FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows !== 1) {
    $_SESSION['forgot_success'] = "Jika email terdaftar, OTP akan dikirim.";
    header("Location: verify_otp.php");
    exit;
}

$stmt->bind_result($user_id, $nama, $user_email, $status);
$stmt->fetch();
$stmt->close();

if ($status !== 'active') {
    $_SESSION['forgot_error'] = "Akun belum aktif.";
    header("Location: login.php");
    exit;
}

// ===================================================
// 🔐 GENERATE OTP (FIX TIMEZONE)
// ===================================================
$otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

// ❌ JANGAN pakai strtotime('+10 minutes')
// ✅ PAKAI time()
$expires_at = date('Y-m-d H:i:s', time() + 600); // 10 menit

$insert = $conn->prepare("
    INSERT INTO password_resets (email, token, expires_at, used)
    VALUES (?, ?, ?, 0)
");
$insert->bind_param("sss", $email, $otp, $expires_at);
$insert->execute();
$insert->close();

// ===================================================
// KIRIM EMAIL
// ===================================================
// ===================================================
// KIRIM EMAIL (VERSI PROFESIONAL)
// ===================================================
$email_sent = false;

require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'PHPMailer/src/Exception.php';

$mail_setting = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT * FROM mail_settings LIMIT 1")
);

if ($mail_setting) {
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $mail_setting['mail_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $mail_setting['mail_username'];
        $mail->Password   = $mail_setting['mail_password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $mail_setting['mail_port'];
        $mail->CharSet    = 'UTF-8';

        // Pengirim & Penerima
        $mail->setFrom(
            $mail_setting['mail_from_email'],
            'FixPoint Smart Office Management System'
        );
        $mail->addAddress($user_email, $nama);

        // Format Email
        $mail->isHTML(true);
        $mail->Subject = "Kode Verifikasi Reset Password – FixPoint";

        // ================= ISI EMAIL =================
        $mail->Body = "
        <div style='font-family:Arial,Helvetica,sans-serif;
                    background:#f4f6f8;
                    padding:30px;'>

          <div style='max-width:600px;
                      margin:auto;
                      background:#ffffff;
                      border-radius:10px;
                      overflow:hidden;'>

            <div style='background:#4f46e5;
                        color:#ffffff;
                        padding:20px;
                        text-align:center;'>
              <h2 style='margin:0;'>FixPoint</h2>
              <p style='margin:5px 0 0;'>Smart Office Management System</p>
            </div>

            <div style='padding:30px; color:#333;'>
              <p>Yth. <strong>$nama</strong>,</p>

              <p>
                Kami menerima permintaan untuk melakukan <strong>reset password</strong>
                pada akun FixPoint Anda.
              </p>

              <p>
                Silakan gunakan <strong>Kode OTP</strong> berikut untuk melanjutkan proses:
              </p>

              <div style='text-align:center;
                          margin:30px 0;
                          font-size:32px;
                          font-weight:bold;
                          letter-spacing:6px;
                          color:#4f46e5;'>
                $otp
              </div>

              <p>
                Kode ini <strong>berlaku selama 10 menit</strong> sejak email ini dikirim.
              </p>

              <p style='color:#555;'>
                Jika Anda tidak merasa melakukan permintaan reset password,
                silakan abaikan email ini. Tidak ada perubahan yang akan dilakukan
                pada akun Anda.
              </p>

              <hr style='margin:30px 0;'>

              <p style='font-size:13px; color:#888;'>
                Email ini dikirim secara otomatis oleh sistem FixPoint.
                Mohon tidak membalas email ini.
              </p>
            </div>
          </div>
        </div>
        ";

        // Versi teks (fallback)
        $mail->AltBody =
            "FixPoint - Reset Password\n\n" .
            "Kode OTP Anda: $otp\n" .
            "Berlaku selama 10 menit.\n\n" .
            "Jika Anda tidak merasa melakukan permintaan ini, abaikan email ini.";

        $mail->send();
        $email_sent = true;

    } catch (Exception $e) {
        error_log("EMAIL OTP ERROR: " . $e->getMessage());
    }
}


// ===================================================
// SESSION RESULT
// ===================================================
$_SESSION['forgot_success'] = $email_sent
    ? "📧 OTP telah dikirim ke email Anda."
    : "OTP Anda: <strong>$otp</strong>";

$_SESSION['reset_email'] = $email;

header("Location: verify_otp.php");
exit;
