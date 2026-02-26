<?php
// check_integrity.php

$sidebar_path = __DIR__ . '/sidebar.php';
$expected_hash = '76fb0b05337b92a0e51b29d0da6dcff41747c477'; 

if (!file_exists($sidebar_path)) {
    die('
    <div style="max-width:600px;margin:80px auto;font-family:Arial,sans-serif;">
      <div style="border:2px solid #dc3545;border-radius:10px;padding:30px;background:#fff3f3;text-align:center;box-shadow:0 4px 10px rgba(0,0,0,0.1);">
        <i class="fas fa-exclamation-triangle" style="font-size:48px;color:#dc3545;"></i>
        <h2 style="color:#dc3545;margin-top:20px;">ERROR: File sidebar.php tidak ditemukan</h2>
        <p style="color:#333;font-size:16px;margin-top:15px;">
          ⚠️ Sistem mendeteksi bahwa file penting <strong>sidebar.php</strong> hilang.<br>
          Demi menjaga <strong>integritas & keamanan aplikasi</strong>, proses dihentikan.
        </p>
        <p style="color:#555;font-size:14px;margin-top:10px;">
          Mohon segera hubungi pengembang di <strong>0821 7784 6209</strong> untuk bantuan teknis.<br>
          Jangan melakukan perubahan tanpa izin resmi.
        </p>
        <p style="margin-top:20px;color:#666;font-size:13px;">
          🔒 Integritas sistem adalah prioritas utama. Terima kasih atas pengertian Anda.
        </p>
      </div>
    </div>
    ');
}

$current_hash = sha1_file($sidebar_path);

if ($current_hash !== $expected_hash) {
    die('
    <div style="max-width:600px;margin:80px auto;font-family:Arial,sans-serif;">
      <div style="border:2px solid #dc3545;border-radius:10px;padding:30px;background:#fff3f3;text-align:center;box-shadow:0 4px 10px rgba(0,0,0,0.1);">
        <i class="fas fa-ban" style="font-size:48px;color:#dc3545;"></i>
        <h2 style="color:#dc3545;margin-top:20px;">Akses Ditolak</h2>
        <p style="color:#333;font-size:16px;margin-top:15px;">
          🙏 Maaf, perubahan pada file <strong>sidebar.php</strong> tidak diizinkan.<br>
          Sistem mendeteksi adanya <strong>modifikasi tidak sah</strong>.
        </p>
        <p style="color:#555;font-size:14px;margin-top:10px;">
          Demi menjaga <strong>keamanan & konsistensi aplikasi</strong>, mohon tidak memodifikasi file ini.<br>
          Hubungi pengembang di <strong>0821 7784 6209 (Muhammad Wira satria Buana)</strong> untuk informasi lebih lanjut.
        </p>
        <p style="margin-top:20px;color:#666;font-size:13px;">
          🚀 Aplikasi ini dirancang untuk bekerja stabil. Setiap perubahan tanpa izin dapat menimbulkan risiko.<br>
          Terima kasih atas kerja sama dan komitmen menjaga sistem tetap aman.
        </p>
      </div>
    </div>
    ');
}
