<?php
/**
 * File: generate_undangan_pdf.php
 * Fungsi untuk generate PDF Undangan Rapat dengan TTE OTOMATIS
 * TTE otomatis diambil dari penandatangan yang dipilih (bukan pembuat undangan)
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

// ===================================================
// MAIN FUNCTION
// ===================================================
function generateUndangan_PDF($undangan_id) {
    global $conn;
    
    try {
        // Ambil data undangan dengan info penandatangan (bukan pembuat)
        $qUnd = mysqli_query($conn, "
            SELECT u.* 
            FROM undangan_rapat u
            WHERE u.id = $undangan_id 
            LIMIT 1
        ");
        
        if(!$qUnd || mysqli_num_rows($qUnd) == 0) {
            return ['success' => false, 'error' => 'Data undangan tidak ditemukan'];
        }
        $undangan = mysqli_fetch_assoc($qUnd);
        
        // ===================================================
        // TTE - DARI PENANDATANGAN YANG DIPILIH (BUKAN PEMBUAT)
        // ===================================================
        $tte_penandatangan = getTteByUser($conn, $undangan['penandatangan_id']);
        $qr_penandatangan = $tte_penandatangan ? qrTte($tte_penandatangan['token']) : '';
        
        // ===================================================
        // DATA PERUSAHAAN
        // ===================================================
        $qPerusahaan = mysqli_query($conn, "SELECT * FROM perusahaan LIMIT 1");
        $perusahaan = mysqli_fetch_assoc($qPerusahaan);
        if(!$perusahaan) {
            $perusahaan = [
                'nama_perusahaan' => 'Rumah Sakit',
                'alamat' => '',
                'kota' => '',
                'logo' => '',
                'kontak' => '',
                'email' => '',
                'instagram' => ''
            ];
        }
        
        // Pastikan field instagram ada (untuk backward compatibility)
        if (!isset($perusahaan['instagram'])) {
            $perusahaan['instagram'] = '';
        }
        
        // Decode JSON data penerima
        $penerima = json_decode($undangan['penerima'], true) ?? [];
        
        // Format tanggal
        $tanggal_indo = formatTanggalIndonesia($undangan['tanggal_surat']);
        
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
    padding: 30px 40px;
    line-height: 1.6;
}
.header {
    position: relative;
    margin-bottom: 10px;
    padding-bottom: 10px;
    min-height: 100px;
}
.header-logo {
    position: absolute;
    left: 50%;
    top: 0;
    transform: translateX(-50%);
    width: 90px;
    text-align: center;
}
.header-logo img {
    max-width: 90px;
    max-height: 90px;
}
.header-content {
    text-align: center;
    margin: 100px auto 0;
}
.header-content h2 {
    margin: 0 0 5px 0;
    font-size: 18px;
    font-weight: bold;
    color: #d4865b;
    text-transform: uppercase;
}
.header-content .nama-rs {
    font-size: 20px;
    font-weight: bold;
    color: #d4865b;
    margin: 0 0 5px 0;
    text-transform: uppercase;
}
.header-content .address {
    font-size: 9px;
    margin-top: 3px;
    line-height: 1.4;
}
.header-content .contact-info {
    font-size: 9px;
    margin-top: 3px;
}
.header-content .social-media {
    font-size: 9px;
    margin-top: 2px;
    color: #333;
}
.header-line {
    border-top: 3px solid #000;
    margin: 8px 0 2px 0;
}
.header-line-thin {
    border-top: 1px solid #000;
    margin: 0 0 15px 0;
}
.nomor-surat {
    width: 100%;
    margin-bottom: 15px;
}
.nomor-surat table {
    width: 100%;
}
.nomor-surat td {
    padding: 2px 0;
}
.label-col {
    width: 100px;
}
.colon-col {
    width: 10px;
}
.kepada {
    margin: 15px 0;
}
.kepada-list {
    margin-left: 0;
    padding-left: 20px;
}
.kepada-list li {
    margin-bottom: 3px;
}
.tempat {
    margin: 15px 0 5px 35px;
}
.isi-surat {
    text-align: justify;
    margin: 10px 0;
    text-indent: 50px;
}
.detail-rapat {
    margin: 10px 0 10px 50px;
}
.detail-rapat table {
    width: 100%;
}
.detail-rapat td {
    padding: 3px 0;
    vertical-align: top;
}
.detail-label {
    width: 110px;
}
.penutup {
    text-align: justify;
    margin: 10px 0;
    text-indent: 50px;
}

/* TTD Section - CENTER (SEPERTI IZIN KELUAR) */
.ttd-section {
    margin-top: 40px;
    text-align: center;
}
.ttd-box {
    display: inline-block;
    text-align: center;
}
.ttd-title {
    font-size: 11px;
    margin-bottom: 10px;
}
.qr-code {
    margin: 10px 0;
}
.qr-code img {
    width: 100px;
}
.nama-ttd {
    font-weight: bold;
    text-decoration: underline;
    margin-top: 10px;
    font-size: 13px;
}
.nik-ttd {
    font-size: 11px;
    margin-top: 3px;
}
.waktu-tte {
    font-size: 9px;
    color: #666;
    margin-top: 5px;
}

/* Footer TTE */
.footer-tte {
    margin-top: 30px;
    padding-top: 10px;
    border-top: 1px solid #ccc;
    font-size: 9px;
    text-align: center;
    color: #666;
}
</style>
</head>
<body>';

        // ===================================================
        // HEADER
        // ===================================================
        $html .= '
<div class="header">';
        
        // Logo di tengah - konversi ke base64 untuk DOMPDF
        $logo_displayed = false;
        if (!empty($perusahaan['logo'])) {
            $logo_path = $perusahaan['logo'];
            
            // Coba beberapa kemungkinan path (images/logo/ sebagai prioritas)
            $possible_paths = [
                __DIR__ . '/images/logo/' . $logo_path,  // Path yang benar
                $logo_path,
                __DIR__ . '/' . $logo_path,
                $_SERVER['DOCUMENT_ROOT'] . '/' . $logo_path,
                __DIR__ . '/../' . $logo_path,
                __DIR__ . '/uploads/' . $logo_path,
                __DIR__ . '/../uploads/' . $logo_path,
            ];
            
            foreach ($possible_paths as $path) {
                if (file_exists($path)) {
                    $logo_type = pathinfo($path, PATHINFO_EXTENSION);
                    
                    // Map extension ke MIME type
                    $mime_types = [
                        'jpg' => 'jpeg',
                        'jpeg' => 'jpeg',
                        'png' => 'png',
                        'gif' => 'gif',
                        'svg' => 'svg+xml'
                    ];
                    
                    $mime = isset($mime_types[strtolower($logo_type)]) ? $mime_types[strtolower($logo_type)] : 'png';
                    
                    // Konversi logo ke base64
                    $logo_data = base64_encode(file_get_contents($path));
                    $logo_src = 'data:image/' . $mime . ';base64,' . $logo_data;
                    
                    $html .= '
    <div class="header-logo">
        <img src="' . $logo_src . '" alt="Logo">
    </div>';
                    
                    $logo_displayed = true;
                    break;
                }
            }
        }
        
        // Content di tengah
        $html .= '
    <div class="header-content">
        <div class="nama-rs">' . strtoupper(htmlspecialchars($perusahaan['nama_perusahaan'])) . '</div>
        <div class="address">
            ' . htmlspecialchars($perusahaan['alamat']) . '<br>
        </div>
        <div class="contact-info">';
        
        if (!empty($perusahaan['email'])) {
            $html .= htmlspecialchars($perusahaan['email']) . ' ';
        }
        if (!empty($perusahaan['kontak'])) {
            $html .= htmlspecialchars($perusahaan['kontak']);
        }
        
        $html .= '
        </div>';
        
        // Tampilkan Instagram jika ada
        if (!empty($perusahaan['instagram'])) {
            $html .= '
        <div class="social-media">' . htmlspecialchars($perusahaan['instagram']) . '</div>';
        }
        
        $html .= '
    </div>
</div>
<div class="header-line"></div>
<div class="header-line-thin"></div>';

        // ===================================================
        // NOMOR SURAT
        // ===================================================
        $html .= '
<div class="nomor-surat">
    <table>
        <tr>
            <td class="label-col">Nomor</td>
            <td class="colon-col">:</td>
            <td>' . htmlspecialchars($undangan['nomor_surat']) . '</td>
            <td style="text-align: right; width: 200px;">' . htmlspecialchars($perusahaan['kota']) . ', ' . $tanggal_indo . '</td>
        </tr>
        <tr>
            <td class="label-col">Lampiran</td>
            <td class="colon-col">:</td>
            <td>-</td>
            <td></td>
        </tr>
        <tr>
            <td class="label-col">Perihal</td>
            <td class="colon-col">:</td>
            <td><strong>' . htmlspecialchars($undangan['perihal']) . '</strong></td>
            <td></td>
        </tr>
    </table>
</div>';

        // ===================================================
        // KEPADA YTH
        // ===================================================
        $html .= '
<div class="kepada">
    <p style="margin: 5px 0;">Kepada Yth;</p>
    <ol class="kepada-list">';
        
        foreach ($penerima as $p) {
            $html .= '<li><strong>' . htmlspecialchars($p) . '</strong></li>';
        }
        
        $html .= '
    </ol>
    <p style="margin: 5px 0 0 0;">di-</p>
</div>

<div class="tempat">
    <p style="margin: 0;"><u>Tempat</u></p>
</div>';

        // ===================================================
        // ISI SURAT
        // ===================================================
        $html .= '
<div class="isi-surat">
    Sehubungan dengan akan diadakannya rapat koordinasi bulanan, Bersama ini kami mengundang Bapak/Ibu untuk hadir pada rapat tersebut yang akan dilaksanakan pada:
</div>

<div class="detail-rapat">
    <table>
        <tr>
            <td class="detail-label">Hari, Tanggal</td>
            <td>: ' . htmlspecialchars($undangan['hari_tanggal']) . '</td>
        </tr>
        <tr>
            <td class="detail-label">Waktu</td>
            <td>: ' . htmlspecialchars($undangan['waktu']) . '</td>
        </tr>
        <tr>
            <td class="detail-label">Tempat</td>
            <td>: ' . htmlspecialchars($undangan['tempat']) . '</td>
        </tr>
        <tr>
            <td class="detail-label">Agenda</td>
            <td>: ' . nl2br(htmlspecialchars($undangan['agenda'])) . '</td>
        </tr>
    </table>
</div>

<div class="penutup">
    Demikian undangan ini kami sampaikan, mohon untuk hadir tepat waktu, terima kasih.
</div>';

        // ===================================================
        // TTD SECTION - CENTER (1 TTD SAJA - PENANDATANGAN YANG DIPILIH)
        // ===================================================
        $html .= '
<div class="ttd-section">
    <div class="ttd-box">';
        
        if ($tte_penandatangan) {
            // Ada TTE - Tampilkan QR Code
            $html .= '
        <div class="qr-code">
            <img src="' . $qr_penandatangan . '" alt="QR TTE">
        </div>
        <div class="nama-ttd">' . htmlspecialchars($tte_penandatangan['nama']) . '</div>
        <div class="nik-ttd">NIK. ' . htmlspecialchars($tte_penandatangan['nik']) . '</div>
        <div class="waktu-tte">TTE: ' . date('d-m-Y H:i', strtotime($tte_penandatangan['created_at'])) . '</div>';
        } else {
            // Tidak ada TTE - Tampilkan TTD Manual (data dari snapshot di database)
            $html .= '
        <div style="margin-top: 80px;">
            <div class="nama-ttd">' . htmlspecialchars($undangan['penandatangan_nama'] ?? 'Penandatangan') . '</div>
            <div class="nik-ttd">NIK. ' . htmlspecialchars($undangan['penandatangan_nik'] ?? '-') . '</div>
        </div>';
        }
        
        $html .= '
    </div>
</div>';

        // ===================================================
        // FOOTER TTE
        // ===================================================
        $html .= '
<div class="footer-tte">
    Dokumen ini ditandatangani secara elektronik menggunakan<br>
    <strong>FixPoint – Smart Office Management System</strong><br>
    TTE Non Sertifikasi | Scan QR Code untuk verifikasi
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
        // GET PDF OUTPUT & EMBED TOKEN
        // ===================================================
        $pdf_output = $pdf->output();
        
        // Embed token di PDF stream (before %%EOF) - gunakan token penandatangan
        if ($tte_penandatangan) {
            $token_text = "\nTTE-TOKEN:" . $tte_penandatangan['token'] . "\n";
            $pdf_output = str_replace('%%EOF', $token_text . '%%EOF', $pdf_output);
        }
        
        // ===================================================
        // SAVE PDF
        // ===================================================
        $output_dir = 'uploads/undangan/';
        if (!is_dir($output_dir)) {
            if (!mkdir($output_dir, 0755, true)) {
                return ['success' => false, 'error' => 'Gagal membuat folder ' . $output_dir];
            }
        }
        
        $filename = 'Undangan_Rapat_' . date('YmdHis') . '_' . $undangan_id . '.pdf';
        $filepath = $output_dir . $filename;
        
        file_put_contents($filepath, $pdf_output);
        
        // ===================================================
        // LOG TTE (jika helper tersedia) - log atas nama penandatangan
        // ===================================================
        if ($tte_penandatangan && function_exists('generateFileHash') && function_exists('saveDocumentSigningLog')) {
            $file_hash = generateFileHash($filepath);
            
            if ($file_hash) {
                saveDocumentSigningLog($conn, $tte_penandatangan['token'], $undangan['penandatangan_id'], $filename, $file_hash);
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

// ===================================================
// DIRECT CALL (untuk testing)
// ===================================================
if (isset($_GET['id'])) {
    include 'koneksi.php';
    
    $id = intval($_GET['id']);
    $result = generateUndangan_PDF($id);
    
    if ($result['success']) {
        // Stream PDF to browser
        $pdf_content = file_get_contents($result['file_path']);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="undangan_rapat.pdf"');
        header('Content-Length: ' . strlen($pdf_content));
        echo $pdf_content;
        exit;
    } else {
        die('Error: ' . $result['error']);
    }
}
?>