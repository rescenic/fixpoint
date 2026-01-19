<?php
session_start();
require 'koneksi.php';

date_default_timezone_set('Asia/Jakarta');

// ================= CEK OTP SUDAH DIVERIFIKASI
if (
    empty($_SESSION['reset_verified']) ||
    $_SESSION['reset_verified'] !== true ||
    empty($_SESSION['reset_email'])
) {
    header("Location: login.php");
    exit;
}

// ================= CSRF TOKEN
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error   = $_SESSION['reset_error'] ?? '';
$success = $_SESSION['reset_success'] ?? '';
$email   = $_SESSION['reset_email'];

unset($_SESSION['reset_error'], $_SESSION['reset_success']);

// ================= PROSES RESET PASSWORD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
        $_POST['csrf_token'] !== $_SESSION['csrf_token']
    ) {
        $_SESSION['reset_error'] = "Token tidak valid.";
        header("Location: reset_password.php");
        exit;
    }

    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        $_SESSION['reset_error'] = "Password minimal 8 karakter.";
        header("Location: reset_password.php");
        exit;
    }

    if ($password !== $confirm) {
        $_SESSION['reset_error'] = "Konfirmasi password tidak sama.";
        header("Location: reset_password.php");
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("
        UPDATE users 
        SET password_hash = ?
        WHERE email = ?
        LIMIT 1
    ");
    $stmt->bind_param("ss", $hash, $email);
    $stmt->execute();
    $stmt->close();

    unset($_SESSION['reset_verified'], $_SESSION['reset_email']);

    $_SESSION['reset_success'] = "Password berhasil diperbarui. Silakan login.";
    header("Location: reset_password.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Reset Password</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <style>
    body {
      background: url('images/back2.jpg') no-repeat center center fixed;
      background-size: cover;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      backdrop-filter: blur(6px);
    }
    .reset-box {
      background: rgba(255,255,255,0.95);
      border-radius: 20px;
      padding: 40px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.25);
      width: 100%;
      max-width: 500px;
    }
  </style>
</head>
<body>

<div class="reset-box">
  <div class="text-center mb-4">
    <h4>🔐 Reset Password</h4>
    <p class="text-muted">
      Akun: <strong><?= htmlspecialchars($email) ?></strong>
    </p>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="mb-3">
      <label>Password Baru</label>
      <div class="input-group">
        <input type="password" name="password" id="password" class="form-control" required minlength="8">
        <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('password')">
          <i class="fas fa-eye"></i>
        </button>
      </div>
      <small class="text-muted">Minimal 8 karakter</small>
    </div>

    <div class="mb-4">
      <label>Ulangi Password</label>
      <div class="input-group">
        <input type="password" name="confirm_password" id="confirm" class="form-control" required minlength="8">
        <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('confirm')">
          <i class="fas fa-eye"></i>
        </button>
      </div>
    </div>

    <button class="btn btn-primary w-100 py-2">
      <i class="fas fa-save"></i> Simpan Password Baru
    </button>
  </form>

  <div class="text-center mt-4">
    <a href="login.php">← Kembali ke Login</a>
  </div>
</div>

<?php if ($success): ?>
<script>
Swal.fire({
  icon: 'success',
  title: 'Berhasil 🎉',
  text: '<?= htmlspecialchars($success) ?>',
  timer: 2500,
  showConfirmButton: false
}).then(() => {
  window.location.href = 'login.php';
});
</script>
<?php endif; ?>

<script>
function togglePassword(id) {
  const input = document.getElementById(id);
  input.type = input.type === 'password' ? 'text' : 'password';
}
</script>

</body>
</html>
