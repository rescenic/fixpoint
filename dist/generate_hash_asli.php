<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
include 'koneksi.php';

require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ===============================
// KONFIGURASI KEAMANAN
// ===============================
$master_password = 'FIXPOINT2025';

// Ambil pengaturan email
$mail_setting = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM mail_settings LIMIT 1"));
if (!$mail_setting) {
    die("<h2 style='color:red;text-align:center;margin-top:50px;'>❌ Pengaturan email tidak ditemukan.</h2>");
}

// ===============================
// AUTENTIKASI AKSES HALAMAN
// ===============================
if (!isset($_SESSION['authorized'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if ($_POST['password'] === $master_password) {
            $_SESSION['authorized'] = true;
            header("Location: generate_hash_asli.php");
            exit;
        } else {
            echo "<p style='color:red;text-align:center;'>❌ Password keamanan salah.</p>";
        }
    } else {
        echo '
        <div style="max-width:400px;margin:120px auto;padding:25px;
                    border-radius:10px;box-shadow:0 8px 25px rgba(0,0,0,.15);
                    text-align:center;font-family:Arial;">
            <h3>🔐 Akses Keamanan Terbatas</h3>
            <p style="font-size:13px;color:#666;">
                Halaman ini hanya dapat diakses oleh administrator sistem.
            </p>
            <form method="POST">
                <input type="password" name="password" placeholder="Password Keamanan"
                       style="padding:10px;width:100%;margin-top:10px;" required>
                <br><br>
                <button type="submit"
                        style="padding:10px 25px;border:none;border-radius:6px;
                               background:#2d7ef7;color:#fff;cursor:pointer;">
                    Masuk
                </button>
            </form>
        </div>';
        exit;
    }
}

// ===============================
// PROSES GENERATE HASH
// ===============================
$notifikasi = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_hash'])) {

    $file_path = 'sidebar.php';

    if (!file_exists($file_path)) {
        $notifikasi = "<p style='color:red;'>❌ File sidebar.php tidak ditemukan.</p>";
    } else {

        $hash     = sha1_file($file_path);
        $isi_file = htmlentities(file_get_contents($file_path));

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = $mail_setting['mail_host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $mail_setting['mail_username'];
            $mail->Password   = $mail_setting['mail_password'];
            $mail->SMTPSecure = 'tls';
            $mail->Port       = $mail_setting['mail_port'];

            $mail->setFrom(
                $mail_setting['mail_from_email'],
                $mail_setting['mail_from_name']
            );
            $mail->addAddress(
                $mail_setting['mail_from_email'],
                'Pemilik Aplikasi'
            );

            $mail->isHTML(true);
            $mail->Subject = '🔐 Laporan Hash Keamanan File sidebar.php';
            $mail->Body = "
                <h3>🔒 Verifikasi Integritas File</h3>
                <p>File: <strong>sidebar.php</strong></p>
                <p><strong>Hash SHA1:</strong></p>
                <code style='font-size:14px;'>$hash</code>
                <p><strong>Waktu Generate:</strong> ".date('d-m-Y H:i:s')."</p>
                <hr>
                <p><strong>Cuplikan isi file:</strong></p>
                <pre style='background:#f8f8f8;padding:10px;border:1px solid #ccc;
                             max-height:300px;overflow:auto;'>$isi_file</pre>
                <p style='font-size:12px;color:#777;'>
                    Email ini merupakan bagian dari sistem audit keamanan aplikasi.
                </p>
            ";

            $mail->send();
            $notifikasi = "<p style='color:green;'>✅ Hash berhasil dibuat dan dikirim ke email.</p>";

        } catch (Exception $e) {
            $notifikasi = "<p style='color:red;'>❌ Gagal mengirim email: {$mail->ErrorInfo}</p>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Verifikasi Integritas File</title>
</head>
<body style="background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;">

<div style="max-width:800px;margin:60px auto;padding:30px;
            background:#fff;border-radius:12px;
            box-shadow:0 12px 35px rgba(0,0,0,.15);">

    <h2 style="margin-bottom:5px;">🔐 Sistem Verifikasi Integritas File</h2>
    <p style="color:#666;margin-top:0;">
        Modul Keamanan Internal Aplikasi
    </p>

    <hr>

    <p style="line-height:1.7;text-align:justify;">
        Modul ini digunakan untuk <strong>memverifikasi keaslian dan integritas file sistem</strong>,
        khususnya file <code>sidebar.php</code> yang berperan penting dalam
        <strong>pengaturan menu dan hak akses pengguna</strong>.
        <br><br>
        Sistem akan menghasilkan <strong>hash SHA1</strong> sebagai <em>sidik digital</em> file.
        Setiap perubahan sekecil apa pun pada file akan menghasilkan nilai hash yang berbeda.
    </p>

    <ul style="line-height:1.8;">
        <li>🛡️ Mendeteksi perubahan file tanpa izin</li>
        <li>📑 Mendukung audit keamanan sistem</li>
        <li>🔍 Validasi integritas kode aplikasi</li>
        <li>⚠️ Pencegahan manipulasi menu & akses</li>
    </ul>

    <p>
        Hasil hash akan <strong>dikirim otomatis ke email administrator</strong>
        sebagai arsip dan bukti keamanan.
    </p>

    <hr>

    <div style="text-align:center;">
        <?= $notifikasi ?>
        <form method="POST" style="margin-top:20px;">
            <input type="hidden" name="generate_hash" value="1">
            <button type="submit"
                    style="padding:14px 32px;
                           background:#2d7ef7;
                           color:#fff;
                           border:none;
                           border-radius:8px;
                           font-size:15px;
                           cursor:pointer;">
                🔁 Generate & Kirim Hash Keamanan
            </button>
        </form>

        <p style="margin-top:25px;font-size:12px;color:#777;">
            ⚠️ Fitur ini bersifat rahasia dan hanya digunakan untuk kepentingan audit internal.
        </p>

        <a href="?logout=true"
           style="color:#c00;text-decoration:none;font-size:13px;">
           🔓 Keluar dari Mode Keamanan
        </a>
    </div>
</div>

</body>
</html>

<?php
// ===============================
// LOGOUT
// ===============================
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: generate_hash_asli.php");
    exit;
}
?>
