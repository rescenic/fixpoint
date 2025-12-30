<?php
// ===== SECURITY HEADERS =====
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self';");

session_start();
require 'koneksi.php';

// ===== REGENERATE SESSION ID (Prevent Session Fixation) =====
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

// ===== GENERATE CSRF TOKEN =====
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$notif = "";

// ===== GET ERROR MESSAGE FROM SESSION (PRG Pattern) =====
if (isset($_SESSION['login_error'])) {
    $notif = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}

// ===== RATE LIMITING FUNCTION =====
function checkRateLimit($ip, $conn) {
    $max_attempts = 5;
    $time_window = 180; // 3 menit (180 detik)
    
    // Bersihkan data lama
    $conn->query("DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL {$time_window} SECOND)");
    
    // Hitung percobaan
    $stmt = $conn->prepare("SELECT COUNT(*) as attempts FROM login_attempts WHERE ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL {$time_window} SECOND)");
    $stmt->bind_param("s", $ip);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result['attempts'] < $max_attempts;
}

// ===== RECORD LOGIN ATTEMPT =====
function recordLoginAttempt($ip, $email, $success, $conn) {
    $stmt = $conn->prepare("INSERT INTO login_attempts (ip_address, email, success, attempt_time) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("ssi", $ip, $email, $success);
    $stmt->execute();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['email'], $_POST['password'])) {
    
    // ===== CSRF TOKEN VALIDATION =====
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $notif = "Permintaan tidak valid. Silakan muat ulang halaman.";
    } else {
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        // ===== RATE LIMITING CHECK =====
        if (!checkRateLimit($ip_address, $conn)) {
            $notif = "Terlalu banyak percobaan login. Silakan coba lagi dalam 3 menit.";
        } else {
            $email = trim($_POST['email']);
            $password = trim($_POST['password']);
            $captcha_input = strtoupper(trim($_POST['captcha_input'] ?? ''));
            $captcha_session = $_SESSION['captcha'] ?? '';
            
            if ($email === "" || $password === "") {
                $notif = "Email dan Password tidak boleh kosong.";
                recordLoginAttempt($ip_address, $email, 0, $conn);
            } elseif ($captcha_input === "" || $captcha_input !== $captcha_session) {
                $notif = "Kode keamanan salah atau kosong.";
                recordLoginAttempt($ip_address, $email, 0, $conn);
            } else {
                // Email validation
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $notif = "Format email tidak valid.";
                    recordLoginAttempt($ip_address, $email, 0, $conn);
                } else {
                    $stmt = $conn->prepare("SELECT id, nama, password_hash, status FROM users WHERE email = ?");
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $stmt->store_result();
                    
                    if ($stmt->num_rows === 1) {
                        $stmt->bind_result($id, $nama, $password_hash, $status);
                        $stmt->fetch();
                        
                        if ($status != 'active') {
                            // Generic message untuk keamanan
                            $notif = "Login gagal. Periksa kredensial Anda.";
                            recordLoginAttempt($ip_address, $email, 0, $conn);
                        } elseif (password_verify($password, $password_hash)) {
                            // ===== SUCCESSFUL LOGIN =====
                            
                            // Regenerate session ID
                            session_regenerate_id(true);
                            
                            $_SESSION['user_id'] = $id;
                            $_SESSION['nama'] = $nama;
                            $_SESSION['login_time'] = time();
                            $_SESSION['last_activity'] = time();
                            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
                            
                            // Update last login dengan IP
                            $update = $conn->prepare("UPDATE users SET last_login = NOW(), last_ip = ? WHERE id = ?");
                            $update->bind_param("si", $ip_address, $id);
                            $update->execute();
                            
                            // Record successful login
                            recordLoginAttempt($ip_address, $email, 1, $conn);
                            
                            // Regenerate CSRF token
                            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                            
                            header("Location: dashboard.php");
                            exit;
                        } else {
                            // Generic message
                            $notif = "Login gagal. Periksa kredensial Anda.";
                            recordLoginAttempt($ip_address, $email, 0, $conn);
                        }
                    } else {
                        // Generic message
                        $notif = "Login gagal. Periksa kredensial Anda.";
                        recordLoginAttempt($ip_address, $email, 0, $conn);
                    }
                }
            }
        }
    }
    
    // ===== REDIRECT UNTUK MENCEGAH FORM RESUBMISSION (PRG Pattern) =====
    if (!empty($notif)) {
        $_SESSION['login_error'] = $notif;
        header("Location: login.php");
        exit;
    }
    
    // Regenerate captcha setelah submit
    unset($_SESSION['captcha']);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>f.i.x.p.o.i.n.t</title>

  <link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/components.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <style>
    /* ===== LATAR BELAKANG BLUR ===== */
    body {
      background: url('images/back2.jpg') no-repeat center center fixed;
      background-size: cover;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      margin: 0;
      backdrop-filter: blur(6px);
      -webkit-backdrop-filter: blur(6px);
    }

    /* ===== KOTAK LOGIN ===== */
    .login-box {
      background: rgba(255, 255, 255, 0.93);
      border-radius: 20px;
      padding: 35px 40px;
      box-shadow: 0 8px 30px rgba(0,0,0,0.25);
      width: 100%;
      max-width: 700px;
      animation: fadeIn 0.8s ease;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .login-logo img {
      width: 150px;
      height: auto;
    }

    .form-group label {
      font-size: 14px;
      font-weight: 600;
    }

    .form-control {
      font-size: 14px;
      padding: 8px 10px;
    }

    .btn {
      font-size: 14px;
      padding: 10px 15px;
    }

    .text-muted {
      font-size: 13px;
    }

    @media (max-width: 768px) {
      .login-box {
        padding: 25px;
        margin: 15px;
      }
    }
  </style>
</head>

<body>

<div class="login-box">
  <div class="login-logo text-center mb-3">
    <img src="images/logo7.png" alt="Logo FixPoint">
  </div>

  <?php if (!empty($notif)): ?>
    <script>
      document.addEventListener("DOMContentLoaded", function() {
        Swal.fire({
          icon: 'error',
          title: 'Login Gagal',
          text: <?= json_encode($notif) ?>,
          confirmButtonColor: '#d33'
        });
      });
    </script>
  <?php endif; ?>

  <form method="POST" action="login.php" autocomplete="off">
    <!-- CSRF Token -->
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    
    <div class="form-row">
      <div class="form-group col-md-6">
        <label for="email"><i class="fas fa-envelope text-primary"></i> Email</label>
        <input type="email" name="email" id="email" class="form-control" placeholder="Masukkan email" required autocomplete="email" maxlength="255">
      </div>

      <div class="form-group col-md-6">
        <label for="password"><i class="fas fa-lock text-primary"></i> Password</label>
        <div class="input-group">
          <input type="password" name="password" id="password" class="form-control" placeholder="Masukkan password" required autocomplete="current-password" maxlength="255">
          <div class="input-group-append">
            <span class="input-group-text" onclick="togglePassword('password', 'toggleIcon')" style="cursor:pointer" tabindex="-1">
              <i class="fas fa-eye" id="toggleIcon"></i>
            </span>
          </div>
        </div>
      </div>

      <div class="form-group col-md-8">
        <label for="captcha_input"><i class="fas fa-shield-alt text-primary"></i> Kode Keamanan</label>
        <div class="d-flex align-items-center mb-2">
          <img src="captcha.php" id="captcha-img" alt="Captcha" style="border-radius: 5px; height: 38px;">
          <a href="#" onclick="reloadCaptcha(); return false;" class="ml-3">🔄 Muat Ulang</a>
        </div>
        <input type="text" name="captcha_input" id="captcha_input" class="form-control" placeholder="Masukkan kode di atas" required autocomplete="off" maxlength="6">
      </div>

      <div class="form-group col-md-4 d-flex align-items-end">
        <button type="submit" class="btn btn-primary btn-block shadow-sm w-100" id="loginBtn">
          <i class="fas fa-sign-in-alt mr-1"></i> Login
        </button>
      </div>
    </div>
  </form>

  <div class="text-center mt-2">
    <a href="#" data-toggle="modal" data-target="#modalForgot">
      Lupa Password?
      <i class="fas fa-question-circle text-danger" title="Cara reset password"></i>
    </a>
  </div>

  <div class="text-center mt-3">
    Belum punya akun? <a href="#" data-toggle="modal" data-target="#modalRegister">Daftar di sini</a>
  </div>

  <hr>
  <div class="text-center text-muted">
    &copy; <?= date('Y') ?> FixPoint, V. 2. - .29.12.2025<br>
    Info Trouble: <strong>M. Wira</strong> - <a href="tel:+6282177856209">0821-7784-6209</a>
  </div>
</div>

<!-- MODAL REGISTER -->
<div class="modal fade" id="modalRegister" tabindex="-1" role="dialog" aria-labelledby="modalRegisterLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <form method="POST" action="proses_register.php" class="modal-content" autocomplete="off">
      <!-- CSRF Token -->
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-user-plus mr-2"></i> Daftar Akun Baru</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="form-row">
        <div class="form-group col-md-6">
    <label>NIK / NIP Karyawan <small class="text-muted">(Bukan NIK KTP)</small></label>
    <input type="text"name="nik"class="form-control"requiredmaxlength="30"pattern="[A-Za-z0-9]+"title="NIK/NIP wajib diisi dan hanya boleh berisi huruf dan angka">
</div>

          <div class="form-group col-md-6">
            <label>Nama Lengkap</label>
            <input type="text" name="nama" class="form-control" required maxlength="100">
          </div>
          <div class="form-group col-md-6">
            <label>Jabatan</label>
            <select name="jabatan" class="form-control" required>
              <option value="">Pilih Jabatan</option>
              <?php
              $jabatan = $conn->query("SELECT nama_jabatan FROM jabatan ORDER BY nama_jabatan");
              while($r = $jabatan->fetch_assoc()):
              ?>
                <option value="<?= htmlspecialchars($r['nama_jabatan']) ?>"><?= htmlspecialchars($r['nama_jabatan']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="form-group col-md-6">
            <label>Unit Kerja</label>
            <select name="unit_kerja" class="form-control" required>
              <option value="">Pilih Unit</option>
              <?php
              $unit = $conn->query("SELECT nama_unit FROM unit_kerja ORDER BY nama_unit");
              while($r = $unit->fetch_assoc()):
              ?>
                <option value="<?= htmlspecialchars($r['nama_unit']) ?>"><?= htmlspecialchars($r['nama_unit']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="form-group col-md-6">
            <label>Email</label>
            <input type="email" name="email" class="form-control" required maxlength="255">
          </div>
          <div class="form-group col-md-6">
            <label>Password</label>
            <div class="input-group">
              <input type="password" name="password" id="reg-password" class="form-control" required minlength="8" maxlength="255">
              <div class="input-group-append">
                <span class="input-group-text" onclick="togglePassword('reg-password', 'reg-eye')" style="cursor:pointer" tabindex="-1">
                  <i class="fas fa-eye" id="reg-eye"></i>
                </span>
              </div>
            </div>
            <small class="form-text text-muted">Minimal 8 karakter</small>
          </div>
          <div class="form-group col-md-6">
            <label>Konfirmasi Password</label>
            <div class="input-group">
              <input type="password" name="konfirmasi_password" id="reg-confirm" class="form-control" required minlength="8" maxlength="255">
              <div class="input-group-append">
                <span class="input-group-text" onclick="togglePassword('reg-confirm', 'reg-confirm-eye')" style="cursor:pointer" tabindex="-1">
                  <i class="fas fa-eye" id="reg-confirm-eye"></i>
                </span>
              </div>
            </div>
          </div>
          <div class="form-group col-md-6">
            <label>Atasan Langsung</label>
            <select name="atasan_id" class="form-control">
              <option value="">Pilih Atasan</option>
              <?php
              $atasan = $conn->query("SELECT id, nama FROM users WHERE status = 'active' ORDER BY nama");
              while($r = $atasan->fetch_assoc()):
              ?>
                <option value="<?= htmlspecialchars($r['id']) ?>"><?= htmlspecialchars($r['nama']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-user-plus mr-1"></i> Daftar Sekarang
        </button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL LUPA PASSWORD -->
<div class="modal fade" id="modalForgot" tabindex="-1" role="dialog" aria-labelledby="modalForgotLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form method="POST" action="proses_forgot.php" class="modal-content" autocomplete="off">
      <!-- CSRF Token -->
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-key mr-2"></i> Lupa Password</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <p>Masukkan email Anda untuk mengatur ulang password.</p>
        <div class="form-group mt-3">
          <label><i class="fas fa-envelope text-primary"></i> Email</label>
          <input type="email" name="email" class="form-control" placeholder="Masukkan email Anda" required maxlength="255">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane mr-1"></i> Kirim Link Reset</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
      </div>
    </form>
  </div>
</div>

<script src="assets/modules/jquery.min.js"></script>
<script src="assets/modules/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
function togglePassword(inputId, iconId) {
  const input = document.getElementById(inputId);
  const icon = document.getElementById(iconId);
  if (input.type === "password") {
    input.type = "text";
    icon.classList.replace("fa-eye", "fa-eye-slash");
  } else {
    input.type = "password";
    icon.classList.replace("fa-eye-slash", "fa-eye");
  }
}

function reloadCaptcha() {
  document.getElementById('captcha-img').src = 'captcha.php?' + Date.now();
}

// Prevent multiple form submissions
document.querySelector('form').addEventListener('submit', function() {
  const btn = document.getElementById('loginBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Mohon tunggu...';
});

// Clear password fields on page load
window.addEventListener('load', function() {
  document.getElementById('password').value = '';
  if(document.getElementById('reg-password')) {
    document.getElementById('reg-password').value = '';
    document.getElementById('reg-confirm').value = '';
  }
});
</script>

</body>
</html>