<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'koneksi.php';


$licenseConfigPath = __DIR__ . '/license_config.php';
$licenseValidatorPath = __DIR__ . '/license_validator.php';

if (!file_exists($licenseConfigPath)) {
    die('ERROR: File license_config.php tidak ditemukan di folder: ' . __DIR__);
}

if (!file_exists($licenseValidatorPath)) {
    die('ERROR: File license_validator.php tidak ditemukan di folder: ' . __DIR__);
}

require_once $licenseConfigPath;
require_once $licenseValidatorPath;


if (!function_exists('validateLicenseToServer')) {
    die('ERROR: Function validateLicenseToServer() tidak ditemukan! Periksa isi file license_validator.php<br><br>Path: ' . $licenseValidatorPath);
}

if (!function_exists('saveLicenseToDatabase')) {
    die('ERROR: Function saveLicenseToDatabase() tidak ditemukan! Periksa isi file license_validator.php');
}


if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}


$result = $conn->query("SELECT * FROM perusahaan LIMIT 1");
$perusahaan = $result ? $result->fetch_assoc() : null;

if (!$perusahaan) {
    die('Data perusahaan tidak ditemukan! Silakan setup data perusahaan terlebih dahulu di menu Master Data → Perusahaan.');
}

$perusahaan_id = $perusahaan['id'];
$message = '';
$messageType = '';
$activated = false;


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<!-- DEBUG: Form submitted -->\n";
    
    $token = strtoupper(trim($_POST['token'] ?? ''));
    $email = strtolower(trim($_POST['email'] ?? ''));
    
    echo "<!-- DEBUG: Token = $token -->\n";
    echo "<!-- DEBUG: Email = $email -->\n";
    echo "<!-- DEBUG: Perusahaan ID = $perusahaan_id -->\n";
    
    if (empty($token)) {
        $message = 'Token wajib diisi!';
        $messageType = 'danger';
    } elseif (empty($email)) {
        $message = 'Email wajib diisi!';
        $messageType = 'danger';
    } else {
       
        echo "<!-- DEBUG: Calling validateLicenseToServer (OFFLINE MODE)... -->\n";
        
        try {
            $result = validateLicenseToServer($token, $email);
            
            echo "<!-- DEBUG: Validation Result = " . print_r($result, true) . " -->\n";
            
            if ($result['success']) {
                echo "<!-- DEBUG: Token VALID, saving to database... -->\n";
                
          
                try {
                    $saved = saveLicenseToDatabase($conn, $perusahaan_id, $email, $token, $result['data']);
                    
                    echo "<!-- DEBUG: Save result = " . print_r($saved, true) . " -->\n";
                    
                    
                    if ($saved === true) {
                        // Berhasil
                        $message = '✅ TTE berhasil diaktifkan! Silakan gunakan fitur TTE.';
                        $messageType = 'success';
                        $activated = true;
                        $_SESSION['tte_activated'] = true;
                        
                        echo "<!-- DEBUG: SUCCESS! Redirecting to cek_tte.php in 2 seconds... -->\n";
                        header("Refresh: 2; url=cek_tte.php");
                        
                    } elseif (is_array($saved) && isset($saved['success']) && !$saved['success']) {
                        $message = $saved['message'];
                        $messageType = 'danger';
                        echo "<!-- DEBUG: Email already used -->\n";
                        
                    } else {
                        $message = 'Gagal menyimpan ke database: ' . $conn->error;
                        $messageType = 'danger';
                        echo "<!-- DEBUG ERROR: " . $conn->error . " -->\n";
                    }
                    
                } catch (Exception $e) {
                    $message = 'Error saat menyimpan: ' . $e->getMessage();
                    $messageType = 'danger';
                    echo "<!-- DEBUG EXCEPTION: " . $e->getMessage() . " -->\n";
                }
                
            } else {
                $message = $result['message'] ?? 'Validasi gagal tanpa pesan error';
                $messageType = 'danger';
                echo "<!-- DEBUG: Validation FAILED - " . $message . " -->\n";
            }
            
        } catch (Exception $e) {
            $message = 'Error saat validasi: ' . $e->getMessage();
            $messageType = 'danger';
            echo "<!-- DEBUG EXCEPTION: " . $e->getMessage() . " -->\n";
        }
    }
}

include 'navbar.php';
include 'sidebar.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Aktivasi TTE</title>
    <link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .token-input {
            text-transform: uppercase;
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 2px;
            font-family: 'Courier New', monospace;
        }
    </style>
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
                <div class="col-12 col-md-8 offset-md-2">
                    
                  
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible show fade">
                            <div class="alert-body">
                                <button class="close" data-dismiss="alert">
                                    <span>&times;</span>
                                </button>
                                <strong><?php echo $messageType === 'success' ? '✅ Sukses!' : '❌ Error!'; ?></strong><br>
                                <?php echo $message; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="card">
                        <div class="card-header">
                            <h4>Aktivasi Fitur Tanda Tangan Elektronik</h4>
                        </div>
                        <div class="card-body">
                            
                            <?php if (!$activated): ?>
                                
                            
                                <div class="alert alert-info">
                                    <strong>🏢 Perusahaan:</strong> <?php echo htmlspecialchars($perusahaan['nama_perusahaan']); ?><br>
                                    <strong>📧 Email Terdaftar:</strong> <?php echo htmlspecialchars($perusahaan['email']); ?><br>
                                    <strong>🆔 Perusahaan ID:</strong> <?php echo $perusahaan_id; ?>
                                </div>
                                
                                <!-- Info Cara Aktivasi -->
                                <div class="alert alert-light border">
                                    <h6 class="mb-2">ℹ️ Cara Aktivasi:</h6>
                                    <ol class="mb-0 pl-3">
                                        <li>Dapatkan <strong>token lisensi</strong> dari administrator</li>
                                        <li>Token dibuat khusus untuk email: <code><?php echo htmlspecialchars($perusahaan['email']); ?></code></li>
                                        <li>Masukkan token di form bawah</li>
                                        <li>Klik tombol Aktivasi</li>
                                    </ol>
                                </div>
                                
                              
                                <form method="POST" action="">
                                    <div class="form-group">
                                        <label>📧 Email Perusahaan</label>
                                        <input type="email" 
                                               name="email" 
                                               class="form-control" 
                                               value="<?php echo htmlspecialchars($perusahaan['email']); ?>"
                                               readonly required>
                                        <small class="form-text text-muted">
                                            Email ini harus sesuai dengan yang digunakan saat generate token
                                        </small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>🔑 Token Lisensi</label>
                                        <input type="text" 
                                               name="token" 
                                               class="form-control form-control-lg token-input" 
                                               placeholder="FIXPOINT-XXXXX-XXXXX-XXXXX"
                                               value="<?php echo isset($_POST['token']) ? htmlspecialchars($_POST['token']) : ''; ?>"
                                               maxlength="28"
                                               required>
                                        <small class="form-text text-muted">
                                            Paste token yang Anda terima dari administrator
                                        </small>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary btn-lg btn-block">
                                        🔓 Aktivasi TTE Sekarang
                                    </button>
                                </form>
                                
                           
                                <div class="alert alert-warning mt-3">
                                    <strong>⚠️ Penting:</strong>
                                    <ul class="mb-0 pl-3">
                                        <li>Token bersifat <strong>case-sensitive</strong> (huruf besar semua)</li>
                                        <li>Format: <code>FIXPOINT-XXXXX-XXXXX-XXXXX</code> (28 karakter)</li>
                                        <li>1 token hanya untuk 1 email perusahaan</li>
                                        <li>Setiap email hanya bisa aktivasi 1 kali</li>
                                    </ul>
                                </div>
                                
                            <?php else: ?>
                                
                                <!-- Success State -->
                                <div class="text-center py-5">
                                    <i class="fas fa-check-circle text-success" style="font-size: 80px;"></i>
                                    <h3 class="text-success mt-3">✅ Aktivasi Berhasil!</h3>
                                    <p>Fitur TTE telah aktif. Anda akan diarahkan ke halaman Cek TTE...</p>
                                    <div class="spinner-border text-primary mt-3" role="status">
                                        <span class="sr-only">Loading...</span>
                                    </div>
                                </div>
                                
                            <?php endif; ?>
                            
                        </div>
                    </div>
                    
                    <?php if (!$activated && ini_get('display_errors')): ?>
                    <div class="card">
                        <div class="card-header bg-secondary text-white">
                            <h4>🔍 Debug Information</h4>
                        </div>
                        <div class="card-body">
                            <p><strong>Perusahaan ID:</strong> <?php echo $perusahaan_id; ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($perusahaan['email']); ?></p>
                            <p><strong>License Mode:</strong> <?php echo htmlspecialchars(getLicenseConfig()['mode'] ?? 'unknown'); ?></p>
                            <p><strong>License Config Path:</strong> <?php echo $licenseConfigPath; ?></p>
                            <p><strong>License Validator Path:</strong> <?php echo $licenseValidatorPath; ?></p>
                            <p class="text-muted mb-0">
                                <small>Lihat debug detail lengkap di View Page Source (Ctrl+U)</small>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
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

document.addEventListener('DOMContentLoaded', function() {
    const tokenInput = document.querySelector('input[name="token"]');
    
    if (tokenInput) {
        tokenInput.addEventListener('input', function(e) {
            // Remove semua karakter selain huruf dan angka
            let value = e.target.value.replace(/[^A-Z0-9-]/gi, '').toUpperCase();
            
            // Hilangkan dash dulu
            value = value.replace(/-/g, '');
            
            // Auto-format dengan dash: FIXPOINT-XXXXX-XXXXX-XXXXX
            if (value.startsWith('FIXPOINT')) {
                value = value.substring(8); // Hapus "FIXPOINT"
                
                let formatted = 'FIXPOINT';
                if (value.length > 0) formatted += '-' + value.substring(0, 5);
                if (value.length > 5) formatted += '-' + value.substring(5, 10);
                if (value.length > 10) formatted += '-' + value.substring(10, 15);
                
                e.target.value = formatted;
            } else {
                e.target.value = value;
            }
        });
        tokenInput.addEventListener('paste', function(e) {
            setTimeout(function() {
                tokenInput.dispatchEvent(new Event('input'));
            }, 10);
        });
    }
});
</script>

</body>
</html>
