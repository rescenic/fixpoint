<?php
// ==========================================
// SETUP DASAR
// ==========================================
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Jakarta');

include 'koneksi.php';

// ==========================================
// HELPER: DOWNLOAD QR (AMAN UNTUK FPDF)
// ==========================================
function downloadQr($url, $savePath)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 15
    ]);
    $data = curl_exec($ch);
    curl_close($ch);

    if ($data) {
        file_put_contents($savePath, $data);
        return file_exists($savePath);
    }
    return false;
}

// ==========================================
// AUTOLOAD FPDF + FPDI
// ==========================================
require __DIR__ . '/lib/autoload.php';
use setasign\Fpdi\Fpdi;

// ==========================================
// PARAMETER
// ==========================================
$id   = intval($_GET['id'] ?? 0);
$mode = $_GET['mode'] ?? 'view';
if ($id === 0) die('ID surat tidak valid');

// ==========================================
// DATA SURAT
// ==========================================
$qSurat = mysqli_query($conn, "SELECT * FROM surat_masuk WHERE id='$id'");
$surat  = mysqli_fetch_assoc($qSurat);
if (!$surat) die('Data surat tidak ditemukan');

// ==========================================
// PATH FILE PDF ASLI
// ==========================================
$rootPath = realpath(__DIR__ . '/..');
$file_pdf = $rootPath . '/dist/uploads/' . $surat['file_surat'];
if (!file_exists($file_pdf)) die('File surat tidak ditemukan');

// ==========================================
// DATA DISPOSISI + TTE TERBARU
// ==========================================
$qDisp = mysqli_query($conn, "
    SELECT d.*,
           u.nama AS nama_user,
           u.jabatan AS jabatan_user,
           t.nama AS nama_tte,
           t.jabatan AS jabatan_tte,
           t.token
    FROM disposisi d
    JOIN users u ON d.disposisi_oleh = u.id
    LEFT JOIN tte_user t 
        ON t.user_id = u.id AND t.status='aktif'
    WHERE d.surat_masuk_id = '$id'
    ORDER BY d.tanggal_disposisi DESC
    LIMIT 1
");
$disp = mysqli_fetch_assoc($qDisp);

// Default jika belum ada disposisi
if (!$disp) {
    $disp = [
        'tanggal_disposisi' => null,
        'instruksi' => 'BELUM ADA DISPOSISI',
        'catatan' => '-',
        'nama_user' => '-',
        'jabatan_user' => '-',
        'nama_tte' => null,
        'jabatan_tte' => null,
        'token' => null
    ];
}

// ==========================================
// GENERATE QR TTE KE TEMP
// ==========================================
$qrFile = null;
if (!empty($disp['token'])) {
    $verifyUrl = "http://" . $_SERVER['HTTP_HOST'] . "/verify_tte.php?token=" . $disp['token'];
    $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($verifyUrl);

    $tmpQr = sys_get_temp_dir() . '/qr_' . md5($disp['token']) . '.png';
    if (downloadQr($qrUrl, $tmpQr)) {
        $qrFile = $tmpQr;
    }
}

// ==========================================
// LOAD PDF ASLI
// ==========================================
$pdf = new Fpdi();
$pageCount = $pdf->setSourceFile($file_pdf);

// ===== CETAK SEMUA HALAMAN SURAT ASLI =====
for ($i = 1; $i <= $pageCount; $i++) {
    $tpl = $pdf->importPage($i);
    $size = $pdf->getTemplateSize($tpl);

    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
    $pdf->useTemplate($tpl);
}

// ==========================================
// HALAMAN BARU → STEMPEL DISPOSISI
// ==========================================
$pdf->AddPage('P', 'A4');

// POSISI STEMPEL (KANAN ATAS)
$stampWidth = 120;
$stampX = 210 - $stampWidth - 15;
$stampY = 25;

$pdf->SetXY($stampX, $stampY);

// ===== HEADER =====
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell($stampWidth, 9, 'DISPOSISI', 1, 1, 'C');

// ===== META =====
$pdf->SetFont('Arial', '', 9);

$pdf->SetX($stampX);
$pdf->Cell(30, 7, 'Tanggal', 1);
$pdf->Cell($stampWidth - 30, 7,
    $disp['tanggal_disposisi']
        ? date('d-m-Y H:i', strtotime($disp['tanggal_disposisi']))
        : '-', 1, 1);

$pdf->SetX($stampX);
$pdf->Cell(30, 7, 'Pejabat', 1);
$pdf->Cell($stampWidth - 30, 7, $disp['nama_user'], 1, 1);

$pdf->SetX($stampX);
$pdf->Cell(30, 7, 'Jabatan', 1);
$pdf->Cell($stampWidth - 30, 7, $disp['jabatan_user'], 1, 1);

// ===== INSTRUKSI =====
$pdf->SetX($stampX);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell($stampWidth, 7, 'INSTRUKSI', 1, 1);

$pdf->SetFont('Arial', '', 9);
$pdf->SetX($stampX);
$pdf->MultiCell($stampWidth, 6, trim($disp['instruksi']), 1);

// ===== CATATAN =====
$pdf->SetX($stampX);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell($stampWidth, 7, 'CATATAN', 1, 1);

$pdf->SetFont('Arial', '', 9);
$pdf->SetX($stampX);
$pdf->MultiCell($stampWidth, 6, $disp['catatan'] ?: '-', 1);

// ===== TTE =====
$tteY = $pdf->GetY();
$pdf->SetX($stampX);
$pdf->Cell(40, 30, '', 1);
$pdf->Cell($stampWidth - 40, 30, '', 1, 1);

if ($qrFile && file_exists($qrFile)) {
    $pdf->Image($qrFile, $stampX + 5, $tteY + 4, 28);
}

$pdf->SetXY($stampX + 45, $tteY + 6);
$pdf->SetFont('Arial', 'I', 8);
$pdf->MultiCell(
    $stampWidth - 48,
    5,
    "Ditandatangani secara elektronik oleh:\n" .
    ($disp['nama_tte'] ?: $disp['nama_user']) . "\n" .
    ($disp['jabatan_tte'] ?: $disp['jabatan_user']) . "\n" .
    (!empty($disp['token']) ? 'TTE Valid' : 'Tanpa TTE')
);

// ==========================================
// OUTPUT
// ==========================================
if (ob_get_length()) ob_clean();
$pdf->Output($mode === 'print' ? 'D' : 'I', "surat_disposisi_$id.pdf");

// ==========================================
// CLEANUP
// ==========================================
if ($qrFile && file_exists($qrFile)) {
    unlink($qrFile);
}
