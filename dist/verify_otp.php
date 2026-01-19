<?php
session_start();
require 'koneksi.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = isset($_SESSION['forgot_error']) ? $_SESSION['forgot_error'] : "";
$success = isset($_SESSION['forgot_success']) ? $_SESSION['forgot_success'] : "";
$reset_email = isset($_SESSION['reset_email']) ? $_SESSION['reset_email'] : "";

unset($_SESSION['forgot_error']);
unset($_SESSION['forgot_success']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Verifikasi OTP - FixPoint</title>
  <link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
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
    .verify-box {
      background: rgba(255,255,255,0.95);
      border-radius: 20px;
      padding: 40px;
      box-shadow: 0 8px 30px rgba(0,0,0,0.2);
      width: 100%;
      max-width: 500px;
    }
    .otp-inputs {
      display: flex;
      gap: 10px;
      justify-content: center;
      margin: 30px 0;
    }
    .otp-input {
      width: 50px;
      height: 55px;
      text-align: center;
      font-size: 24px;
      font-weight: bold;
      border: 2px solid #ddd;
      border-radius: 10px;
    }
    .otp-input:focus {
      border-color: #6366f1;
      outline: none;
    }
    .btn-primary {
      background: linear-gradient(135deg, #6366f1, #8b5cf6);
      border: none;
      padding: 12px;
      border-radius: 10px;
    }
    .timer {
      text-align: center;
      font-size: 18px;
      font-weight: bold;
      color: #6366f1;
      margin: 20px 0;
    }
  </style>
</head>
<body>

<div class="verify-box">
  <div class="text-center mb-4">
    <h4>🔐 Verifikasi OTP</h4>
    <p class="text-muted">Masukkan kode 6 digit yang dikirim ke email</p>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= $error ?></div>
  <?php endif; ?>

  <?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= $success ?></div>
  <?php endif; ?>

  <form method="POST" action="proses_verify_otp.php" id="otpForm">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    
    <div class="form-group">
      <label>Email</label>
      <input type="email"name="email"class="form-control"value="<?= htmlspecialchars($reset_email) ?>"readonly></div>

    <label class="text-center d-block">Kode OTP</label>
    <div class="otp-inputs">
      <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" required>
      <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" required>
      <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" required>
      <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" required>
      <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" required>
      <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" required>
    </div>
    <input type="hidden" name="otp" id="otp">

    <div class="timer">⏱️ <span id="countdown">10:00</span></div>

    <button type="submit" class="btn btn-primary btn-block">
      <i class="fas fa-check"></i> Verifikasi
    </button>
  </form>

  <div class="text-center mt-3">
    <a href="login.php">← Kembali ke Login</a>
  </div>
</div>

<script src="assets/modules/jquery.min.js"></script>
<script src="assets/modules/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
// OTP Input Handler
const inputs = document.querySelectorAll('.otp-input');
inputs.forEach((input, index) => {
  input.addEventListener('input', function() {
    if (this.value.length === 1 && index < inputs.length - 1) {
      inputs[index + 1].focus();
    }
    updateOTP();
  });
  
  input.addEventListener('keydown', function(e) {
    if (e.key === 'Backspace' && !this.value && index > 0) {
      inputs[index - 1].focus();
    }
  });
  
  input.addEventListener('paste', function(e) {
    e.preventDefault();
    const paste = e.clipboardData.getData('text').trim();
    if (/^\d{6}$/.test(paste)) {
      paste.split('').forEach((char, i) => {
        if (inputs[i]) inputs[i].value = char;
      });
      updateOTP();
    }
  });
});

function updateOTP() {
  document.getElementById('otp').value = Array.from(inputs).map(i => i.value).join('');
}

// Countdown
let time = 600;
setInterval(() => {
  if (time > 0) {
    time--;
    const min = Math.floor(time / 60);
    const sec = time % 60;
    document.getElementById('countdown').textContent = min + ':' + (sec < 10 ? '0' : '') + sec;
  } else {
    document.getElementById('countdown').textContent = 'Kadaluarsa';
  }
}, 1000);

inputs[0].focus();
</script>

</body>
</html>