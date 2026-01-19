<?php
/**
 * File: generate_pemberitahuan_pdf.php
 * Fungsi untuk generate PDF Surat Pemberitahuan menggunakan DOMPDF
 */

// ===================================================
// ERROR HANDLING AMAN PHP 8
// ===================================================
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 1);

// ===================================================
// LOAD DOMPDF
// ===================================================
require 'dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Include helper jika ada (untuk hash & log)
if (file_exists(__DIR__ . '/tte_hash_helper.php')) {
    require_once __DIR__ . '/tte_hash_helper.php';
}

// ===================================================
// FUNCTION TTE - SAMA SEPERTI cetak_izin_keluar.php
// ===================================================
function getTteByUser($conn, $user_id) {
    if (empty($user_id)) return null;
    $q = mysqli_query($conn, "
        SELECT * FROM tte_user
        WHERE user_id = '$user_id'
          AND status = 'aktif'
        ORDER BY created_at DESC
        LIMIT 1
    ");
    return mysqli_fetch_assoc($q) ?: null;
}

function qrTte($token) {
    $url = "http://" . $_SERVER['HTTP_HOST'] . "/cek_tte.php?token=" . $token;
    return "https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=" . urlencode($url);
}

function generatePemberitahuan_PDF($pemberitahuan_id) {
    global $conn;
    
    try {
        // Ambil data surat pemberitahuan dengan info pembuat
        $qPemberitahuan = mysqli_query($conn, "
            SELECT pb.*, us.nama as nama_pembuat, us.nik as nik_pembuat 
            FROM surat_pemberitahuan pb
            LEFT JOIN users us ON pb.dibuat_oleh = us.id
            WHERE pb.id = $pemberitahuan_id 
            LIMIT 1
        ");
        if(!$qPemberitahuan || mysqli_num_rows($qPemberitahuan) == 0) {
            return ['success' => false, 'error' => 'Data surat pemberitahuan tidak ditemukan'];
        }
        $pb = mysqli_fetch_assoc($qPemberitahuan);
        
        // Ambil data perusahaan
        $qPerusahaan = mysqli_query($conn, "SELECT * FROM perusahaan LIMIT 1");
        $perusahaan = mysqli_fetch_assoc($qPerusahaan);
        if(!$perusahaan) {
            $perusahaan = [
                'nama_perusahaan' => 'Rumah Sakit',
                'alamat' => '',
                'kota' => '',
                'logo' => '',
                'kontak' => '',
                'email' => ''
            ];
        }
        
        // Format tanggal
        $tanggal_indo = formatTanggalIndonesia($pb['tanggal_surat']);
        
        // Format waktu mulai dan selesai
        $waktu_mulai_indo = '';
        $waktu_selesai_indo = '';
        if(!empty($pb['waktu_mulai'])) {
            $waktu_mulai_indo = formatWaktuIndonesia($pb['waktu_mulai']);
        }
        if(!empty($pb['waktu_selesai'])) {
            $waktu_selesai_indo = formatWaktuIndonesia($pb['waktu_selesai']);
        }
        
        // ===================================================
        // TTE - OTOMATIS DARI PEMBUAT (yang login)
        // ===================================================
        $tte_pembuat = getTteByUser($conn, $pb['dibuat_oleh']);
        $qr_pembuat = $tte_pembuat ? qrTte($tte_pembuat['token']) : '';
        
        // ===================================================
        // BUILD HTML
        // ===================================================
        $html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
body { 
    font-family: "Times New Roman", Times, serif; 
    font-size: 12px; 
    margin: 0;
    padding: 30px 50px;
    line-height: 1.6;
}
.header {
    text-align: center;
    margin-bottom: 10px;
    padding-bottom: 10px;
}
.header img {
    max-width: 70px;
    max-height: 70px;
}
.header h2 {
    margin: 5px 0 0 0;
    font-size: 16px;
    font-weight: bold;
    color: #d4865b;
}
.header .address {
    font-size: 9px;
    margin-top: 2px;
}
.header-line {
    border-top: 3px solid #000;
    margin: 8px 0 2px 0;
}
.header-line-thin {
    border-top: 1px solid #000;
    margin: 0 0 20px 0;
}
.title {
    text-align: center;
    margin: 20px 0;
}
.title h3 {
    margin: 0;
    font-size: 14px;
    text-decoration: underline;
    font-weight: bold;
}
.nomor {
    text-align: center;
    margin-bottom: 5px;
}
.perihal {
    text-align: center;
    font-weight: bold;
    margin: 10px 0 20px 0;
}
.kategori-box {
    text-align: center;
    margin: 15px auto;
    padding: 8px;
    background-color: #f0f0f0;
    border: 1px solid #ccc;
    width: 200px;
    border-radius: 5px;
}
.content {
    text-align: justify;
    margin: 15px 0;
    line-height: 1.8;
}
.waktu-box {
    margin: 20px 0;
    padding: 15px;
    background-color: #fff9e6;
    border-left: 4px solid #ffc107;
}
.waktu-box .label {
    font-weight: bold;
    margin-bottom: 5px;
}
.signature {
    margin-top: 40px;
    text-align: center;
}
.signature-box {
    width: 250px;
    margin-left: auto;
    margin-right: 50px;
}
.signature-title {
    margin-bottom: 5px;
}
.signature-place {
    margin-bottom: 60px;
}
.signature-name {
    font-weight: bold;
    text-decoration: underline;
}
.signature-nik {
    font-size: 10px;
}
.signature-tte {
    font-size: 9px;
    color: #666;
    margin-top: 5px;
}
.qr-code {
    margin: 10px auto;
}
.qr-code img {
    width: 60px;
}
.footer-note {
    margin-top: 30px;
    padding-top: 15px;
    border-top: 1px solid #ccc;
    font-size: 10px;
    text-align: center;
    color: #666;
}
</style>
</head>
<body>';

        // HEADER
        $html .= '
<div class="header">';
        
        if (!empty($perusahaan['logo']) && file_exists($perusahaan['logo'])) {
            $html .= '<img src="' . $perusahaan['logo'] . '" alt="Logo">';
        }
        
        $html .= '
    <h2>RUMAH SAKIT<br>' . strtoupper(htmlspecialchars($perusahaan['nama_perusahaan'])) . '</h2>
    <div class="address">
        Jl. ' . htmlspecialchars($perusahaan['alamat']) . ', ' . htmlspecialchars($perusahaan['kota']) . '<br>';
        
        if (!empty($perusahaan['kontak'])) {
            $html .= '☎ ' . htmlspecialchars($perusahaan['kontak']) . ' ';
        }
        if (!empty($perusahaan['email'])) {
            $html .= '✉ ' . htmlspecialchars($perusahaan['email']);
        }
        
        $html .= '
    </div>
</div>
<div class="header-line"></div>
<div class="header-line-thin"></div>';

        // TITLE
        $html .= '
<div class="title">
    <h3>SURAT PEMBERITAHUAN</h3>
</div>

<div class="nomor">
    Nomor : ' . htmlspecialchars($pb['nomor_surat']) . '
</div>

<div class="kategori-box">
    <strong>Kategori: ' . strtoupper(htmlspecialchars($pb['kategori'])) . '</strong>
</div>

<div class="perihal">
    ' . strtoupper(htmlspecialchars($pb['perihal'])) . '
</div>';

        // ISI PEMBERITAHUAN
        $html .= '
<div class="content">
    ' . nl2br(htmlspecialchars($pb['isi_pemberitahuan'])) . '
</div>';

        // WAKTU MULAI & SELESAI (jika ada)
        if (!empty($waktu_mulai_indo) || !empty($waktu_selesai_indo)) {
            $html .= '
<div class="waktu-box">';
            
            if (!empty($waktu_mulai_indo)) {
                $html .= '
    <div class="label">Waktu Mulai:</div>
    <div>' . $waktu_mulai_indo . '</div>';
            }
            
            if (!empty($waktu_selesai_indo)) {
                $html .= '
    <div class="label" style="margin-top: 10px;">Estimasi Selesai:</div>
    <div>' . $waktu_selesai_indo . '</div>';
            }
            
            $html .= '
</div>';
        }

        // SIGNATURE (TTE OTOMATIS DARI PEMBUAT)
        $html .= '
<div class="signature">
    <div class="signature-box">
        <div class="signature-place">' . htmlspecialchars($perusahaan['kota']) . ', ' . $tanggal_indo . '</div>';
        
        if ($tte_pembuat) {
            // Ada TTE - Tampilkan QR Code
            $html .= '
        <div class="qr-code">
            <img src="' . $qr_pembuat . '" alt="QR TTE">
        </div>
        <div class="signature-name">' . htmlspecialchars($tte_pembuat['nama']) . '</div>
        <div class="signature-nik">NIK. ' . htmlspecialchars($tte_pembuat['nik']) . '</div>
        <div class="signature-tte">TTE: ' . date('d-m-Y H:i', strtotime($tte_pembuat['created_at'])) . '</div>';
        } else {
            // Tidak ada TTE - Tampilkan TTD Manual
            $html .= '
        <div style="margin-top: 80px;">
            <div class="signature-name">' . htmlspecialchars($pb['nama_pembuat'] ?? 'Pembuat Surat') . '</div>
            <div class="signature-nik">NIK. ' . htmlspecialchars($pb['nik_pembuat'] ?? '-') . '</div>
        </div>';
        }
        
        $html .= '
    </div>
</div>';

        // FOOTER NOTE
        $html .= '
<div class="footer-note">
    Surat pemberitahuan ini dibuat secara elektronik dan sah tanpa tanda tangan basah.<br>
    Untuk informasi lebih lanjut, hubungi bagian terkait di Rumah Sakit ' . htmlspecialchars($perusahaan['nama_perusahaan']) . '.
</div>';

        $html .= '
</body>
</html>';
        
        // ===================================================
        // GENERATE PDF (WAJIB remote enabled untuk QR/logo)
        // ===================================================
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        
        $pdf = new Dompdf($options);
        $pdf->loadHtml($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();
        
        // ===================================================
        // GET PDF OUTPUT & EMBED TTE TOKEN (dari pembuat)
        // ===================================================
        $pdf_output = $pdf->output();
        
        // Embed token di PDF stream (before %%EOF)
        if ($tte_pembuat) {
            $token_text = "\nTTE-TOKEN:" . $tte_pembuat['token'] . "\n";
            $pdf_output = str_replace('%%EOF', $token_text . '%%EOF', $pdf_output);
        }
        
        // ===================================================
        // SAVE PDF
        // ===================================================
        $output_dir = 'uploads/pemberitahuan/';
        if (!is_dir($output_dir)) {
            if (!mkdir($output_dir, 0755, true)) {
                return ['success' => false, 'error' => 'Gagal membuat folder ' . $output_dir];
            }
        }
        
        $filename = 'Surat_Pemberitahuan_' . date('YmdHis') . '_' . $pemberitahuan_id . '.pdf';
        $filepath = $output_dir . $filename;
        
        file_put_contents($filepath, $pdf_output);
        
        // ===================================================
        // LOG TTE (jika helper tersedia)
        // ===================================================
        if ($tte_pembuat && function_exists('generateFileHash') && function_exists('saveDocumentSigningLog')) {
            $file_hash = generateFileHash($filepath);
            
            if ($file_hash) {
                saveDocumentSigningLog($conn, $tte_pembuat['token'], $pb['dibuat_oleh'], $filename, $file_hash);
            }
        }
        
        return [
            'success' => true,
            'file_path' => $filepath
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Helper function: Format tanggal ke Bahasa Indonesia
 */
function formatTanggalIndonesia($tanggal) {
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    
    $split = explode('-', $tanggal);
    return $split[2] . ' ' . $bulan[(int)$split[1]] . ' ' . $split[0];
}

/**
 * Helper function: Format datetime ke Bahasa Indonesia
 */
function formatWaktuIndonesia($datetime) {
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    
    $timestamp = strtotime($datetime);
    $tgl = date('d', $timestamp);
    $bln = $bulan[(int)date('m', $timestamp)];
    $thn = date('Y', $timestamp);
    $jam = date('H:i', $timestamp);
    
    return $tgl . ' ' . $bln . ' ' . $thn . ' pukul ' . $jam . ' WIB';
}
?>