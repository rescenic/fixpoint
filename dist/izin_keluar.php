<?php
// Aktifkan error reporting untuk debug (matikan di production)
error_reporting(E_ALL);
ini_set('display_errors', 0);       // jangan tampil ke layar
ini_set('log_errors', 1);           // log ke error_log server
ob_start();                          // buffer output agar header() tidak gagal

session_start();
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

function sendTelegram($conn, $pesan_html, $tujuan = 'hrd') {
    $token = mysqli_fetch_assoc(
        mysqli_query($conn, "SELECT nilai FROM setting WHERE nama='telegram_bot_token' LIMIT 1")
    )['nilai'] ?? '';

    if ($tujuan === 'it') {
        $setting_chat = 'telegram_chat_id';
    } else {
        $setting_chat = 'telegram_chat_id_hrd';
    }

    $chat_id = mysqli_fetch_assoc(
        mysqli_query($conn, "SELECT nilai FROM setting WHERE nama='$setting_chat' LIMIT 1")
    )['nilai'] ?? '';

    if (empty($token) || empty($chat_id)) return false;

    $url  = "https://api.telegram.org/bot{$token}/sendMessage";
    $data = [
        'chat_id'    => trim($chat_id),
        'text'       => $pesan_html,
        'parse_mode' => 'HTML'
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 10
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

$user_id = $_SESSION['user_id'] ?? 0;
if ($user_id == 0) {
    echo "<script>alert('Anda belum login.'); window.location.href='login.php';</script>";
    exit;
}

$current_file = basename(__FILE__);

$query = "SELECT 1 FROM akses_menu 
          JOIN menu ON akses_menu.menu_id = menu.id 
          WHERE akses_menu.user_id = ? AND menu.file_menu = ?";
$stmt = $conn->prepare($query);
if (!$stmt) die("Prepare failed: " . $conn->error);
$stmt->bind_param("is", $user_id, $current_file);
$stmt->execute();
$result = $stmt->get_result();
if (!$result || $result->num_rows == 0) {
    echo "<script>alert('Anda tidak memiliki akses ke halaman ini.'); window.location.href='dashboard.php';</script>";
    exit;
}

$qUser = $conn->prepare("SELECT u.nik, u.nama, u.jabatan, u.unit_kerja, a.nama AS nama_atasan 
                          FROM users u
                          LEFT JOIN users a ON u.atasan_id = a.id
                          WHERE u.id = ?");
$qUser->bind_param("i", $user_id);
$qUser->execute();
$user = $qUser->get_result()->fetch_assoc();

// ============================================================
// PROSES: Simpan izin keluar baru
// ============================================================
if (isset($_POST['simpan'])) {
    $tanggal     = $_POST['tanggal']     ?? '';
    $jam_keluar  = $_POST['jam_keluar']  ?? '';
    $jam_kembali = $_POST['jam_kembali'] ?? null;
    $keperluan   = trim($_POST['keperluan'] ?? '');
    $atasan_langsung = $user['nama_atasan'] ?? '';

    if (empty($tanggal) || empty($jam_keluar) || empty($keperluan)) {
        $_SESSION['flash_message'] = "Tanggal izin, jam keluar, dan keperluan wajib diisi!";
        header("Location: izin_keluar.php");
        exit;
    }

    $insert = $conn->prepare("INSERT INTO izin_keluar 
        (user_id, nik, nama, jabatan, bagian, atasan_langsung, tanggal, jam_keluar, jam_kembali, keperluan, status_atasan, status_sdm, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', NOW())");
    $insert->bind_param(
        "isssssssss",
        $user_id,
        $user['nik'],
        $user['nama'],
        $user['jabatan'],
        $user['unit_kerja'],
        $atasan_langsung,
        $tanggal,
        $jam_keluar,
        $jam_kembali,
        $keperluan
    );

    if ($insert->execute()) {
        $pesanTG  = "<b>📝 IZIN KELUAR RUMAH SAKIT</b>\n\n";
        $pesanTG .= "👤 <b>Nama:</b> {$user['nama']}\n";
        $pesanTG .= "🆔 <b>NIK:</b> {$user['nik']}\n";
        $pesanTG .= "💼 <b>Jabatan:</b> {$user['jabatan']}\n";
        $pesanTG .= "🏢 <b>Unit:</b> {$user['unit_kerja']}\n\n";
        $pesanTG .= "🕒 <b>Jam Keluar:</b> {$jam_keluar} WIB\n";
        $pesanTG .= "⏰ <b>Estimasi Kembali:</b> " . ($jam_kembali ?: "-") . "\n";
        $pesanTG .= "📌 <b>Keperluan:</b>\n<pre>$keperluan</pre>\n";
        $pesanTG .= "👔 <b>Atasan:</b> {$atasan_langsung}\n";
        $pesanTG .= "📅 <b>Tanggal Izin:</b> " . date('d-m-Y', strtotime($tanggal));

        sendTelegram($conn, $pesanTG);

        $_SESSION['flash_message'] = "✅ Data izin keluar berhasil disimpan & Telegram terkirim.";
    } else {
        $_SESSION['flash_message'] = "❌ Gagal menyimpan data izin keluar: " . $insert->error;
    }

    header("Location: izin_keluar.php?tab=data");
    exit;
}

// ============================================================
// PROSES: Update jam kembali oleh karyawan sendiri
// ============================================================
if (isset($_POST['update_kembali'])) {
    $izin_id            = (int)($_POST['izin_id'] ?? 0);
    $jam_kembali_real   = date('Y-m-d H:i:s'); // otomatis waktu sekarang
    $keterangan_kembali = trim($_POST['keterangan_kembali'] ?? '');

    if (empty($keterangan_kembali)) {
        $_SESSION['flash_message'] = "❌ Keterangan kembali wajib diisi.";
        header("Location: izin_keluar.php?tab=data");
        exit;
    }

    // Validasi: milik user ini, sudah disetujui SDM, belum ada jam_kembali_real
    $cek = $conn->prepare("
        SELECT id, nama, nik, jabatan, bagian AS unit_kerja, jam_keluar, jam_kembali, keperluan, tanggal
        FROM izin_keluar 
        WHERE id = ? 
          AND user_id = ? 
          AND status_sdm = 'disetujui'
          AND (jam_kembali_real IS NULL OR jam_kembali_real = '')
    ");

    if (!$cek) {
        $_SESSION['flash_message'] = "❌ Terjadi kesalahan sistem: " . $conn->error;
        header("Location: izin_keluar.php?tab=data");
        exit;
    }
    $cek->bind_param("ii", $izin_id, $user_id);
    $cek->execute();
    $izin_data = $cek->get_result()->fetch_assoc();

    if (!$izin_data) {
        $_SESSION['flash_message'] = "❌ Izin tidak ditemukan atau sudah pernah dikonfirmasi kembali.";
        header("Location: izin_keluar.php?tab=data");
        exit;
    }

    $upd = $conn->prepare("
        UPDATE izin_keluar 
        SET jam_kembali_real   = ?,
            keterangan_kembali = ?
        WHERE id = ? AND user_id = ?
    ");

    if (!$upd) {
        $_SESSION['flash_message'] = "❌ Terjadi kesalahan sistem: " . $conn->error;
        header("Location: izin_keluar.php?tab=data");
        exit;
    }
    $upd->bind_param("ssii", $jam_kembali_real, $keterangan_kembali, $izin_id, $user_id);

    if ($upd->execute() && $upd->affected_rows > 0) {

        // Kirim notifikasi Telegram ke HRD/SDM
        $pesanTG  = "<b>✅ KONFIRMASI KEMBALI - IZIN KELUAR</b>\n\n";
        $pesanTG .= "👤 <b>Nama:</b> {$izin_data['nama']}\n";
        $pesanTG .= "🆔 <b>NIK:</b> {$izin_data['nik']}\n";
        $pesanTG .= "💼 <b>Jabatan:</b> {$izin_data['jabatan']}\n";
        $pesanTG .= "🏢 <b>Unit:</b> {$izin_data['unit_kerja']}\n\n";
        $pesanTG .= "📅 <b>Tanggal:</b> " . date('d-m-Y', strtotime($izin_data['tanggal'])) . "\n";
        $pesanTG .= "🕒 <b>Jam Keluar:</b> {$izin_data['jam_keluar']} WIB\n";
        $pesanTG .= "⏰ <b>Estimasi Kembali:</b> " . ($izin_data['jam_kembali'] ?: "-") . " WIB\n";
        $pesanTG .= "🏠 <b>Jam Kembali Real:</b> " . date('H:i', strtotime($jam_kembali_real)) . " WIB\n\n";
        $pesanTG .= "📝 <b>Keterangan:</b> {$keterangan_kembali}\n\n";
        $pesanTG .= "📌 <b>Keperluan:</b> {$izin_data['keperluan']}\n\n";
        $pesanTG .= "⚠️ <i>Karyawan diminta mengirimkan Share Lokasi sebagai konfirmasi sudah kembali ke area RS.</i>";

        sendTelegram($conn, $pesanTG, 'hrd');

        // Set flag untuk tampilkan modal pengingat share lokasi
        $_SESSION['show_shareloc_modal'] = true;
        $_SESSION['flash_message'] = "✅ Jam kembali berhasil dikonfirmasi. Silakan share lokasi ke HRD/SDM.";
    } else {
        $_SESSION['flash_message'] = "❌ Gagal menyimpan konfirmasi kembali.";
    }

    header("Location: izin_keluar.php?tab=data");
    exit;
}

// ============================================================
// PROSES: Batalkan izin
// ============================================================
if (isset($_POST['batal_izin'])) {
    $izin_id = (int)$_POST['izin_id'];

    $cek = $conn->prepare("
        SELECT id FROM izin_keluar 
        WHERE id=? AND user_id=?
          AND status_atasan != 'disetujui'
          AND status_sdm    != 'disetujui'
    ");
    $cek->bind_param("ii", $izin_id, $user_id);
    $cek->execute();

    if ($cek->get_result()->num_rows == 0) {
        $_SESSION['flash_message'] = "❌ Izin tidak dapat dibatalkan (sudah disetujui).";
        header("Location: izin_keluar.php?tab=data");
        exit;
    }

    $update = $conn->prepare("
        UPDATE izin_keluar 
        SET status_atasan      = 'dibatalkan',
            status_sdm         = 'dibatalkan',
            waktu_dibatalkan   = NOW()
        WHERE id=? AND user_id=?
    ");
    $update->bind_param("ii", $izin_id, $user_id);

    $_SESSION['flash_message'] = $update->execute()
        ? "✅ Izin keluar berhasil dibatalkan."
        : "❌ Gagal membatalkan izin keluar.";

    header("Location: izin_keluar.php?tab=data");
    exit;
}

// ============================================================
// Filter & Pagination Data Tersimpan
// ============================================================
$tgl_awal  = $_GET['tgl_awal']  ?? date('Y-m-d');
$tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');

$limit = 10;
$page  = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$start = ($page - 1) * $limit;

$stmtCount = $conn->prepare("SELECT COUNT(*) as total FROM izin_keluar WHERE user_id=? AND tanggal BETWEEN ? AND ?");
$stmtCount->bind_param("iss", $user_id, $tgl_awal, $tgl_akhir);
$stmtCount->execute();
$total_data = $stmtCount->get_result()->fetch_assoc()['total'];
$total_page = ceil($total_data / $limit);

$stmtIzin = $conn->prepare("
    SELECT * FROM izin_keluar 
    WHERE user_id=? AND tanggal BETWEEN ? AND ?
    ORDER BY created_at DESC LIMIT ?,?
");
$stmtIzin->bind_param("issii", $user_id, $tgl_awal, $tgl_akhir, $start, $limit);
$stmtIzin->execute();
$data_izin = $stmtIzin->get_result();

function buildPaginationUrl($page, $tgl_awal, $tgl_akhir) {
    return 'izin_keluar.php?' . http_build_query(['tab' => 'data', 'page' => $page, 'tgl_awal' => $tgl_awal, 'tgl_akhir' => $tgl_akhir]);
}

// Ambil kontak HRD dari setting untuk ditampilkan di modal
$qHrdContact = $conn->query("SELECT nilai FROM setting WHERE nama='hrd_whatsapp' LIMIT 1");
$hrd_wa = $qHrdContact ? ($qHrdContact->fetch_assoc()['nilai'] ?? '') : '';
$qHrdName = $conn->query("SELECT nilai FROM setting WHERE nama='hrd_nama' LIMIT 1");
$hrd_nama = $qHrdName ? ($qHrdName->fetch_assoc()['nilai'] ?? 'HRD/SDM') : 'HRD/SDM';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Form Izin Keluar</title>
<link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/components.css">
<style>
.flash-center {
    position: fixed; top: 20%; left: 50%; transform: translate(-50%,-50%);
    z-index: 1050; min-width: 300px; max-width: 90%;
    text-align: center; padding: 15px; border-radius: 8px;
    font-weight: 500; box-shadow: 0 5px 15px rgba(0,0,0,0.3);
}
.izin-table { font-size: 13px; white-space: nowrap; }
.izin-table th, .izin-table td { padding: 6px 10px; vertical-align: middle; }
.izin-table thead th { color: #fff !important; background-color: #000 !important; }
.pagination-info { font-size: 14px; color: #666; margin-bottom: 10px; }
.pagination .page-item.active .page-link { background-color: #6777ef; border-color: #6777ef; }
.pagination .page-link { color: #6777ef; }

/* Modal Share Lokasi */
.shareloc-steps { counter-reset: step-counter; list-style: none; padding: 0; }
.shareloc-steps li {
    counter-increment: step-counter;
    position: relative;
    padding: 10px 10px 10px 52px;
    margin-bottom: 10px;
    background: #f8f9fc;
    border-radius: 10px;
    border-left: 4px solid #6777ef;
    font-size: 0.93rem;
}
.shareloc-steps li::before {
    content: counter(step-counter);
    position: absolute;
    left: 12px; top: 50%; transform: translateY(-50%);
    width: 28px; height: 28px;
    background: #6777ef; color: white;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 0.85rem;
}
.shareloc-alert-box {
    background: linear-gradient(135deg, #fff3cd, #ffe082);
    border: 2px solid #ffc107;
    border-radius: 12px;
    padding: 16px 18px;
    margin-bottom: 16px;
}
.shareloc-contact-box {
    background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
    border: 2px solid #4caf50;
    border-radius: 10px;
    padding: 14px 16px;
}
.btn-whatsapp {
    background: #25D366; border: none; color: white;
    padding: 10px 20px; border-radius: 8px;
    font-weight: 600; font-size: 0.95rem;
    transition: all 0.2s;
}
.btn-whatsapp:hover { background: #1ebe5d; color: white; transform: translateY(-1px); }

/* Tombol update kembali */
.btn-update-kembali {
    background: linear-gradient(135deg, #17a2b8, #0d7a8a);
    border: none; color: white;
    font-size: 0.78rem; padding: 4px 8px;
    border-radius: 6px; transition: all 0.2s;
}
.btn-update-kembali:hover { transform: translateY(-1px); box-shadow: 0 3px 8px rgba(23,162,184,0.4); color: white; }
</style>
</head>
<body>
<div id="app">
<div class="main-wrapper main-wrapper-1">
<?php include 'navbar.php'; ?>
<?php include 'sidebar.php'; ?>
<div class="main-content">
<section class="section">
<div class="section-body">

<?php if (isset($_SESSION['flash_message'])): ?>
<div class="alert alert-info flash-center" id="flashMsg">
    <?= htmlspecialchars($_SESSION['flash_message']) ?>
</div>
<?php unset($_SESSION['flash_message']); ?>
<?php endif; ?>

<div class="card">
  <div class="card-header d-flex align-items-center">
    <h4 class="mb-0">Form Izin Keluar</h4>
    <button type="button" class="btn btn-link text-danger ml-2 p-0"
            data-toggle="modal" data-target="#prosedurModal" title="Lihat Prosedur">
      <i class="fas fa-question-circle fa-lg"></i>
    </button>
  </div>
  <div class="card-body">
    <ul class="nav nav-tabs" id="izinTab" role="tablist">
      <li class="nav-item">
        <a class="nav-link active" id="input-tab" data-toggle="tab" href="#input" role="tab">
          <i class="fas fa-edit mr-1"></i>Input Data
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" id="data-tab" data-toggle="tab" href="#data" role="tab">
          <i class="fas fa-list mr-1"></i>Data Tersimpan
        </a>
      </li>
    </ul>

    <div class="tab-content mt-3">

      <!-- ===== TAB INPUT DATA ===== -->
      <div class="tab-pane fade show active" id="input" role="tabpanel">
        <form method="POST" novalidate>
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>Tanggal Izin <span class="text-danger">*</span></label>
                <input type="date" name="tanggal" class="form-control"
                       value="<?= date('Y-m-d') ?>" required>
                <small class="text-muted">Tanggal izin keluar</small>
              </div>
              <div class="form-group">
                <label>Jam Keluar <span class="text-danger">*</span></label>
                <input type="time" name="jam_keluar" class="form-control" required>
                <small class="text-muted">00:00 s/d 11:59 = AM &nbsp;|&nbsp; 12:00 s/d 23:59 = PM</small>
              </div>
              <div class="form-group">
                <label>Jam Kembali (Estimasi)</label>
                <input type="time" name="jam_kembali" class="form-control">
                <small class="text-muted">00:00 s/d 11:59 = AM &nbsp;|&nbsp; 12:00 s/d 23:59 = PM</small>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Keperluan / Alasan <span class="text-danger">*</span></label>
                <textarea name="keperluan" class="form-control" rows="5"
                          placeholder="Contoh: Keperluan keluarga, urusan bank, dll." required></textarea>
              </div>
            </div>
          </div>
          <div class="text-right">
            <button type="submit" name="simpan" class="btn btn-primary">
              <i class="fas fa-save mr-1"></i>Simpan
            </button>
          </div>
        </form>
      </div>

      <!-- ===== TAB DATA TERSIMPAN ===== -->
      <div class="tab-pane fade" id="data" role="tabpanel">
        <!-- Filter -->
        <form method="GET" class="form-inline mb-3">
          <input type="hidden" name="tab" value="data">
          <label class="mr-2">Dari</label>
          <input type="date" name="tgl_awal" class="form-control mr-2"
                 value="<?= htmlspecialchars($tgl_awal) ?>">
          <label class="mr-2">Sampai</label>
          <input type="date" name="tgl_akhir" class="form-control mr-2"
                 value="<?= htmlspecialchars($tgl_akhir) ?>">
          <button type="submit" class="btn btn-primary mr-2">
            <i class="fas fa-search"></i> Tampilkan
          </button>
          <a href="izin_keluar.php?tab=data" class="btn btn-secondary">
            <i class="fas fa-redo"></i> Reset
          </a>
        </form>

        <div class="pagination-info">
          Menampilkan <?= min($start + 1, $total_data) ?> - <?= min($start + $limit, $total_data) ?>
          dari <?= $total_data ?> data
        </div>

        <div class="table-responsive">
          <table class="table table-bordered izin-table">
            <thead>
              <tr class="text-center">
                <th>No</th>
                <th>Tanggal</th>
                <th>Jam Keluar</th>
                <th>Jam Kembali<br>(Estimasi)</th>
                <th>Jam Kembali<br>(Real)</th>
                <th>Keperluan</th>
                <th>Waktu Input</th>
                <th>ACC Atasan</th>
                <th>ACC SDM</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
            <?php if ($data_izin && $data_izin->num_rows > 0):
                $no = $start + 1;
                while ($izin = $data_izin->fetch_assoc()): ?>
              <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td><?= date('d-m-Y', strtotime($izin['tanggal'])) ?></td>
                <td class="text-center"><?= htmlspecialchars($izin['jam_keluar']) ?></td>
                <td class="text-center">
                  <?= !empty($izin['jam_kembali']) ? htmlspecialchars($izin['jam_kembali']) : '-' ?>
                </td>

                <!-- Jam Kembali Real -->
                <td class="text-center">
                  <?php if (!empty($izin['jam_kembali_real'])): ?>
                    <span class="badge badge-success px-2 py-1">
                      <i class="fas fa-check-circle mr-1"></i>
                      <?= date('H:i', strtotime($izin['jam_kembali_real'])) ?>
                    </span>
                    <?php if (!empty($izin['keterangan_kembali'])): ?>
                      <br><small class="text-muted"><i><?= htmlspecialchars($izin['keterangan_kembali']) ?></i></small>
                    <?php endif; ?>

                  <?php elseif ($izin['status_sdm'] === 'disetujui'): ?>
                    <!-- Sudah disetujui SDM, belum konfirmasi kembali → tampilkan tombol -->
                    <button type="button"
                            class="btn btn-update-kembali btn-konfirmasi-kembali"
                            data-id="<?= $izin['id'] ?>"
                            data-jam-estimasi="<?= htmlspecialchars($izin['jam_kembali'] ?? '-') ?>"
                            data-jam-keluar="<?= htmlspecialchars($izin['jam_keluar']) ?>"
                            data-toggle="modal"
                            data-target="#modalKembali">
                      <i class="fas fa-map-marker-alt mr-1"></i>Konfirmasi Kembali
                    </button>

                  <?php else: ?>
                    <span class="badge badge-warning">
                      <i class="fas fa-clock mr-1"></i>Menunggu ACC
                    </span>
                  <?php endif; ?>
                </td>

                <td><?= htmlspecialchars($izin['keperluan']) ?></td>
                <td class="text-center">
                  <?= date('d-m-Y H:i', strtotime($izin['created_at'])) ?>
                </td>

                <!-- Status Atasan -->
                <td class="text-center">
                  <?php
                  $clr = ['disetujui'=>'success','ditolak'=>'danger','dibatalkan'=>'secondary'];
                  $badge = $clr[$izin['status_atasan']] ?? 'warning';
                  echo "<span class='badge badge-{$badge}'>".ucfirst($izin['status_atasan'])."</span><br>";
                  echo "<small>".($izin['waktu_acc_atasan'] ? date('d-m-Y H:i', strtotime($izin['waktu_acc_atasan'])) : '-')."</small>";
                  ?>
                </td>

                <!-- Status SDM -->
                <td class="text-center">
                  <?php
                  $badge2 = $clr[$izin['status_sdm']] ?? 'warning';
                  echo "<span class='badge badge-{$badge2}'>".ucfirst($izin['status_sdm'])."</span><br>";
                  echo "<small>".($izin['waktu_acc_sdm'] ? date('d-m-Y H:i', strtotime($izin['waktu_acc_sdm'])) : '-')."</small>";
                  ?>
                </td>

                <!-- Aksi -->
                <td class="text-center">
                  <a href="cetak_izin_keluar.php?id=<?= $izin['id'] ?>"
                     target="_blank" class="btn btn-sm btn-info mb-1">
                    <i class="fas fa-print"></i> Cetak
                  </a>

                  <?php if ($izin['status_atasan'] != 'disetujui' || $izin['status_sdm'] != 'disetujui'): ?>
                    <button type="button"
                            class="btn btn-sm btn-danger mb-1"
                            data-toggle="modal"
                            data-target="#batalModal"
                            data-id="<?= $izin['id'] ?>">
                      <i class="fas fa-times"></i> Batalkan
                    </button>
                    <br>
                    <small class="text-danger">
                      <i class="fas fa-exclamation-circle"></i> Belum ACC
                    </small>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; else: ?>
              <tr>
                <td colspan="10" class="text-center text-muted py-4">
                  <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                  Belum ada data izin keluar pada periode ini.
                </td>
              </tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_page > 1): ?>
        <nav>
          <ul class="pagination justify-content-center">
            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
              <a class="page-link" href="<?= buildPaginationUrl($page - 1, $tgl_awal, $tgl_akhir) ?>">&laquo;</a>
            </li>
            <?php
            $sp = max(1, $page - 2); $ep = min($total_page, $page + 2);
            if ($page <= 3) $ep = min(5, $total_page);
            if ($page >= $total_page - 2) $sp = max(1, $total_page - 4);
            if ($sp > 1) { echo '<li class="page-item"><a class="page-link" href="'.buildPaginationUrl(1,$tgl_awal,$tgl_akhir).'">1</a></li>'; if ($sp > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; }
            for ($i = $sp; $i <= $ep; $i++) { $ac = ($i==$page)?'active':''; echo '<li class="page-item '.$ac.'"><a class="page-link" href="'.buildPaginationUrl($i,$tgl_awal,$tgl_akhir).'">'.$i.'</a></li>'; }
            if ($ep < $total_page) { if ($ep < $total_page - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; echo '<li class="page-item"><a class="page-link" href="'.buildPaginationUrl($total_page,$tgl_awal,$tgl_akhir).'">'.$total_page.'</a></li>'; }
            ?>
            <li class="page-item <?= ($page >= $total_page) ? 'disabled' : '' ?>">
              <a class="page-link" href="<?= buildPaginationUrl($page + 1, $tgl_awal, $tgl_akhir) ?>">&raquo;</a>
            </li>
          </ul>
        </nav>
        <?php endif; ?>

      </div><!-- end tab-pane data -->
    </div><!-- end tab-content -->
  </div><!-- end card-body -->
</div><!-- end card -->

</div>
</section>
</div>
</div>
</div>

<!-- ============================================================ -->
<!-- MODAL: Konfirmasi Kembali + Pengingat Share Lokasi           -->
<!-- ============================================================ -->
<div class="modal fade" id="modalKembali" tabindex="-1" role="dialog" aria-labelledby="modalKembaliLabel">
  <div class="modal-dialog modal-md modal-dialog-centered" role="document">
    <form method="POST" action="izin_keluar.php">
      <input type="hidden" name="update_kembali" value="1">
      <input type="hidden" name="izin_id" id="kembali_izin_id">

      <div class="modal-content" style="border-radius: 14px; overflow: hidden;">
        <div class="modal-header text-white" style="background: linear-gradient(135deg,#17a2b8,#0d7a8a);">
          <h5 class="modal-title" id="modalKembaliLabel">
            <i class="fas fa-map-marker-alt mr-2"></i>Konfirmasi Sudah Kembali
          </h5>
          <button type="button" class="close text-white" data-dismiss="modal">
            <span>&times;</span>
          </button>
        </div>

        <div class="modal-body">

          <!-- Info izin -->
          <div class="card bg-light border-0 mb-3">
            <div class="card-body py-2 px-3">
              <div class="row text-center">
                <div class="col-6 border-right">
                  <small class="text-muted d-block">Jam Keluar</small>
                  <strong id="info_jam_keluar" class="text-dark">-</strong>
                </div>
                <div class="col-6">
                  <small class="text-muted d-block">Estimasi Kembali</small>
                  <strong id="info_jam_estimasi" class="text-dark">-</strong>
                </div>
              </div>
            </div>
          </div>

          <!-- Keterangan -->
          <div class="form-group mb-3">
            <label class="font-weight-bold">
              <i class="fas fa-comment-alt mr-1 text-primary"></i>
              Keterangan Kembali <span class="text-danger">*</span>
            </label>
            <textarea name="keterangan_kembali"
                      class="form-control"
                      rows="3"
                      placeholder="Contoh: Sudah kembali ke unit kerja, siap bertugas."
                      required></textarea>
            <small class="text-muted">
              <i class="fas fa-clock mr-1"></i>
              Jam kembali akan dicatat otomatis: <strong id="jam_sekarang_text"></strong>
            </small>
          </div>

    <!-- PENGINGAT SHARE LOKASI -->
<div class="shareloc-alert-box">
  <div class="d-flex align-items-start">
    <i class="fas fa-exclamation-triangle fa-lg text-warning mr-3 mt-1"></i>
    <div>
      <strong style="font-size:0.95rem;">Informasi Penting</strong>
      <p class="mb-0 mt-1" style="font-size:0.87rem; color:#5a4a00;">
        Apabila ditemukan adanya manipulasi data jam kembali, maka hal tersebut
        akan dianggap sebagai pelanggaran terhadap ketentuan Perusahaan/Rumah Sakit
        dan akan dikenakan sanksi sesuai dengan peraturan yang berlaku.
      </p>
    </div>
  </div>
</div>

       

          <?php if (!empty($hrd_wa)): ?>
          <!-- Kontak HRD -->
          <div class="shareloc-contact-box">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
              <div>
                <small class="text-muted d-block">Kirim share lokasi ke:</small>
                <strong><?= htmlspecialchars($hrd_nama) ?></strong>
                <span class="text-muted ml-1">(<?= htmlspecialchars($hrd_wa) ?>)</span>
              </div>
              <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $hrd_wa) ?>?text=<?= urlencode('Assalamu\'alaikum, saya ' . $user['nama'] . ' (' . $user['nik'] . ') sudah kembali ke area RS. Berikut share lokasi saya.') ?>"
                 target="_blank"
                 class="btn btn-whatsapp mt-2 mt-md-0">
                <i class="fab fa-whatsapp mr-1"></i> Buka WhatsApp
              </a>
            </div>
          </div>
          <?php else: ?>
          <div class="alert alert-info py-2 mb-0">
            <i class="fas fa-info-circle mr-1"></i>
            Segera hubungi <strong>HRD/SDM</strong> dan informasikan melalui whatsapp bahwa telah kembali.
          </div>
          <?php endif; ?>

        </div><!-- end modal-body -->

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">
            <i class="fas fa-times mr-1"></i>Batal
          </button>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save mr-1"></i>Simpan & Konfirmasi Kembali
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- ============================================================ -->
<!-- MODAL: Pengingat Share Lokasi SETELAH berhasil simpan        -->
<!-- (muncul otomatis setelah redirect jika flag aktif)           -->
<!-- ============================================================ -->
<div class="modal fade" id="modalShareLokasi" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-md modal-dialog-centered" role="document">
    <div class="modal-content" style="border-radius: 14px; overflow: hidden;">
      <div class="modal-header text-white" style="background: linear-gradient(135deg,#28a745,#1d8035);">
        <h5 class="modal-title">
          <i class="fas fa-check-circle mr-2"></i>Konfirmasi Kembali Berhasil!
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <div class="modal-body text-center">

        <div style="font-size: 4rem; margin-bottom: 12px;">📍</div>
        <h5 class="font-weight-bold text-success mb-1">Jam kembali sudah tercatat!</h5>
    

        <div class="shareloc-alert-box text-left mb-3">
          <strong>⚠️ Mengapa harus menginformasikan HR/SDM?</strong>
          <ul class="mb-0 mt-2" style="font-size:0.88rem; color:#5a4a00; padding-left:18px;">
            <li>Sebagai bukti bahwa sudah kembali ke RS</li>
            <li>Memastikan jam kembali sesuai dengan estimasi</li>
            <li>Prosedur wajib sesuai aturan HR/SDM Rumah Sakit</li>
          </ul>
        </div>

      

        <?php if (!empty($hrd_wa)): ?>
        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $hrd_wa) ?>?text=<?= urlencode('Assalamu\'alaikum, saya ' . $user['nama'] . ' (' . $user['nik'] . ') sudah kembali ke area RS. Berikut share lokasi saya.') ?>"
           target="_blank"
           class="btn btn-whatsapp btn-block mb-2">
          <i class="fab fa-whatsapp mr-2"></i>Buka WhatsApp <?= htmlspecialchars($hrd_nama) ?>
        </a>
        <?php endif; ?>

      </div>
      <div class="modal-footer justify-content-center">
        <button type="button" class="btn btn-success px-4" data-dismiss="modal">
          <i class="fas fa-check mr-1"></i>Baik saya mengerti, Tutup
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Batalkan -->
<div class="modal fade" id="batalModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <form method="POST">
      <input type="hidden" name="izin_id" id="batalIzinId">
      <input type="hidden" name="batal_izin" value="1">
      <div class="modal-content">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title">
            <i class="fas fa-exclamation-triangle"></i> Konfirmasi Pembatalan
          </h5>
          <button type="button" class="close text-white" data-dismiss="modal">
            <span>&times;</span>
          </button>
        </div>
        <div class="modal-body text-center">
          <p class="mb-2">Apakah Anda yakin ingin <b class="text-danger">membatalkan izin keluar</b> ini?</p>
          <small class="text-muted">Data yang dibatalkan tidak dapat dikembalikan.</small>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-danger">
            <i class="fas fa-trash"></i> Ya, Batalkan
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Modal Prosedur -->
<div class="modal fade" id="prosedurModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">
          <i class="fas fa-info-circle"></i> Prosedur Izin Keluar Karyawan
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <h6 class="mb-2">📌 Tahap 1 : Pengajuan Izin Keluar</h6>
        <ol>
          <li>Karyawan mengisi <b>Jam Keluar</b></li>
          <li>Karyawan mengisi <b>Jam Kembali (Estimasi)</b> (opsional)</li>
          <li>Karyawan mengisi <b>Alasan / Keperluan</b></li>
          <li>Form disimpan sebagai <b>Pengajuan Izin Keluar</b></li>
        </ol>
        <hr>
        <h6 class="mb-2">📌 Tahap 2 : Persetujuan</h6>
        <ul>
          <li>Pengajuan diperiksa oleh <b>Atasan</b></li>
          <li>Status <b>ACC Atasan</b> harus <span class="text-success font-weight-bold">Disetujui ✅</span></li>
          <li>Selanjutnya diperiksa oleh <b>SDM / HRD</b></li>
          <li>Status <b>ACC SDM</b> harus <span class="text-success font-weight-bold">Disetujui ✅</span></li>
        </ul>
        <hr>
        <h6 class="mb-2">📌 Tahap 3 : Cetak & Izin Keluar</h6>
        <ul>
          <li>Jika <b>ACC Atasan</b> dan <b>ACC SDM</b> sudah disetujui</li>
          <li>Karyawan dapat klik tombol <b><i class="fas fa-print"></i> Cetak</b></li>
          <li>Surat izin ditunjukkan kepada <b>Security</b></li>
        </ul>
        <hr>
        <h6 class="mb-2">📌 Tahap 4 : Konfirmasi Kembali ke RS <span class="badge badge-info">BARU</span></h6>
        <ul>
          <li>Setelah kembali ke RS, karyawan klik tombol <b><i class="fas fa-map-marker-alt"></i> Konfirmasi Kembali</b></li>
          <li>Isi keterangan kembali, lalu klik <b>Simpan</b></li>
          <li>Setelah itu, <b>wajib mengirimkan Share Lokasi</b> ke <b><?= htmlspecialchars($hrd_nama) ?></b> via WhatsApp</li>
          <li>Share lokasi sebagai bukti fisik sudah berada di area RS</li>
        </ul>
        <hr>
        <h6 class="mb-2">📌 Tahap 5 : Selesai</h6>
        <ul>
          <li>SDM akan memverifikasi dan data izin keluar dinyatakan <b>Selesai</b></li>
        </ul>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<script src="assets/modules/jquery.min.js"></script>
<script src="assets/modules/popper.js"></script>
<script src="assets/modules/bootstrap/js/bootstrap.min.js"></script>
<script src="assets/modules/nicescroll/jquery.nicescroll.min.js"></script>
<script src="assets/modules/moment.min.js"></script>
<script src="assets/js/stisla.js"></script>
<script src="assets/js/scripts.js"></script>
<script src="assets/js/custom.js"></script>

<script>
$(document).ready(function () {

    // Auto hide flash
    setTimeout(function () { $('#flashMsg').fadeOut('slow'); }, 3000);

    // Aktifkan tab berdasarkan ?tab=
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('tab') === 'data') {
        $('#input-tab').removeClass('active');
        $('#input').removeClass('show active');
        $('#data-tab').addClass('active');
        $('#data').addClass('show active');
    }

    // Modal batalkan
    $('#batalModal').on('show.bs.modal', function (e) {
        $('#batalIzinId').val($(e.relatedTarget).data('id'));
    });

    // Modal konfirmasi kembali — isi data dari tombol
    $(document).on('click', '.btn-konfirmasi-kembali', function () {
        var id          = $(this).data('id');
        var jamKeluar   = $(this).data('jam-keluar') || '-';
        var jamEstimasi = $(this).data('jam-estimasi') || '-';

        // Isi hidden field dan info display
        $('#kembali_izin_id').val(id);
        $('#info_jam_keluar').text(jamKeluar !== '-' ? jamKeluar + ' WIB' : '-');
        $('#info_jam_estimasi').text(jamEstimasi !== '-' ? jamEstimasi + ' WIB' : '-');

        // Reset textarea
        $('textarea[name="keterangan_kembali"]').val('');

        // Tampilkan jam sekarang
        var now = new Date();
        var hh  = String(now.getHours()).padStart(2, '0');
        var mm  = String(now.getMinutes()).padStart(2, '0');
        $('#jam_sekarang_text').text(hh + ':' + mm + ' WIB');
    });

    // Auto-buka modal pengingat share lokasi jika flag aktif dari PHP
    <?php if (!empty($_SESSION['show_shareloc_modal'])): ?>
    <?php unset($_SESSION['show_shareloc_modal']); ?>
    setTimeout(function () {
        $('#modalShareLokasi').modal('show');
    }, 600);
    <?php endif; ?>

});
</script>
</body>
</html>