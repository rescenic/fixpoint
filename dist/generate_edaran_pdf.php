<?php
/**
 * File: generate_edaran_pdf.php
 * Fungsi untuk generate PDF Surat Edaran menggunakan DOMPDF
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

function generateEdaran_PDF($edaran_id) {
    global $conn;
    
    try {
        // Ambil data surat edaran
        $qEdaran = mysqli_query($conn, "SELECT * FROM surat_edaran WHERE id = $edaran_id LIMIT 1");
        if(!$qEdaran || mysqli_num_rows($qEdaran) == 0) {
            return ['success' => false, 'error' => 'Data surat edaran tidak ditemukan'];
        }
        $edaran = mysqli_fetch_assoc($qEdaran);
        
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
        
        // Decode JSON data
        $isi_poin = json_decode($edaran['isi_poin'], true) ?? [];
        $tembusan = json_decode($edaran['tembusan'], true) ?? [];
        
        // Format tanggal
        $tanggal_indo = formatTanggalIndonesia($edaran['tanggal_surat']);
        $tanggal_berlaku_indo = '';
        if(!empty($edaran['tanggal_berlaku'])) {
            $tanggal_berlaku_indo = formatTanggalIndonesia($edaran['tanggal_berlaku']);
        }
        
        // Generate QR Code untuk TTE (jika ada)
        $qr_code = '';
        if (!empty($edaran['tte_token'])) {
            $url = "http://" . $_SERVER['HTTP_HOST'] . "/cek_tte_edaran.php?token=" . $edaran['tte_token'];
            $qr_code = 'https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=' . urlencode($url);
        }
        
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
.content {
    text-align: justify;
    margin: 15px 0;
}
.content-indent {
    text-align: justify;
    margin: 10px 0;
    text-indent: 50px;
}
.poin-list {
    margin: 15px 0 15px 20px;
}
.poin-item {
    margin-bottom: 10px;
    text-align: justify;
}
.tanggal-berlaku {
    margin: 15px 0;
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
.qr-code {
    margin: 10px auto;
}
.qr-code img {
    width: 60px;
}
.tembusan {
    margin-top: 30px;
}
.tembusan-title {
    margin-bottom: 5px;
}
.tembusan-list {
    margin-left: 0;
    padding-left: 20px;
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
            $html .= '☎' . htmlspecialchars($perusahaan['kontak']) . ' ';
        }
        if (!empty($perusahaan['email'])) {
            $html .= '✉' . htmlspecialchars($perusahaan['email']);
        }
        
        $html .= '
    </div>
</div>
<div class="header-line"></div>
<div class="header-line-thin"></div>';

        // TITLE
        $html .= '
<div class="title">
    <h3>SURAT EDARAN</h3>
</div>

<div class="nomor">
    Nomor : ' . htmlspecialchars($edaran['nomor_surat']) . '
</div>

<div class="perihal">
    Tentang<br>
    ' . strtoupper(htmlspecialchars($edaran['perihal'])) . '
</div>';

        // PEMBUKAAN
        $html .= '
<div class="content-indent">
    ' . nl2br(htmlspecialchars($edaran['pembukaan'])) . '
</div>';

        // ISI POIN
        $html .= '
<div class="poin-list">
    <ol>';
        
        foreach ($isi_poin as $poin) {
            $html .= '
        <li class="poin-item">' . nl2br(htmlspecialchars($poin)) . '</li>';
        }
        
        $html .= '
    </ol>
</div>';

        // TANGGAL BERLAKU (jika ada)
        if (!empty($tanggal_berlaku_indo)) {
            $html .= '
<div class="tanggal-berlaku">
    Ketentuan ini berlaku efektif per tanggal ' . $tanggal_berlaku_indo . '.
</div>';
        }

        // PENUTUP
        $html .= '
<div class="content-indent">
    ' . nl2br(htmlspecialchars($edaran['penutup'])) . '
</div>';

        // SIGNATURE
        $html .= '
<div class="signature">
    <div class="signature-box">
        <div class="signature-place">' . htmlspecialchars($perusahaan['kota']) . ', ' . $tanggal_indo . '</div>
        <div class="signature-title">Rumah Sakit ' . htmlspecialchars($perusahaan['nama_perusahaan']) . '</div>
        <div class="signature-title">' . htmlspecialchars($edaran['penandatangan_jabatan']) . '</div>';
        
        if (!empty($qr_code)) {
            $html .= '
        <div class="qr-code">
            <img src="' . $qr_code . '" alt="QR Code TTE">
        </div>';
        } else {
            $html .= '<div style="height: 50px;"></div>';
        }
        
        $html .= '
        <div class="signature-name">' . htmlspecialchars($edaran['penandatangan_nama']) . '</div>
    </div>
</div>';

        // TEMBUSAN (jika ada)
        if (!empty($tembusan) && count($tembusan) > 0) {
            $html .= '
<div class="tembusan">
    <div class="tembusan-title">Tembusan:</div>
    <ol class="tembusan-list">';
            
            foreach ($tembusan as $tmb) {
                $html .= '<li>' . htmlspecialchars($tmb) . '</li>';
            }
            
            $html .= '
    </ol>
</div>';
        }

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
        // GET PDF OUTPUT & EMBED TTE TOKEN (jika ada)
        // ===================================================
        $pdf_output = $pdf->output();
        
        // Embed TTE token di PDF stream (before %%EOF)
        if (!empty($edaran['tte_token'])) {
            $token_text = "\nTTE-TOKEN:" . $edaran['tte_token'] . "\n";
            $pdf_output = str_replace('%%EOF', $token_text . '%%EOF', $pdf_output);
        }
        
        // ===================================================
        // SAVE PDF
        // ===================================================
        $output_dir = 'uploads/edaran/';
        if (!is_dir($output_dir)) {
            if (!mkdir($output_dir, 0755, true)) {
                return ['success' => false, 'error' => 'Gagal membuat folder ' . $output_dir];
            }
        }
        
        $filename = 'Surat_Edaran_' . date('YmdHis') . '_' . $edaran_id . '.pdf';
        $filepath = $output_dir . $filename;
        
        file_put_contents($filepath, $pdf_output);
        
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
?>