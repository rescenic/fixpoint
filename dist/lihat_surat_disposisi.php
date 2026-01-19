<?php
// ==========================================
// SETUP DASAR
// ==========================================
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Jakarta');

include 'koneksi.php';
require_once __DIR__ . '/tte_hash_helper.php';

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
// FUNCTION TTE
// ==========================================
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
// DATA DISPOSISI TERBARU + USER LOGIN
// ==========================================
$qDisp = mysqli_query($conn, "
    SELECT d.*,
           u.nama AS nama_user,
           u.jabatan AS jabatan_user,
           u.nik AS nik_user,
           u.id AS user_id
    FROM disposisi d
    JOIN users u ON d.disposisi_oleh = u.id
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
        'nik_user' => '-',
        'user_id' => null
    ];
}

// ==========================================
// AMBIL TTE USER YANG MEMBUAT DISPOSISI
// ==========================================
$tte_disposisi = null;
$qrFile = null;

if (!empty($disp['user_id'])) {
    $tte_disposisi = getTteByUser($conn, $disp['user_id']);
    
    // Generate QR Code jika ada TTE
    if ($tte_disposisi && !empty($tte_disposisi['token'])) {
        $verifyUrl = "http://" . $_SERVER['HTTP_HOST'] . "/cek_tte.php?token=" . $tte_disposisi['token'];
        $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($verifyUrl);

        // Try temp directory first
        $tmpQr = sys_get_temp_dir() . '/qr_disp_' . md5($tte_disposisi['token']) . '.png';
        
        if (downloadQr($qrUrl, $tmpQr) && file_exists($tmpQr)) {
            $qrFile = $tmpQr;
        } else {
            // Fallback: try uploads directory
            $uploadDir = __DIR__ . '/uploads/temp/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }
            $tmpQr = $uploadDir . 'qr_disp_' . md5($tte_disposisi['token']) . '.png';
            
            if (downloadQr($qrUrl, $tmpQr) && file_exists($tmpQr)) {
                $qrFile = $tmpQr;
            }
        }
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

// Cell untuk QR Code (kiri) dan Info (kanan)
$qrCellX = $stampX;
$qrCellWidth = 40;
$infoCellWidth = $stampWidth - 40;

// Draw border untuk kedua cell
$pdf->Rect($qrCellX, $tteY, $qrCellWidth, 30);
$pdf->Rect($qrCellX + $qrCellWidth, $tteY, $infoCellWidth, 30);

// Tampilkan QR Code di cell kiri jika ada TTE
if ($qrFile && file_exists($qrFile)) {
    // Posisi QR di tengah cell kiri
    $qrSize = 28;
    $qrX = $qrCellX + ($qrCellWidth - $qrSize) / 2;
    $qrY = $tteY + 1;
    
    // Gunakan file yang sudah didownload
    $pdf->Image($qrFile, $qrX, $qrY, $qrSize);
} else {
    // Jika tidak ada QR, tampilkan placeholder
    $pdf->SetXY($qrCellX + 2, $tteY + 12);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->Cell($qrCellWidth - 4, 5, 'No TTE', 0, 0, 'C');
}

// Info TTE di cell kanan
$pdf->SetXY($qrCellX + $qrCellWidth + 2, $tteY + 6);
$pdf->SetFont('Arial', 'I', 8);

if ($tte_disposisi) {
    $pdf->MultiCell(
        $infoCellWidth - 4,
        4,
        "Ditandatangani secara\nelektronik oleh:\n" .
        $tte_disposisi['nama'] . "\n" .
        $tte_disposisi['jabatan']
    );
} else {
    $pdf->MultiCell(
        $infoCellWidth - 4,
        4,
        "Ditandatangani oleh:\n" .
        $disp['nama_user'] . "\n" .
        $disp['jabatan_user'] . "\n" .
        "(Tanpa TTE)"
    );
}

// Update posisi Y setelah cell TTE
$pdf->SetY($tteY + 30);

// ===== FOOTER LEGAL DI HALAMAN YANG SAMA =====
// Hitung sisa ruang di halaman
$currentY = $pdf->GetY();
$pageHeight = 297; // A4 height in mm
$bottomMargin = 15;
$footerHeight = 20;
$footerStartY = $pageHeight - $bottomMargin - $footerHeight;

// Jika posisi sekarang masih di atas area footer, pindah ke area footer
if ($currentY < $footerStartY) {
    $pdf->SetY($footerStartY);
}

$pdf->SetX(10);
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(190, 4, 'Tanda Tangan Elektronik (TTE) Non Sertifikasi', 0, 1, 'C');

$pdf->SetFont('Arial', '', 7);
$pdf->SetX(10);
$pdf->MultiCell(190, 3.5, 
    "Dokumen ini menggunakan TTE Non Sertifikasi yang sah untuk penggunaan internal perusahaan sesuai Peraturan Pemerintah Nomor 71 Tahun 2019 tentang Penyelenggaraan Sistem dan Transaksi Elektronik dan Undang-Undang Nomor 11 Tahun 2008 jo. UU No. 19 Tahun 2016 tentang Informasi dan Transaksi Elektronik (ITE)",
    0, 'C'
);

$pdf->SetFont('Arial', 'I', 6);
$pdf->SetX(10);
$pdf->Cell(190, 3, 'Dokumen ini di-generate melalui aplikasi FixPoint - Smart Office Management System', 0, 1, 'C');

// ==========================================
// EMBED TTE TOKEN DI PDF STREAM
// ==========================================
$pdf_output = $pdf->Output('S'); // Get as string

if ($tte_disposisi && !empty($tte_disposisi['token'])) {
    $token_text = "\nTTE-TOKEN:" . $tte_disposisi['token'] . "\n";
    $pdf_output = str_replace('%%EOF', $token_text . '%%EOF', $pdf_output);
}

// ==========================================
// SAVE & LOG TTE
// ==========================================
if ($tte_disposisi && !empty($tte_disposisi['token'])) {
    $output_dir = __DIR__ . '/uploads/signed/';
    if (!is_dir($output_dir)) {
        @mkdir($output_dir, 0755, true);
    }
    
    $filename = 'disposisi_' . $id . '_' . time() . '.pdf';
    $filepath = $output_dir . $filename;
    
    // Save PDF
    file_put_contents($filepath, $pdf_output);
    
    // Generate file hash
    $file_hash = generateFileHash($filepath);
    
    // Log TTE
    if ($file_hash) {
        saveDocumentSigningLog($conn, $tte_disposisi['token'], $disp['user_id'], $filename, $file_hash);
    }
}

// ==========================================
// OUTPUT PDF TO BROWSER
// ==========================================
if (ob_get_length()) ob_clean();

header('Content-Type: application/pdf');
header('Content-Disposition: ' . ($mode === 'print' ? 'attachment' : 'inline') . '; filename="Surat_Disposisi_' . $id . '.pdf"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('Content-Length: ' . strlen($pdf_output));

echo $pdf_output;

// ==========================================
// CLEANUP TEMP FILE
// ==========================================
if ($qrFile && file_exists($qrFile)) {
    @unlink($qrFile);
}

exit;
?>