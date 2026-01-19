<?php
/**
 * File: metadata_signature.php
 * Halaman untuk menampilkan METADATA SIGNATURE lengkap dari dokumen ber-TTE
 * Menunjukkan kredibilitas dan integritas TTE Non-Sertifikasi
 */

session_start();
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

// Ambil token dari URL
$token = $_GET['token'] ?? '';

if (empty($token)) {
    die('Token tidak ditemukan');
}

// Query TTE dari database
$qTTE = mysqli_query($conn, "
    SELECT t.*, u.nama, u.nik, u.jabatan, u.unit_kerja, u.email,
           p.nama_perusahaan, p.alamat, p.kota, p.provinsi, p.kontak, p.email as email_perusahaan
    FROM tte_user t
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN perusahaan p ON 1=1
    WHERE t.token = '".mysqli_real_escape_string($conn, $token)."'
    LIMIT 1
");

$tte = mysqli_fetch_assoc($qTTE);

if (!$tte) {
    die('TTE tidak ditemukan atau tidak valid');
}

// Query log dokumen yang ditandatangani dengan TTE ini
$qLogs = mysqli_query($conn, "
    SELECT * FROM tte_document_log
    WHERE tte_token = '".mysqli_real_escape_string($conn, $token)."'
    ORDER BY signed_at DESC
    LIMIT 10
");

$logs = [];
while ($log = mysqli_fetch_assoc($qLogs)) {
    $logs[] = $log;
}

// Hitung statistik
$total_docs = count($logs);
$first_use = !empty($logs) ? end($logs)['signed_at'] : $tte['created_at'];
$last_use = !empty($logs) ? $logs[0]['signed_at'] : '-';

// Format tanggal
function formatTanggal($datetime) {
    if (empty($datetime) || $datetime == '-') return '-';
    return date('d F Y, H:i', strtotime($datetime)) . ' WIB';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Metadata Signature - <?= htmlspecialchars($tte['nama']) ?></title>
<link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css">
<style>
body {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    padding: 30px 0;
}
.container {
    max-width: 900px;
}
.metadata-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    overflow: hidden;
    margin-bottom: 20px;
}
.header-badge {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    text-align: center;
}
.header-badge h3 {
    margin: 0;
    font-size: 24px;
    font-weight: bold;
}
.header-badge .token {
    font-family: 'Courier New', monospace;
    font-size: 12px;
    background: rgba(255,255,255,0.2);
    padding: 8px 15px;
    border-radius: 5px;
    margin-top: 10px;
    display: inline-block;
    word-break: break-all;
}
.status-badge {
    display: inline-block;
    padding: 8px 20px;
    border-radius: 25px;
    font-weight: bold;
    font-size: 14px;
    margin: 10px 0;
}
.status-aktif {
    background: #10b981;
    color: white;
}
.status-expired {
    background: #ef4444;
    color: white;
}
.section {
    padding: 25px 30px;
    border-bottom: 1px solid #e5e7eb;
}
.section:last-child {
    border-bottom: none;
}
.section-title {
    font-size: 18px;
    font-weight: bold;
    color: #1f2937;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
}
.section-title i {
    margin-right: 10px;
    color: #667eea;
}
.info-row {
    display: flex;
    padding: 12px 0;
    border-bottom: 1px solid #f3f4f6;
}
.info-row:last-child {
    border-bottom: none;
}
.info-label {
    width: 200px;
    font-weight: 600;
    color: #6b7280;
    flex-shrink: 0;
}
.info-value {
    flex: 1;
    color: #111827;
    word-break: break-word;
}
.hash-value {
    font-family: 'Courier New', monospace;
    font-size: 11px;
    background: #f3f4f6;
    padding: 8px;
    border-radius: 5px;
    word-break: break-all;
}
.doc-list {
    list-style: none;
    padding: 0;
    margin: 0;
}
.doc-item {
    padding: 12px;
    background: #f9fafb;
    border-radius: 8px;
    margin-bottom: 10px;
    border-left: 4px solid #667eea;
}
.doc-item strong {
    color: #1f2937;
    display: block;
    margin-bottom: 5px;
}
.doc-item small {
    color: #6b7280;
}
.qr-display {
    text-align: center;
    padding: 20px;
    background: #f9fafb;
    border-radius: 10px;
}
.qr-display img {
    width: 200px;
    height: 200px;
    border: 3px solid #667eea;
    border-radius: 10px;
    padding: 10px;
    background: white;
}
.security-features {
    background: #ecfdf5;
    border-left: 4px solid #10b981;
    padding: 15px;
    border-radius: 5px;
    margin-top: 15px;
}
.security-features h6 {
    color: #047857;
    font-weight: bold;
    margin-bottom: 10px;
}
.security-features ul {
    margin: 0;
    padding-left: 20px;
}
.security-features li {
    color: #065f46;
    margin-bottom: 5px;
}
.btn-download {
    background: #667eea;
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 25px;
    font-weight: bold;
    text-decoration: none;
    display: inline-block;
    margin-top: 15px;
}
.btn-download:hover {
    background: #5568d3;
    color: white;
    text-decoration: none;
}
.footer-note {
    background: #f9fafb;
    padding: 20px;
    text-align: center;
    border-radius: 10px;
    margin-top: 20px;
}
.footer-note small {
    color: #6b7280;
}
</style>
</head>
<body>

<div class="container">
    
    <!-- Header Badge -->
    <div class="metadata-card">
        <div class="header-badge">
            <i class="fas fa-certificate fa-3x mb-3"></i>
            <h3>METADATA SIGNATURE</h3>
            <p class="mb-0">Tanda Tangan Elektronik Non-Sertifikasi</p>
            <div class="token">Token: <?= htmlspecialchars($token) ?></div>
            <div class="mt-3">
                <?php if ($tte['status'] == 'aktif'): ?>
                    <span class="status-badge status-aktif">
                        <i class="fas fa-check-circle"></i> STATUS: AKTIF & VALID
                    </span>
                <?php else: ?>
                    <span class="status-badge status-expired">
                        <i class="fas fa-times-circle"></i> STATUS: TIDAK AKTIF
                    </span>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Informasi Pemilik TTE -->
        <div class="section">
            <div class="section-title">
                <i class="fas fa-user-circle"></i>
                Informasi Pemilik Tanda Tangan
            </div>
            
            <div class="info-row">
                <div class="info-label">Nama Lengkap</div>
                <div class="info-value"><strong><?= htmlspecialchars($tte['nama']) ?></strong></div>
            </div>
            <div class="info-row">
                <div class="info-label">NIK/NIP</div>
                <div class="info-value"><?= htmlspecialchars($tte['nik']) ?></div>
            </div>
            <?php if (!empty($tte['no_ktp'])): ?>
            <div class="info-row">
                <div class="info-label">Nomor KTP</div>
                <div class="info-value"><?= htmlspecialchars($tte['no_ktp']) ?></div>
            </div>
            <?php endif; ?>
            <div class="info-row">
                <div class="info-label">Jabatan</div>
                <div class="info-value"><?= htmlspecialchars($tte['jabatan']) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Unit/Bagian</div>
                <div class="info-value"><?= htmlspecialchars($tte['unit_kerja']) ?></div>
            </div>
            <?php if (!empty($tte['email'])): ?>
            <div class="info-row">
                <div class="info-label">Email</div>
                <div class="info-value"><?= htmlspecialchars($tte['email']) ?></div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Informasi Perusahaan/Instansi -->
        <div class="section">
            <div class="section-title">
                <i class="fas fa-building"></i>
                Informasi Perusahaan/Instansi
            </div>
            
            <div class="info-row">
                <div class="info-label">Nama Perusahaan</div>
                <div class="info-value"><strong><?= htmlspecialchars($tte['nama_perusahaan']) ?></strong></div>
            </div>
            <div class="info-row">
                <div class="info-label">Alamat</div>
                <div class="info-value"><?= htmlspecialchars($tte['alamat']) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Kota/Provinsi</div>
                <div class="info-value"><?= htmlspecialchars($tte['kota']) ?>, <?= htmlspecialchars($tte['provinsi']) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Kontak</div>
                <div class="info-value"><?= htmlspecialchars($tte['kontak']) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Email Perusahaan</div>
                <div class="info-value"><?= htmlspecialchars($tte['email_perusahaan']) ?></div>
            </div>
        </div>
        
        <!-- Informasi Teknis TTE -->
        <div class="section">
            <div class="section-title">
                <i class="fas fa-cog"></i>
                Informasi Teknis Tanda Tangan Elektronik
            </div>
            
            <div class="info-row">
                <div class="info-label">Token ID (Hash)</div>
                <div class="info-value">
                    <div class="hash-value"><?= htmlspecialchars($tte['token']) ?></div>
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">File Hash (SHA-256)</div>
                <div class="info-value">
                    <div class="hash-value"><?= htmlspecialchars($tte['file_hash']) ?></div>
                    <small class="text-muted">Untuk verifikasi integritas file asli</small>
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Dibuat Pada</div>
                <div class="info-value"><?= formatTanggal($tte['created_at']) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Terakhir Diperbarui</div>
                <div class="info-value"><?= formatTanggal($tte['updated_at']) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Status</div>
                <div class="info-value">
                    <?php if ($tte['status'] == 'aktif'): ?>
                        <span class="badge badge-success">AKTIF</span>
                    <?php else: ?>
                        <span class="badge badge-danger">TIDAK AKTIF</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">IP Address Pembuatan</div>
                <div class="info-value">
                    <?= htmlspecialchars($tte['ip_address'] ?? '-') ?>
                </div>
            </div>
        </div>
        
        <!-- Statistik Penggunaan -->
        <div class="section">
            <div class="section-title">
                <i class="fas fa-chart-line"></i>
                Statistik Penggunaan TTE
            </div>
            
            <div class="info-row">
                <div class="info-label">Total Dokumen Ditandatangani</div>
                <div class="info-value"><strong><?= $total_docs ?> Dokumen</strong></div>
            </div>
            <div class="info-row">
                <div class="info-label">Pertama Kali Digunakan</div>
                <div class="info-value"><?= formatTanggal($first_use) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Terakhir Kali Digunakan</div>
                <div class="info-value"><?= formatTanggal($last_use) ?></div>
            </div>
        </div>
        
        <!-- Daftar Dokumen yang Ditandatangani -->
        <?php if (!empty($logs)): ?>
        <div class="section">
            <div class="section-title">
                <i class="fas fa-file-signature"></i>
                Riwayat Dokumen yang Ditandatangani
            </div>
            
            <ul class="doc-list">
                <?php foreach ($logs as $idx => $log): ?>
                <li class="doc-item">
                    <strong>#<?= $idx + 1 ?> - <?= htmlspecialchars($log['document_name']) ?></strong>
                    <small>
                        Ditandatangani: <?= formatTanggal($log['signed_at']) ?><br>
                        Hash File: <code><?= htmlspecialchars(substr($log['document_hash'], 0, 40)) ?>...</code>
                    </small>
                </li>
                <?php endforeach; ?>
            </ul>
            
            <?php if ($total_docs > 10): ?>
            <p class="text-muted mt-3 mb-0">
                <i class="fas fa-info-circle"></i> Menampilkan 10 dokumen terbaru dari total <?= $total_docs ?> dokumen
            </p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- QR Code Verifikasi -->
        <div class="section">
            <div class="section-title">
                <i class="fas fa-qrcode"></i>
                QR Code Verifikasi
            </div>
            
            <div class="qr-display">
                <?php
                $qr_url = "http://" . $_SERVER['HTTP_HOST'] . "/cek_tte.php?token=" . $token;
                $qr_api = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qr_url);
                ?>
                <img src="<?= $qr_api ?>" alt="QR Code">
                <p class="mt-3 mb-0">
                    <small class="text-muted">Scan QR Code ini untuk verifikasi cepat</small><br>
                    <a href="<?= htmlspecialchars($qr_url) ?>" target="_blank" class="btn-download btn-sm mt-2">
                        <i class="fas fa-external-link-alt"></i> Buka Link Verifikasi
                    </a>
                </p>
            </div>
        </div>
        
        <!-- Fitur Keamanan -->
        <div class="section">
            <div class="section-title">
                <i class="fas fa-shield-alt"></i>
                Fitur Keamanan & Integritas
            </div>
            
            <div class="security-features">
                <h6><i class="fas fa-lock"></i> Metode Keamanan yang Diterapkan:</h6>
                <ul>
                    <li><strong>SHA-256 Hashing:</strong> Setiap dokumen memiliki hash unik untuk deteksi perubahan</li>
                    <li><strong>Token Unik:</strong> Setiap TTE memiliki token kriptografi yang tidak dapat diduplikasi</li>
                    <li><strong>Timestamp Server:</strong> Waktu penandatanganan dicatat oleh server (non-repudiation)</li>
                    <li><strong>Database Logging:</strong> Semua aktivitas TTE tercatat di database terenkripsi</li>
                    <li><strong>IP Address Tracking:</strong> Lokasi pembuatan dan penggunaan TTE tercatat</li>
                    <li><strong>QR Code Verification:</strong> Verifikasi instan melalui scan QR Code</li>
                </ul>
            </div>
            
            <div class="alert alert-info mt-3 mb-0">
                <i class="fas fa-info-circle"></i> <strong>Catatan:</strong> 
                Ini adalah Tanda Tangan Elektronik Non-Sertifikasi yang dikelola secara internal oleh 
                <strong><?= htmlspecialchars($tte['nama_perusahaan']) ?></strong> menggunakan 
                <strong>FixPoint Smart Office Management System</strong>.
            </div>
        </div>
        
        <!-- Download Metadata -->
        <div class="section">
            <div class="text-center">
                <a href="export_metadata.php?token=<?= htmlspecialchars($token) ?>" class="btn-download">
                    <i class="fas fa-download"></i> Download Metadata Lengkap (PDF)
                </a>
                <a href="cek_tte.php" class="btn-download" style="background: #10b981;">
                    <i class="fas fa-search"></i> Verifikasi Dokumen Lain
                </a>
            </div>
        </div>
    </div>
    
    <!-- Footer Note -->
    <div class="footer-note">
        <small>
            <i class="fas fa-lock"></i> Halaman ini menampilkan metadata signature untuk keperluan verifikasi dan audit.<br>
            Informasi ini bersifat publik dan dapat diakses untuk memverifikasi keaslian dokumen.<br>
            <strong>Powered by FixPoint – Smart Office Management System</strong>
        </small>
    </div>
    
</div>

<script src="assets/modules/jquery.min.js"></script>
<script src="assets/modules/bootstrap/js/bootstrap.min.js"></script>
</body>
</html>