<?php
session_start();
require 'koneksi.php';

// ===== CSRF TOKEN VALIDATION =====
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo "<script>alert('Invalid security token. Silakan muat ulang halaman.'); window.location='login.php';</script>";
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    
    // ===== VALIDASI EMAIL =====
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<script>alert('Format email tidak valid.'); history.back();</script>";
        exit;
    }
    
    // ===== CEK EMAIL DI DATABASE =====
    $stmt = $conn->prepare("SELECT id, nama, status FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows === 1) {
        $stmt->bind_result($user_id, $nama, $status);
        $stmt->fetch();
        
        // Cek status user
        if ($status != 'active') {
            // Jangan kasih tahu detail, pakai generic message
            echo "<script>alert('Permintaan reset password telah dikirim ke email Anda (jika terdaftar).'); window.location='login.php';</script>";
            exit;
        }
        
        // ===== GENERATE RESET TOKEN =====
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token berlaku 1 jam
        
        // ===== SIMPAN TOKEN KE DATABASE =====
        $insert = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
        $insert->bind_param("sss", $email, $token, $expires_at);
        
        if ($insert->execute()) {
            // ===== KIRIM EMAIL (Customize sesuai mail server Anda) =====
            
            // Opsi 1: Menggunakan PHP mail()
            $reset_link = "https://yourdomain.com/reset_password.php?token=$token";
            $subject = "Reset Password - FixPoint";
            $message = "Halo $nama,\n\n";
            $message .= "Anda menerima email ini karena ada permintaan reset password untuk akun Anda.\n\n";
            $message .= "Klik link berikut untuk reset password:\n";
            $message .= "$reset_link\n\n";
            $message .= "Link ini akan kadaluarsa dalam 1 jam.\n\n";
            $message .= "Jika Anda tidak meminta reset password, abaikan email ini.\n\n";
            $message .= "Terima kasih,\nTim FixPoint";
            
            $headers = "From: noreply@yourdomain.com\r\n";
            $headers .= "Reply-To: support@yourdomain.com\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();
            
            // Uncomment jika mail server sudah dikonfigurasi
            // mail($email, $subject, $message, $headers);
            
            // ===== LOG ACTIVITY =====
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            
            $log = $conn->prepare("INSERT INTO activity_log (user_id, action, description, ip_address, user_agent) VALUES (?, 'forgot_password', 'Request reset password', ?, ?)");
            if ($log) {
                $log->bind_param("iss", $user_id, $ip_address, $user_agent);
                $log->execute();
                $log->close();
            }
            
            // ===== TELEGRAM NOTIFICATION (Optional) =====
            $token_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nilai FROM setting WHERE nama = 'telegram_bot_token' LIMIT 1"));
            $chatid_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nilai FROM setting WHERE nama = 'telegram_chat_id' LIMIT 1"));
            
            if ($token_row && $chatid_row) {
                $tg_token = $token_row['nilai'];
                $chat_id = $chatid_row['nilai'];
                
                $pesan = "🔐 <b>PERMINTAAN RESET PASSWORD</b>\n\n";
                $pesan .= "👤 <b>Nama:</b> " . htmlspecialchars($nama) . "\n";
                $pesan .= "✉️ <b>Email:</b> " . htmlspecialchars($email) . "\n";
                $pesan .= "🌐 <b>IP:</b> $ip_address\n";
                $pesan .= "⏰ <b>Waktu:</b> " . date('Y-m-d H:i:s') . "\n";
                
                $url = "https://api.telegram.org/bot$tg_token/sendMessage";
                $data = [
                    'chat_id' => $chat_id,
                    'text' => $pesan,
                    'parse_mode' => 'HTML'
                ];
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_exec($ch);
                curl_close($ch);
            }
        }
        
        $insert->close();
    }
    
    // ===== GENERIC MESSAGE (Keamanan) =====
    // Selalu tampilkan pesan sukses meskipun email tidak ditemukan
    // Ini mencegah attacker tahu email mana yang terdaftar
    echo "<script>
        alert('Jika email terdaftar, link reset password telah dikirim ke email Anda.');
        window.location='login.php';
    </script>";
    
    $stmt->close();
}

$conn->close();
?>