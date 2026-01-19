<?php
/**
 * File: generate_spo_pdf.php
 * Fungsi untuk generate PDF SPO menggunakan DOMPDF
 * 
 * MODIFIED: Format persis seperti contoh SPO Rumah Sakit Permata Hati
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

function generateSPO_PDF($spo_id) {
    global $conn;
    
    try {
        // Ambil data SPO
        $qSPO = mysqli_query($conn, "SELECT * FROM spo WHERE id = $spo_id LIMIT 1");
        if(!$qSPO || mysqli_num_rows($qSPO) == 0) {
            return ['success' => false, 'error' => 'Data SPO tidak ditemukan'];
        }
        $spo = mysqli_fetch_assoc($qSPO);
        
        // Ambil data perusahaan
        $qPerusahaan = mysqli_query($conn, "SELECT * FROM perusahaan LIMIT 1");
        $perusahaan = mysqli_fetch_assoc($qPerusahaan);
        if(!$perusahaan) {
            $perusahaan = [
                'nama_perusahaan' => 'Rumah Sakit',
                'alamat' => '',
                'kota' => '',
                'logo' => ''
            ];
        }
        
        // Decode JSON data
        $tujuan = json_decode($spo['tujuan'], true) ?? [];
        $kebijakan = json_decode($spo['kebijakan'], true) ?? [];
        $prosedur = json_decode($spo['prosedur'], true) ?? [];
        $unit_terkait = json_decode($spo['unit_terkait'], true) ?? [];
        
        // Generate HTML berdasarkan template
        switch($spo['template_id']) {
            case 1:
                $html = generateTemplate_FormalBox_HTML($spo, $perusahaan, $tujuan, $kebijakan, $prosedur, $unit_terkait);
                break;
            case 2:
                $html = generateTemplate_Modern_HTML($spo, $perusahaan, $tujuan, $kebijakan, $prosedur, $unit_terkait);
                break;
            case 3:
                $html = generateTemplate_Compact_HTML($spo, $perusahaan, $tujuan, $kebijakan, $prosedur, $unit_terkait);
                break;
            default:
                $html = generateTemplate_FormalBox_HTML($spo, $perusahaan, $tujuan, $kebijakan, $prosedur, $unit_terkait);
        }
        
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
        // GET PDF OUTPUT & EMBED TTE TOKEN (jika ada)
        // ===================================================
        $pdf_output = $pdf->output();
        
        // Embed TTE token di PDF stream (before %%EOF)
        if (!empty($spo['tte_token'])) {
            $token_text = "\nTTE-TOKEN:" . $spo['tte_token'] . "\n";
            $pdf_output = str_replace('%%EOF', $token_text . '%%EOF', $pdf_output);
        }
        
        // ===================================================
        // SAVE PDF
        // ===================================================
        $output_dir = 'uploads/spo/';
        if (!is_dir($output_dir)) {
            if (!mkdir($output_dir, 0755, true)) {
                return ['success' => false, 'error' => 'Gagal membuat folder ' . $output_dir];
            }
        }
        
        $filename = 'SPO_' . date('YmdHis') . '_' . $spo_id . '.pdf';
        $filepath = $output_dir . $filename;
        
        file_put_contents($filepath, $pdf_output);
        
        // Get total pages (estimate - DOMPDF tidak punya method langsung)
        $total_pages = substr_count($pdf_output, '/Type /Page');
        if($total_pages == 0) $total_pages = 1;
        
        return [
            'success' => true,
            'file_path' => $filepath,
            'total_pages' => $total_pages
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Template 1: Formal Box Style - PERSIS SEPERTI CONTOH SPO RUMAH SAKIT
 */
function generateTemplate_FormalBox_HTML($spo, $perusahaan, $tujuan, $kebijakan, $prosedur, $unit_terkait) {
    
    $tanggal_indo = formatTanggalIndonesia($spo['tanggal_terbit']);
    
    // QR Code untuk TTE (jika ada)
    $qr_code = '';
    if (!empty($spo['tte_token'])) {
        $url = "http://" . $_SERVER['HTTP_HOST'] . "/cek_tte_spo.php?token=" . $spo['tte_token'];
        $qr_code = '<img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=' . urlencode($url) . '" style="width: 80px; height: 80px; margin: 5px 0;">';
    }
    
    // Build HTML - EXACT MATCH dengan contoh
    $html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}
body { 
    font-family: "Times New Roman", Times, serif; 
    font-size: 11pt; 
    line-height: 1.4;
    padding: 15mm;
}
.main-table {
    width: 100%;
    border: 2px solid #000;
    border-collapse: collapse;
}
.main-table td {
    border: 1px solid #000;
    padding: 8px;
    vertical-align: top;
}
.logo-cell {
    width: 100px;
    text-align: center;
    vertical-align: middle;
}
.header-right {
    text-align: center;
    font-weight: bold;
}
.header-right .title {
    font-size: 12pt;
    margin-bottom: 3px;
}
.header-right .subtitle {
    font-size: 11pt;
}
.doc-info {
    text-align: center;
    font-size: 10pt;
}
.left-column {
    width: 100px;
    background-color: #f5f5f5;
    text-align: center;
    font-weight: bold;
    font-size: 9pt;
}
.content-cell {
    padding: 10px;
    text-align: justify;
}
.content-cell p {
    margin-bottom: 8px;
}
.content-cell ol,
.content-cell ul {
    margin-left: 20px;
    margin-top: 5px;
}
.content-cell li {
    margin-bottom: 6px;
}
.content-cell ul {
    list-style-type: none;
    padding-left: 0;
}
.content-cell ul li:before {
    content: "-";
    margin-right: 8px;
    margin-left: 20px;
}
.signature-section {
    text-align: center;
    padding: 15px 10px;
}
.signature-section .label {
    font-weight: normal;
    margin-bottom: 3px;
}
.signature-section .name {
    font-weight: bold;
    text-decoration: underline;
    margin-top: 5px;
}
.signature-section .nik {
    font-size: 10pt;
    margin-top: 2px;
}
</style>
</head>
<body>

<table class="main-table">
    <!-- HEADER ROW -->
    <tr>';
    
    // Logo Cell (rowspan 2)
    if (!empty($perusahaan['logo']) && file_exists($perusahaan['logo'])) {
        $html .= '
        <td class="logo-cell" rowspan="2">
            <img src="' . $perusahaan['logo'] . '" style="max-width: 90px; max-height: 90px;">
        </td>';
    } else {
        $html .= '
        <td class="logo-cell" rowspan="2">
            <div style="width: 90px; height: 90px; border: 1px solid #ccc; display: flex; align-items: center; justify-content: center; font-size: 8pt; color: #999;">LOGO</div>
        </td>';
    }
    
    $html .= '
        <td class="header-right" colspan="3">
            <div class="title">STANDAR OPERASIONAL PROSEDUR (SPO)</div>
            <div class="subtitle">' . strtoupper(htmlspecialchars($spo['judul'])) . '</div>
        </td>
    </tr>
    
    <!-- DOC INFO ROW -->
    <tr>
        <td class="doc-info">No. Dokumen<br>' . htmlspecialchars($spo['no_dokumen']) . '</td>
        <td class="doc-info">No. Revisi<br>' . htmlspecialchars($spo['no_revisi']) . '</td>
        <td class="doc-info">Halaman<br>1/' . ($spo['halaman_total'] ?: '2') . '</td>
    </tr>
    
    <!-- STANDAR OPERASIONAL PROSEDUR (VERTICAL TEXT) & TANGGAL TERBIT -->
    <tr>
        <td class="left-column" rowspan="6">
            <div style="writing-mode: vertical-rl; text-orientation: upright; font-size: 9pt; letter-spacing: 2px; padding: 15px 0;">
                STANDAR OPERASIONAL PROSEDUR
            </div>
        </td>
        <td style="width: 50%; padding: 10px;">
            <strong>Tanggal Terbit</strong>
        </td>
        <td colspan="2" class="signature-section">
            <div class="label">Ditetapkan :</div>
            <div style="margin: 3px 0;">' . htmlspecialchars($spo['penandatangan_jabatan']) . '</div>
            ' . $qr_code . '
            <div class="name">' . htmlspecialchars($spo['penandatangan_nama']) . '</div>
            <div class="nik">NIK : ' . htmlspecialchars($spo['penandatangan_nik']) . '</div>
        </td>
    </tr>
    
    <!-- PENGERTIAN -->
    <tr>
        <td class="left-column">PENGERTIAN</td>
        <td class="content-cell" colspan="2">' . nl2br(htmlspecialchars($spo['pengertian'])) . '</td>
    </tr>
    
    <!-- TUJUAN -->
    <tr>
        <td class="left-column">TUJUAN</td>
        <td class="content-cell" colspan="2">
            <ol>';
    
    foreach ($tujuan as $tj) {
        $html .= '<li>' . htmlspecialchars($tj) . '</li>';
    }
    
    $html .= '
            </ol>
        </td>
    </tr>
    
    <!-- KEBIJAKAN -->
    <tr>
        <td class="left-column">KEBIJAKAN</td>
        <td class="content-cell" colspan="2">
            <ul>';
    
    foreach ($kebijakan as $kb) {
        $html .= '<li>' . htmlspecialchars($kb) . '</li>';
    }
    
    $html .= '
            </ul>
        </td>
    </tr>
    
    <!-- PROSEDUR -->
    <tr>
        <td class="left-column">PROSEDUR</td>
        <td class="content-cell" colspan="2">
            <ol>';
    
    foreach ($prosedur as $pr) {
        $html .= '<li>' . nl2br(htmlspecialchars($pr)) . '</li>';
    }
    
    $html .= '
            </ol>
        </td>
    </tr>
    
    <!-- UNIT TERKAIT -->
    <tr>
        <td class="left-column">UNIT TERKAIT</td>
        <td class="content-cell" colspan="2">
            <ol>';
    
    foreach ($unit_terkait as $ut) {
        $html .= '<li>' . htmlspecialchars($ut) . '</li>';
    }
    
    $html .= '
            </ol>
        </td>
    </tr>
</table>

</body>
</html>';
    
    return $html;
}

/**
 * Template 2: Modern Clean Style
 */
function generateTemplate_Modern_HTML($spo, $perusahaan, $tujuan, $kebijakan, $prosedur, $unit_terkait) {
    
    $tanggal_indo = formatTanggalIndonesia($spo['tanggal_terbit']);
    
    $html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
body { 
    font-family: Helvetica, Arial, sans-serif; 
    font-size: 10px;
    margin: 0;
    padding: 20px;
    line-height: 1.4;
}
.header {
    border-bottom: 3px solid #2c3e50;
    padding-bottom: 10px;
    margin-bottom: 15px;
}
.header h1 {
    margin: 0;
    font-size: 14px;
    color: #2c3e50;
}
.header .company {
    font-size: 10px;
    color: #666;
    margin-top: 3px;
}
.doc-info {
    font-size: 9px;
    color: #555;
    margin-bottom: 15px;
    padding-bottom: 8px;
    border-bottom: 1px solid #ddd;
}
.title {
    text-align: center;
    font-size: 13px;
    font-weight: bold;
    margin: 15px 0;
    color: #1f4fd8;
}
.section {
    margin-bottom: 12px;
}
.section-title {
    font-size: 11px;
    font-weight: bold;
    color: #2c3e50;
    margin-bottom: 6px;
}
.section-content {
    text-align: justify;
}
</style>
</head>
<body>

<div class="header">
    <h1>STANDAR OPERASIONAL PROSEDUR</h1>
    <div class="company">' . strtoupper(htmlspecialchars($perusahaan['nama_perusahaan'])) . '</div>
</div>

<div class="doc-info">
    <strong>No. Dok:</strong> ' . htmlspecialchars($spo['no_dokumen']) . ' | 
    <strong>Rev:</strong> ' . htmlspecialchars($spo['no_revisi']) . ' | 
    <strong>Hal:</strong> 1/' . ($spo['halaman_total'] ?: '1') . '<br>
    <strong>Tanggal Terbit:</strong> ' . $tanggal_indo . '<br>
    <strong>Ditetapkan oleh:</strong> ' . htmlspecialchars($spo['penandatangan_nama']) . ' (' . htmlspecialchars($spo['penandatangan_jabatan']) . ')
</div>

<div class="title">' . strtoupper(htmlspecialchars($spo['judul'])) . '</div>

<div class="section">
    <div class="section-title">PENGERTIAN</div>
    <div class="section-content">' . nl2br(htmlspecialchars($spo['pengertian'])) . '</div>
</div>

<div class="section">
    <div class="section-title">TUJUAN</div>
    <div class="section-content">
        <ol style="margin: 0; padding-left: 18px;">';
    
    foreach ($tujuan as $tj) {
        $html .= '<li style="margin-bottom: 4px;">' . htmlspecialchars($tj) . '</li>';
    }
    
    $html .= '</ol>
    </div>
</div>

<div class="section">
    <div class="section-title">KEBIJAKAN</div>
    <div class="section-content">
        <ul style="margin: 0; padding-left: 18px;">';
    
    foreach ($kebijakan as $kb) {
        $html .= '<li style="margin-bottom: 4px;">' . htmlspecialchars($kb) . '</li>';
    }
    
    $html .= '</ul>
    </div>
</div>

<div class="section">
    <div class="section-title">PROSEDUR</div>
    <div class="section-content">
        <ol style="margin: 0; padding-left: 18px;">';
    
    foreach ($prosedur as $pr) {
        $html .= '<li style="margin-bottom: 4px;">' . nl2br(htmlspecialchars($pr)) . '</li>';
    }
    
    $html .= '</ol>
    </div>
</div>

<div class="section">
    <div class="section-title">UNIT TERKAIT</div>
    <div class="section-content">
        <ol style="margin: 0; padding-left: 18px;">';
    
    foreach ($unit_terkait as $ut) {
        $html .= '<li>' . htmlspecialchars($ut) . '</li>';
    }
    
    $html .= '</ol>
    </div>
</div>

</body>
</html>';
    
    return $html;
}

/**
 * Template 3: Compact Style
 */
function generateTemplate_Compact_HTML($spo, $perusahaan, $tujuan, $kebijakan, $prosedur, $unit_terkait) {
    
    $tanggal_indo = formatTanggalIndonesia($spo['tanggal_terbit']);
    
    $html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
body { 
    font-family: Arial, sans-serif; 
    font-size: 9px;
    margin: 0;
    padding: 15px;
    line-height: 1.3;
}
.header {
    font-size: 9px;
    margin-bottom: 10px;
    padding-bottom: 5px;
    border-bottom: 1px solid #000;
}
.title {
    font-size: 11px;
    font-weight: bold;
    margin: 8px 0;
}
.label {
    font-weight: bold;
    font-size: 9px;
}
</style>
</head>
<body>

<div class="header">
    <table style="width: 100%;">
    <tr>
        <td>' . strtoupper(htmlspecialchars($perusahaan['nama_perusahaan'])) . '<br>STANDAR OPERASIONAL PROSEDUR</td>
        <td style="text-align: right;">No: ' . htmlspecialchars($spo['no_dokumen']) . '<br>Rev: ' . htmlspecialchars($spo['no_revisi']) . '</td>
    </tr>
    </table>
</div>

<div class="title">' . strtoupper(htmlspecialchars($spo['judul'])) . '</div>

<div><span class="label">Pengertian:</span> ' . htmlspecialchars($spo['pengertian']) . '</div><br>

<div><span class="label">Tujuan:</span><br>';
    
    foreach ($tujuan as $i => $tj) {
        $html .= ($i + 1) . '. ' . htmlspecialchars($tj) . '<br>';
    }
    
    $html .= '</div><br>

<div><span class="label">Kebijakan:</span><br>';
    
    foreach ($kebijakan as $kb) {
        $html .= '- ' . htmlspecialchars($kb) . '<br>';
    }
    
    $html .= '</div><br>

<div><span class="label">Prosedur:</span><br>';
    
    foreach ($prosedur as $i => $pr) {
        $html .= ($i + 1) . '. ' . nl2br(htmlspecialchars($pr)) . '<br>';
    }
    
    $html .= '</div><br>

<div><span class="label">Unit Terkait:</span> ' . implode(', ', array_map('htmlspecialchars', $unit_terkait)) . '</div><br>

<div style="font-size: 8px; margin-top: 10px;">
    Ditetapkan: ' . $tanggal_indo . ' | ' . htmlspecialchars($spo['penandatangan_nama']) . ' (' . htmlspecialchars($spo['penandatangan_jabatan']) . ')
</div>

</body>
</html>';
    
    return $html;
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
?>