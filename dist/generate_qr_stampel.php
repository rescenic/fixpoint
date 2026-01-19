<?php
/**
 * File: generate_qr_stampel.php
 * Generate Stempel Elektronik dengan Logo Perusahaan + QR Code Kecil
 * Versi Simple - Tidak memerlukan font TTF
 */

session_start();
include 'koneksi.php';

// Ambil token dari parameter
$token = isset($_GET['token']) ? mysqli_real_escape_string($conn, $_GET['token']) : '';

if (empty($token)) {
    die('Token tidak valid');
}

// Verifikasi token di database dan ambil data perusahaan
$q = mysqli_query($conn, "
    SELECT e.*, p.logo, p.nama_perusahaan, p.kota 
    FROM e_stampel e
    LEFT JOIN perusahaan p ON 1=1
    WHERE e.token='$token' AND e.status='aktif' 
    LIMIT 1
");

if (!$q || mysqli_num_rows($q) == 0) {
    die('E-Stampel tidak ditemukan atau sudah nonaktif');
}

$stampel = mysqli_fetch_assoc($q);

// URL untuk verifikasi
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
$verify_url = $base_url . "/cek_stampel.php?token=" . urlencode($token);

// ===================================================
// BUAT STEMPEL DENGAN GD LIBRARY
// ===================================================

// Ukuran canvas stempel (persegi)
$stampel_size = 400;
$canvas = imagecreatetruecolor($stampel_size, $stampel_size);

// Enable alpha blending untuk transparansi
imagealphablending($canvas, true);
imagesavealpha($canvas, true);

// Warna
$white = imagecolorallocate($canvas, 255, 255, 255);
$green = imagecolorallocate($canvas, 40, 167, 69); // Hijau untuk border
$dark_green = imagecolorallocate($canvas, 25, 135, 84);
$light_green = imagecolorallocate($canvas, 200, 240, 200);

// Background putih
imagefill($canvas, 0, 0, $white);

// ===================================================
// BORDER STEMPEL (LINGKARAN GANDA)
// ===================================================
$center = $stampel_size / 2;
$radius_outer = 190;
$radius_inner = 175;

// Lingkaran luar (tebal)
imagesetthickness($canvas, 10);
imagearc($canvas, $center, $center, $radius_outer * 2, $radius_outer * 2, 0, 360, $green);

// Lingkaran dalam
imagesetthickness($canvas, 4);
imagearc($canvas, $center, $center, $radius_inner * 2, $radius_inner * 2, 0, 360, $green);

// Background lingkaran dalam (hijau muda)
imagefilledellipse($canvas, $center, $center, $radius_inner * 2 - 10, $radius_inner * 2 - 10, $light_green);

// ===================================================
// LOGO PERUSAHAAN DI TENGAH
// ===================================================
$logo_path = '';
if (!empty($stampel['logo'])) {
    // Perbaiki path logo jika perlu
    $logo_path = $stampel['logo'];
    if (strpos($logo_path, 'dist/') === 0) {
        $logo_path = str_replace('dist/', '', $logo_path);
    }
}

// Coba load logo
$logo_img = null;
if (!empty($logo_path) && file_exists($logo_path)) {
    $ext = strtolower(pathinfo($logo_path, PATHINFO_EXTENSION));
    
    if ($ext == 'png') {
        $logo_img = @imagecreatefrompng($logo_path);
    } elseif ($ext == 'jpg' || $ext == 'jpeg') {
        $logo_img = @imagecreatefromjpeg($logo_path);
    } elseif ($ext == 'gif') {
        $logo_img = @imagecreatefromgif($logo_path);
    }
}

// Tampilkan logo di tengah
if ($logo_img) {
    $logo_width = imagesx($logo_img);
    $logo_height = imagesy($logo_img);
    
    // Resize logo agar pas (max 100x100)
    $max_logo = 100;
    $ratio = min($max_logo / $logo_width, $max_logo / $logo_height);
    $new_logo_w = $logo_width * $ratio;
    $new_logo_h = $logo_height * $ratio;
    
    $logo_x = $center - ($new_logo_w / 2);
    $logo_y = $center - ($new_logo_h / 2) - 30; // Posisi agak ke atas
    
    // Background putih untuk logo
    imagefilledrectangle($canvas, $logo_x - 5, $logo_y - 5, 
                        $logo_x + $new_logo_w + 5, $logo_y + $new_logo_h + 5, $white);
    
    imagecopyresampled($canvas, $logo_img, $logo_x, $logo_y, 0, 0, 
                       $new_logo_w, $new_logo_h, $logo_width, $logo_height);
    imagedestroy($logo_img);
}

// ===================================================
// TEKS NAMA PERUSAHAAN (ATAS)
// ===================================================
$nama_perusahaan = strtoupper($stampel['nama_perusahaan']);

// Batasi panjang nama (split jika terlalu panjang)
if (strlen($nama_perusahaan) > 25) {
    $words = explode(' ', $nama_perusahaan);
    $line1 = '';
    $line2 = '';
    
    foreach ($words as $i => $word) {
        if ($i < ceil(count($words) / 2)) {
            $line1 .= $word . ' ';
        } else {
            $line2 .= $word . ' ';
        }
    }
    
    $line1 = trim($line1);
    $line2 = trim($line2);
    
    // Tulis 2 baris
    $x1 = $center - (strlen($line1) * 4);
    imagestring($canvas, 4, $x1, 45, $line1, $dark_green);
    
    if (!empty($line2)) {
        $x2 = $center - (strlen($line2) * 4);
        imagestring($canvas, 4, $x2, 65, $line2, $dark_green);
    }
} else {
    $x = $center - (strlen($nama_perusahaan) * 4);
    imagestring($canvas, 5, $x, 50, $nama_perusahaan, $dark_green);
}

// ===================================================
// TEKS KOTA (BAWAH TENGAH)
// ===================================================
$kota_text = strtoupper($stampel['kota']);
$x = $center - (strlen($kota_text) * 3.5);
imagestring($canvas, 4, $x, $stampel_size - 70, $kota_text, $dark_green);

// ===================================================
// TEKS "E-STAMPEL RESMI"
// ===================================================
$stamp_text = "E-STAMPEL RESMI";
$x = $center - (strlen($stamp_text) * 3);
imagestring($canvas, 3, $x, $center + 45, $stamp_text, $dark_green);

// ===================================================
// QR CODE KECIL DI POJOK KANAN BAWAH
// ===================================================
$qr_api_url = "https://api.qrserver.com/v1/create-qr-code/?size=80x80&data=" . urlencode($verify_url);
$qr_img_data = @file_get_contents($qr_api_url);

if ($qr_img_data) {
    $qr_img = @imagecreatefromstring($qr_img_data);
    if ($qr_img) {
        // QR Code kecil 50x50 di pojok kanan bawah
        $qr_size = 50;
        $qr_x = $stampel_size - $qr_size - 25;
        $qr_y = $stampel_size - $qr_size - 25;
        
        // Background putih untuk QR
        imagefilledrectangle($canvas, $qr_x - 3, $qr_y - 3, 
                           $qr_x + $qr_size + 3, $qr_y + $qr_size + 3, $white);
        
        // Border hijau tipis untuk QR
        imagerectangle($canvas, $qr_x - 2, $qr_y - 2, 
                      $qr_x + $qr_size + 2, $qr_y + $qr_size + 2, $green);
        
        imagecopyresampled($canvas, $qr_img, $qr_x, $qr_y, 0, 0, 
                          $qr_size, $qr_size, 80, 80);
        imagedestroy($qr_img);
        
        // Label QR
        imagestring($canvas, 1, $qr_x - 5, $qr_y - 12, "SCAN", $dark_green);
    }
}

// ===================================================
// BINTANG DEKORASI (OPSIONAL)
// ===================================================
// Gambar bintang kecil di 4 pojok dalam lingkaran
$star_points = 5;
for ($i = 0; $i < 4; $i++) {
    $angle = ($i * 90) + 45; // 45, 135, 225, 315 derajat
    $star_x = $center + cos(deg2rad($angle)) * ($radius_inner - 20);
    $star_y = $center + sin(deg2rad($angle)) * ($radius_inner - 20);
    
    imagefilledellipse($canvas, $star_x, $star_y, 8, 8, $green);
}

// ===================================================
// OUTPUT IMAGE
// ===================================================
if (isset($_GET['download']) && $_GET['download'] == '1') {
    header('Content-Type: image/png');
    header('Content-Disposition: attachment; filename="E-Stampel_' . date('YmdHis') . '.png"');
} else {
    header('Content-Type: image/png');
}

imagepng($canvas, null, 9); // Quality 9 (highest)
imagedestroy($canvas);
?>