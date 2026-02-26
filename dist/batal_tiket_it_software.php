<?php
include 'security.php';
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

$user_id = $_SESSION['user_id'];

// Proses pembatalan tiket
if (isset($_POST['batal']) && isset($_POST['tiket_id'])) {
    $tiket_id = mysqli_real_escape_string($conn, $_POST['tiket_id']);
    
    // Cek apakah tiket milik user yang login dan ambil detail tiket
    $queryCheck = mysqli_query($conn, "SELECT * FROM tiket_it_software WHERE id = '$tiket_id' AND user_id = '$user_id'");
    
    if (mysqli_num_rows($queryCheck) > 0) {
        $tiket = mysqli_fetch_assoc($queryCheck);
        
        // Hanya bisa dibatalkan jika status Menunggu dan belum divalidasi
        if ($tiket['status'] == 'Menunggu' && $tiket['status_validasi'] == 'Belum Validasi') {
            
            // Simpan data tiket sebelum dihapus untuk notifikasi
            $nomor_tiket = $tiket['nomor_tiket'];
            $nama = $tiket['nama'];
            $jabatan = $tiket['jabatan'];
            $unit_kerja = $tiket['unit_kerja'];
            $kategori = $tiket['kategori'];
            $kendala = $tiket['kendala'];
            $tanggal_input = $tiket['tanggal_input'];
            $waktu_batal = date('Y-m-d H:i:s');
            
            // Hapus tiket dari database
            $queryDelete = mysqli_query($conn, "DELETE FROM tiket_it_software WHERE id = '$tiket_id'");
            
            if ($queryDelete) {
                // --- Kirim Notifikasi Telegram ---
                $token_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nilai FROM setting WHERE nama='telegram_bot_token' LIMIT 1"));
                $chatid_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nilai FROM setting WHERE nama='telegram_chat_id' LIMIT 1"));
                $token = $token_row['nilai'] ?? '';
                $chat_id = $chatid_row['nilai'] ?? '';
                
                $pesan_telegram  = "<b>🚫 TIKET IT SOFTWARE DIBATALKAN</b>\n\n";
                $pesan_telegram .= "🆔 <b>Nomor:</b> <code>$nomor_tiket</code>\n";
                $pesan_telegram .= "👤 <b>Nama:</b> $nama\n";
                $pesan_telegram .= "💼 <b>Jabatan:</b> $jabatan\n";
                $pesan_telegram .= "🏢 <b>Unit:</b> $unit_kerja\n";
                $pesan_telegram .= "📂 <b>Kategori:</b> $kategori\n";
                $pesan_telegram .= "🛠️ <b>Kendala:</b>\n<pre>$kendala</pre>\n";
                $pesan_telegram .= "📅 <b>Tanggal Input:</b> $tanggal_input\n";
                $pesan_telegram .= "⏰ <b>Waktu Dibatalkan:</b> $waktu_batal\n";
                $pesan_telegram .= "❌ <b>Status:</b> <i>Dibatalkan oleh user</i>";
                
                if ($token && $chat_id) {
                    $url = "https://api.telegram.org/bot$token/sendMessage";
                    $data = [
                        'chat_id' => $chat_id,
                        'text' => $pesan_telegram,
                        'parse_mode' => 'HTML'
                    ];
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_exec($ch);
                    curl_close($ch);
                }
                
                echo "<script>
                    alert('Tiket berhasil dibatalkan dan dihapus. Notifikasi telah dikirim.');
                    window.location.href = 'order_tiket_it_software.php';
                </script>";
                exit;
            } else {
                echo "<script>
                    alert('Gagal membatalkan tiket. Silakan coba lagi.');
                    window.location.href = 'order_tiket_it_software.php';
                </script>";
                exit;
            }
        } else {
            echo "<script>
                alert('Tiket tidak dapat dibatalkan. Hanya tiket dengan status Menunggu dan Belum Validasi yang dapat dibatalkan.');
                window.location.href = 'order_tiket_it_software.php';
            </script>";
            exit;
        }
    } else {
        echo "<script>
            alert('Tiket tidak ditemukan atau Anda tidak memiliki akses.');
            window.location.href = 'order_tiket_it_software.php';
        </script>";
        exit;
    }
} else {
    // Jika akses langsung tanpa POST
    header('Location: order_tiket_it_software.php');
    exit;
}
?>