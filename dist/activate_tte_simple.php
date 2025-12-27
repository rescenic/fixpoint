<?php
session_start();
include 'koneksi.php';

// CEK LOGIN
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$check = $conn->query("SELECT * FROM tte_licenses WHERE perusahaan_id = 1 AND status = 'active' LIMIT 1");
if ($check && $check->num_rows > 0) {
    // Sudah aktif, langsung redirect ke cek_tte
    header('Location: cek_tte.php');
    exit;
}

$perusahaan_query = $conn->query("SELECT * FROM perusahaan LIMIT 1");
$perusahaan = $perusahaan_query ? $perusahaan_query->fetch_assoc() : null;

if (!$perusahaan) {
    echo "<!DOCTYPE html>
    <html><head><title>Error</title></head><body>
    <div style='text-align:center; margin-top:50px;'>
        <h2>⚠️ Data Perusahaan Belum Ada</h2>
        <p>Silakan setup data perusahaan di menu <a href='perusahaan.php'>Master Data → Perusahaan</a></p>
        <a href='dashboard.php' class='btn btn-primary'>Kembali ke Dashboard</a>
    </div>
    </body></html>";
    exit;
}

include 'navbar.php';
include 'sidebar.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aktivasi TTE - Fixpoint</title>
    <link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/components.css">
</head>
<body>
<div id="app">
<div class="main-wrapper main-wrapper-1">

<div class="main-content">
    <section class="section">
        <div class="section-header">
            <h1>🔐 Aktivasi TTE</h1>
        </div>

        <div class="section-body">
            <div class="row">
                <div class="col-12 col-md-10 offset-md-1">
                    
                    <!-- Info Perusahaan -->
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h4>📋 Informasi Perusahaan</h4>
                        </div>
                        <div class="card-body">
                            <table class="table table-borderless">
                                <tr>
                                    <td width="200"><strong>🏢 Nama Perusahaan</strong></td>
                                    <td>: <?php echo htmlspecialchars($perusahaan['nama_perusahaan']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>📧 Email</strong></td>
                                    <td>: <?php echo htmlspecialchars($perusahaan['email']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>📍 Alamat</strong></td>
                                    <td>: <?php echo htmlspecialchars($perusahaan['alamat'] ?? '-'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>🏙️ Kota</strong></td>
                                    <td>: <?php echo htmlspecialchars($perusahaan['kota'] ?? '-'); ?>, <?php echo htmlspecialchars($perusahaan['provinsi'] ?? '-'); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header bg-warning">
                            <h4>📝 Langkah-Langkah Aktivasi TTE</h4>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> 
                                <strong>Informasi:</strong> Untuk mengaktifkan fitur TTE, Anda memerlukan token lisensi dari License Server.
                            </div>
                            
                            <h5 class="mb-3">📌 Ikuti langkah berikut:</h5>
                            <ol style="line-height: 2;">
                                <li>Buka License Server di browser baru: 
                                    <a href="http://localhost/fixlicense" target="_blank" class="btn btn-sm btn-primary">
                                        <i class="fas fa-external-link-alt"></i> Buka License Server
                                    </a>
                                </li>
                                <li>Login dengan username: <code>admin</code> dan password: <code>admin123</code></li>
                                <li>Klik tombol <strong>"Buat Lisensi Baru"</strong></li>
                                <li>Isi form dengan data berikut:
                                    <ul>
                                        <li><strong>Email:</strong> <code><?php echo htmlspecialchars($perusahaan['email']); ?></code> 
                                            <button class="btn btn-sm btn-success" onclick="copyEmail()">
                                                <i class="fas fa-copy"></i> Copy
                                            </button>
                                        </li>
                                        <li><strong>Tipe Lisensi:</strong> Pilih <strong>Lifetime</strong></li>
                                        <li><strong>Domain:</strong> Kosongkan atau isi <code>localhost</code></li>
                                    </ul>
                                </li>
                                <li>Klik tombol <strong>"Generate Lisensi"</strong></li>
                                <li><strong>Copy token</strong> yang muncul (format: FIXPOINT-XXXXX-XXXXX-XXXXX)</li>
                                <li>Kembali ke halaman ini dan klik tombol di bawah</li>
                            </ol>
                            
                            <hr>
                            
                            <div class="text-center">
                                <a href="activate_tte.php" class="btn btn-primary btn-lg">
                                    <i class="fas fa-arrow-right"></i> Lanjut ke Form Aktivasi Token
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header bg-secondary text-white">
                            <h4>💡 Butuh Bantuan?</h4>
                        </div>
                        <div class="card-body">
                            <p><strong>Jika mengalami masalah:</strong></p>
                            <ul>
                                <li>Pastikan License Server sudah berjalan di <code>http://localhost/fixlicense</code></li>
                                <li>Pastikan email perusahaan sudah benar</li>
                                <li>Jika lupa password license server, cek file README</li>
                                <li>Hubungi administrator sistem jika masih ada kendala</li>
                            </ul>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
    </section>
</div>

</div>
</div>

<script src="assets/modules/jquery.min.js"></script>
<script src="assets/modules/bootstrap/js/bootstrap.min.js"></script>
<script>
function copyEmail() {
    const email = "<?php echo $perusahaan['email']; ?>";
    navigator.clipboard.writeText(email).then(function() {
        alert('Email berhasil dicopy: ' + email);
    }, function() {
        prompt('Copy email ini:', email);
    });
}
</script>

</body>
</html>