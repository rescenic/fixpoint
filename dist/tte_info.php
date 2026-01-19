<?php
// File: tte_info.php
// Landing page untuk QR Code scan - menampilkan info TTE lengkap

session_start();
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

// Get token from URL
$token = $_GET['token'] ?? '';

if (empty($token)) {
    header("Location: index.php");
    exit;
}

// Query TTE info
$query = "
    SELECT 
        t.token, t.nama, t.nik, t.no_ktp, t.jabatan, t.unit_kerja, t.status, t.created_at,
        p.nama_perusahaan, p.alamat, p.kota, p.provinsi, p.kontak, p.email,
        l.document_name, l.document_hash, l.signed_at, l.ip_address
    FROM tte_user t
    LEFT JOIN perusahaan p ON t.perusahaan_id = p.id
    LEFT JOIN tte_document_log l ON t.token = l.tte_token
    WHERE t.token = ?
    ORDER BY l.signed_at DESC
    LIMIT 1
";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();
$tte = $result->fetch_assoc();

if (!$tte) {
    $error = "TTE tidak ditemukan";
} else {
    $signed_date = $tte['signed_at'] ?? $tte['created_at'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Info Tanda Tangan Elektronik</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }
        .info-container {
            max-width: 600px;
            margin: 0 auto;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            border: none;
            margin-bottom: 20px;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
        }
        .card-header h4 {
            margin: 0;
            font-weight: 600;
        }
        .info-badge {
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            display: inline-block;
            margin-top: 10px;
        }
        .info-row {
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #666;
            font-size: 0.9em;
            margin-bottom: 5px;
        }
        .info-value {
            color: #333;
            font-size: 1.1em;
        }
        .document-name {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        .verified-badge {
            background: #28a745;
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            display: inline-block;
            font-weight: 600;
            margin-top: 10px;
        }
        .footer-note {
            text-align: center;
            color: white;
            margin-top: 30px;
            font-size: 0.9em;
        }
        .icon-badge {
            width: 40px;
            height: 40px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            color: #667eea;
        }
        @media print {
            body {
                background: white;
            }
            .card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>
    <div class="info-container">
        <?php if (isset($error)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                    <h5><?php echo $error; ?></h5>
                    <p class="text-muted">Token TTE tidak valid atau sudah tidak aktif</p>
                </div>
            </div>
        <?php else: ?>
            
            <!-- Header Card -->
            <div class="card">
                <div class="card-header text-center">
                    <i class="fas fa-shield-check fa-3x mb-3"></i>
                    <h4>Tanda Tangan Elektronik</h4>
                    <div class="info-badge">
                        <i class="fas fa-check-circle"></i> Terverifikasi
                    </div>
                </div>
            </div>

            <!-- Document Info -->
            <?php if (!empty($tte['document_name'])): ?>
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="icon-badge">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <h5 class="mb-0">Informasi Dokumen</h5>
                    </div>
                    <div class="document-name">
                        <div class="info-label">Nama Dokumen:</div>
                        <div class="info-value">
                            <i class="fas fa-file-pdf text-danger"></i>
                            <strong><?php echo htmlspecialchars($tte['document_name']); ?></strong>
                        </div>
                    </div>
                    <?php if (!empty($tte['signed_at'])): ?>
                    <div class="text-muted small">
                        <i class="fas fa-clock"></i> 
                        Ditandatangani: <?php echo date('d F Y, H:i', strtotime($tte['signed_at'])); ?> WIB
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Signer Info -->
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="icon-badge">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <h5 class="mb-0">Informasi Penandatangan</h5>
                    </div>

                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-user"></i> Nama Lengkap
                        </div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($tte['nama']); ?>
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-id-badge"></i> NIK/NIP
                        </div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($tte['nik']); ?>
                        </div>
                    </div>

                    <?php if (!empty($tte['no_ktp'])): ?>
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-address-card"></i> No. KTP
                        </div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($tte['no_ktp']); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-briefcase"></i> Jabatan
                        </div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($tte['jabatan']); ?>
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-building"></i> Unit/Bagian
                        </div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($tte['unit_kerja']); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Time Info -->
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="icon-badge">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h5 class="mb-0">Waktu Penandatanganan</h5>
                    </div>

                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-calendar-alt"></i> Tanggal
                        </div>
                        <div class="info-value">
                            <?php echo date('d F Y', strtotime($signed_date)); ?>
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-clock"></i> Jam
                        </div>
                        <div class="info-value">
                            <?php echo date('H:i', strtotime($signed_date)); ?> WIB
                        </div>
                    </div>
                </div>
            </div>

            <!-- Company Info (if exists) -->
            <?php if (!empty($tte['nama_perusahaan'])): ?>
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="icon-badge">
                            <i class="fas fa-building"></i>
                        </div>
                        <h5 class="mb-0">Informasi Perusahaan/Instansi</h5>
                    </div>

                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-building"></i> Nama Perusahaan
                        </div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($tte['nama_perusahaan']); ?>
                        </div>
                    </div>

                    <?php if (!empty($tte['alamat'])): ?>
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-map-marker-alt"></i> Alamat
                        </div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($tte['alamat']); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($tte['kota']) && !empty($tte['provinsi'])): ?>
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-city"></i> Kota/Provinsi
                        </div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($tte['kota'] . ', ' . $tte['provinsi']); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Verification Badge -->
            <div class="text-center">
                <div class="verified-badge">
                    <i class="fas fa-check-circle"></i> 
                    TTE Terverifikasi & Sah
                </div>
            </div>

            <!-- Footer Note -->
            <div class="footer-note">
                <p>
                    <i class="fas fa-info-circle"></i> 
                    Dokumen ini telah ditandatangani secara elektronik<br>
                    sesuai UU No. 11/2008 tentang ITE dan PP No. 71/2019
                </p>
                <p class="small">
                    TTE Non-Tersertifikasi - FixPoint Digital Signature System
                </p>
            </div>

        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>