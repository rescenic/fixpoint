<?php
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');


$token = $_GET['token'] ?? '';


$status = 'invalid';
$data   = null;

if ($token !== '') {
    $stmt = $conn->prepare("
        SELECT nama, nik, jabatan, unit, created_at, status
        FROM tte_user
        WHERE token = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();

    if ($data && $data['status'] === 'aktif') {
        $status = 'valid';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Verifikasi Tanda Tangan Elektronik</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.verify-container {
    width: 95%;
    max-width: 900px;
    height: 90vh;
    background: #ffffff;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.header {
    background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
    color: white;
    padding: 25px 30px;
    text-align: center;
    position: relative;
}

.header::after {
    content: '';
    position: absolute;
    bottom: -10px;
    left: 50%;
    transform: translateX(-50%);
    width: 80px;
    height: 4px;
    background: #667eea;
    border-radius: 2px;
}

.header h2 {
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 5px;
    letter-spacing: 0.5px;
}

.header small {
    font-size: 13px;
    opacity: 0.9;
}

.content {
    flex: 1;
    padding: 30px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}

/* Status Badge */
.status-badge {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 25px;
    font-size: 18px;
    font-weight: 600;
}

.status-badge.valid {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white;
}

.status-badge.invalid {
    background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
    color: white;
}

.status-badge i {
    font-size: 28px;
}

/* Info Grid */
.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

.info-item {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 10px;
    border-left: 4px solid #667eea;
}

.info-label {
    font-size: 12px;
    color: #6c757d;
    margin-bottom: 5px;
    font-weight: 500;
    text-transform: uppercase;
}

.info-value {
    font-size: 15px;
    color: #212529;
    font-weight: 600;
}

/* Legal Section */
.legal-section {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    padding: 20px;
    border-radius: 10px;
    margin-top: auto;
}

.legal-title {
    font-size: 14px;
    font-weight: 700;
    color: #1e3c72;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.legal-text {
    font-size: 11px;
    color: #495057;
    line-height: 1.5;
}

/* Invalid State */
.invalid-message {
    text-align: center;
    padding: 30px;
}

.invalid-message i {
    font-size: 64px;
    color: #dc3545;
    margin-bottom: 20px;
}

.invalid-message h3 {
    color: #212529;
    margin-bottom: 10px;
    font-size: 20px;
}

.invalid-message p {
    color: #6c757d;
    font-size: 14px;
}

/* Footer */
.footer {
    background: #f8f9fa;
    padding: 15px;
    text-align: center;
    font-size: 12px;
    color: #6c757d;
    border-top: 1px solid #e9ecef;
}

/* Badge */
.badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white;
}

/* Scrollbar */
.content::-webkit-scrollbar {
    width: 6px;
}

.content::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.content::-webkit-scrollbar-thumb {
    background: #667eea;
    border-radius: 3px;
}

/* Responsive */
@media (max-width: 768px) {
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .header h2 {
        font-size: 20px;
    }
    
    .status-badge {
        font-size: 16px;
    }
}
</style>
</head>
<body>

<div class="verify-container">
    
    <div class="header">
        <h2><i class="fas fa-shield-check"></i> VERIFIKASI TANDA TANGAN ELEKTRONIK</h2>
        <small>Sistem FixPoint - Indonesia</small>
    </div>

    <div class="content">
        
        <?php if ($status === 'valid'): ?>
        
        <div class="status-badge valid">
            <i class="fas fa-check-circle"></i>
            <span>TANDA TANGAN ELEKTRONIK TERVERIFIKASI</span>
        </div>

        <div class="info-grid">
            <div class="info-item">
                <div class="info-label"><i class="fas fa-user"></i> Nama Penandatangan</div>
                <div class="info-value"><?= htmlspecialchars($data['nama']) ?></div>
            </div>
            
            <div class="info-item">
                <div class="info-label"><i class="fas fa-id-card"></i> NIK / NIP</div>
                <div class="info-value"><?= htmlspecialchars($data['nik']) ?></div>
            </div>
            
            <div class="info-item">
                <div class="info-label"><i class="fas fa-briefcase"></i> Jabatan</div>
                <div class="info-value"><?= htmlspecialchars($data['jabatan']) ?></div>
            </div>
            
            <div class="info-item">
                <div class="info-label"><i class="fas fa-building"></i> Unit Kerja</div>
                <div class="info-value"><?= htmlspecialchars($data['unit']) ?></div>
            </div>
            
            <div class="info-item">
                <div class="info-label"><i class="fas fa-calendar-alt"></i> Tanggal Pembuatan</div>
                <div class="info-value"><?= date('d M Y, H:i', strtotime($data['created_at'])) ?> WIB</div>
            </div>
            
            <div class="info-item">
                <div class="info-label"><i class="fas fa-check-circle"></i> Status TTE</div>
                <div class="info-value"><span class="badge">AKTIF</span></div>
            </div>
        </div>

        <div class="legal-section">
            <div class="legal-title">
                <i class="fas fa-gavel"></i> Dasar Hukum & Keterangan
            </div>
            <div class="legal-text">
                Tanda tangan elektronik ini merupakan <strong>Tanda Tangan Elektronik Non-Tersertifikasi</strong> 
                yang dibuat melalui Sistem FixPoint sesuai UU No. 11 Tahun 2008 tentang ITE, 
                UU No. 19 Tahun 2016, dan PP No. 71 Tahun 2019 tentang Penyelenggaraan Sistem dan 
                Transaksi Elektronik. Memiliki kekuatan hukum sepanjang memenuhi persyaratan keabsahan 
                dan dapat diverifikasi.
            </div>
        </div>

        <?php else: ?>
        
        <div class="status-badge invalid">
            <i class="fas fa-times-circle"></i>
            <span>TANDA TANGAN ELEKTRONIK TIDAK VALID</span>
        </div>

        <div class="invalid-message">
            <i class="fas fa-exclamation-triangle"></i>
            <h3>Data Tidak Ditemukan</h3>
            <p>Tanda tangan elektronik tidak terdaftar, sudah tidak aktif, atau data tidak valid dalam sistem.<br>
            Silakan hubungi administrator Sistem FixPoint untuk informasi lebih lanjut.</p>
        </div>

        <?php endif; ?>

    </div>

    <div class="footer">
        <i class="fas fa-copyright"></i> <?= date('Y') ?> Sistem FixPoint – Verifikasi Tanda Tangan Elektronik Indonesia
    </div>

</div>

</body>
</html>