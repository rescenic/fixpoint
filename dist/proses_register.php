<?php
session_start();
require 'koneksi.php';

// ===== CSRF TOKEN VALIDATION =====
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo "<script>alert('Invalid security token. Silakan muat ulang halaman.'); window.location='login.php';</script>";
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize dan validasi input
    $nik         = trim($_POST['nik']);
    $nama        = trim($_POST['nama']);
    $jabatan     = trim($_POST['jabatan']);
    $unit_kerja  = trim($_POST['unit_kerja']);
    $email       = trim($_POST['email']);
    $password    = $_POST['password'];
    $konfirmasi  = $_POST['konfirmasi_password'];
    $atasan_id   = !empty($_POST['atasan_id']) ? $_POST['atasan_id'] : null;
    
    // ===== VALIDASI INPUT =====
    
// ================= VALIDASI NIK =================
// Wajib diisi, hanya angka, panjang bebas
if ($nik === '' || !ctype_digit($nik)) {
    echo "<script>
        alert('NIK wajib diisi dan hanya boleh berisi angka.');
        history.back();
    </script>";
    exit;
}


    
    // Validasi Nama (tidak boleh kosong, max 100 karakter)
    if (empty($nama) || strlen($nama) > 100) {
        echo "<script>alert('Nama tidak valid.'); history.back();</script>";
        exit;
    }
    
    // Validasi Email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<script>alert('Format email tidak valid.'); history.back();</script>";
        exit;
    }
    
    // Validasi password minimal 8 karakter
    if (strlen($password) < 8) {
        echo "<script>alert('Password minimal 8 karakter.'); history.back();</script>";
        exit;
    }
    
    // Validasi konfirmasi password
    if ($password !== $konfirmasi) {
        echo "<script>alert('Konfirmasi password tidak cocok.'); history.back();</script>";
        exit;
    }
    
    // ===== CEK DUPLIKASI =====
    
    // Cek apakah NIK sudah terdaftar
    $check_nik = $conn->prepare("SELECT id FROM users WHERE nik = ?");
    $check_nik->bind_param("s", $nik);
    $check_nik->execute();
    $check_nik->store_result();
    
    if ($check_nik->num_rows > 0) {
        echo "<script>alert('NIK sudah terdaftar.'); history.back();</script>";
        exit;
    }
    
    // Cek apakah email sudah terdaftar
    $check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check_email->bind_param("s", $email);
    $check_email->execute();
    $check_email->store_result();
    
    if ($check_email->num_rows > 0) {
        echo "<script>alert('Email sudah terdaftar.'); history.back();</script>";
        exit;
    }
    
    // ===== HASH PASSWORD =====
    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    
    // ===== INSERT KE DATABASE =====
    $stmt = $conn->prepare("INSERT INTO users (nik, nama, jabatan, unit_kerja, email, password_hash, atasan_id, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param("ssssssi", $nik, $nama, $jabatan, $unit_kerja, $email, $password_hash, $atasan_id);
    
    if ($stmt->execute()) {
        $new_user_id = $stmt->insert_id;
        
        // ===== LOG ACTIVITY (Optional) =====
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $log = $conn->prepare("INSERT INTO activity_log (user_id, action, description, ip_address, user_agent) VALUES (?, 'register', 'User mendaftar akun baru', ?, ?)");
        if ($log) {
            $log->bind_param("iss", $new_user_id, $ip_address, $user_agent);
            $log->execute();
            $log->close();
        }
        
        // ===== TELEGRAM NOTIFICATION =====
        $token_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nilai FROM setting WHERE nama = 'telegram_bot_token' LIMIT 1"));
        $chatid_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nilai FROM setting WHERE nama = 'telegram_chat_id' LIMIT 1"));
        
        if ($token_row && $chatid_row) {
            $token = $token_row['nilai'];
            $chat_id = $chatid_row['nilai'];
            
            // Escape HTML characters untuk Telegram
            $nama_esc = htmlspecialchars($nama, ENT_QUOTES, 'UTF-8');
            $nik_esc = htmlspecialchars($nik, ENT_QUOTES, 'UTF-8');
            $jabatan_esc = htmlspecialchars($jabatan, ENT_QUOTES, 'UTF-8');
            $unit_esc = htmlspecialchars($unit_kerja, ENT_QUOTES, 'UTF-8');
            $email_esc = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
            
            $pesan  = "<b>🆕 PENDAFTARAN AKUN BARU</b>\n\n";
            $pesan .= "👤 <b>Nama:</b> $nama_esc\n";
            $pesan .= "🆔 <b>NIK:</b> $nik_esc\n";
            $pesan .= "💼 <b>Jabatan:</b> $jabatan_esc\n";
            $pesan .= "🏢 <b>Unit:</b> $unit_esc\n";
            $pesan .= "✉️ <b>Email:</b> $email_esc\n";
            $pesan .= "⏳ <i>Menunggu aktivasi admin...</i>\n";
            
            $url = "https://api.telegram.org/bot$token/sendMessage";
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
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $result = curl_exec($ch);
            curl_close($ch);
        }
        
        // ===== WHATSAPP NOTIFICATION =====
        $wa_number_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nilai FROM wa_setting WHERE nama='wa_number' LIMIT 1"));
        $wa_url_row    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nilai FROM wa_setting WHERE nama='wa_gateway_url' LIMIT 1"));
        
        $wa_number = $wa_number_row ? $wa_number_row['nilai'] : '';
        $wa_url    = $wa_url_row ? $wa_url_row['nilai'] : '';
        
        if ($wa_number && $wa_url) {
            $wa_text = "🆕 PENDAFTARAN AKUN BARU DI APLIKASI FIXPOINT\n";
            $wa_text .= "Nama: $nama\n";
            $wa_text .= "NIK: $nik\n";
            $wa_text .= "Jabatan: $jabatan\n";
            $wa_text .= "Unit: $unit_kerja\n";
            $wa_text .= "Email: $email\n";
            $wa_text .= "Status: Menunggu aktivasi admin";
            
            $wa_data = http_build_query([
                'number' => $wa_number,
                'text'   => $wa_text
            ]);
            
            $wa_options = [
                'http' => [
                    'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method'  => 'POST',
                    'content' => $wa_data,
                    'timeout' => 5
                ]
            ];
            
            $wa_context = stream_context_create($wa_options);
            @file_get_contents($wa_url, false, $wa_context);
        }
        
        // ===== REGENERATE CSRF TOKEN =====
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        // ===== REDIRECT DENGAN PESAN SUKSES =====
        echo "<script>
            alert('Pendaftaran berhasil! Tunggu aktivasi dari admin.');
            window.location='login.php?registered=success';
        </script>";
        
    } else {
        // Log error untuk debugging (jangan tampilkan detail error ke user)
        error_log("Registration error: " . $stmt->error);
        
        echo "<script>
            alert('Pendaftaran gagal. Silakan coba lagi.');
            history.back();
        </script>";
    }
    
    $stmt->close();
}

$conn->close();
?>