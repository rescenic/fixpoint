<?php
session_start();
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

function sendTelegram($conn, $pesan_html, $tujuan = 'hrd') {

    // BOT TOKEN
    $token = mysqli_fetch_assoc(
        mysqli_query($conn,
            "SELECT nilai FROM setting WHERE nama='telegram_bot_token' LIMIT 1"
        )
    )['nilai'] ?? '';

    // CHAT ID
    if ($tujuan === 'it') {
        $setting_chat = 'telegram_chat_id'; // IT
    } else {
        $setting_chat = 'telegram_chat_id_hrd'; // HRD DEFAULT
    }

    $chat_id = mysqli_fetch_assoc(
        mysqli_query($conn,
            "SELECT nilai FROM setting WHERE nama='$setting_chat' LIMIT 1"
        )
    )['nilai'] ?? '';

    if (empty($token) || empty($chat_id)) {
        return false;
    }

    $url = "https://api.telegram.org/bot{$token}/sendMessage";

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

// Cek akses menu
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

// Ambil data user + nama atasan
$qUser = $conn->prepare("SELECT u.nik, u.nama, u.jabatan, u.unit_kerja, a.nama AS nama_atasan 
                        FROM users u
                        LEFT JOIN users a ON u.atasan_id = a.id
                        WHERE u.id = ?");
$qUser->bind_param("i", $user_id);
$qUser->execute();
$resUser = $qUser->get_result();
$user = $resUser->fetch_assoc();

// --- Proses simpan data izin keluar ---
if (isset($_POST['simpan'])) {
    $tanggal = $_POST['tanggal'] ?? '';

    $jam_keluar = $_POST['jam_keluar'] ?? '';
    $jam_kembali = $_POST['jam_kembali'] ?? null;
    $keperluan = trim($_POST['keperluan'] ?? '');
    $atasan_langsung = $user['nama_atasan'] ?? '';


if (empty($tanggal) || empty($jam_keluar) || empty($keperluan)) {
    $_SESSION['flash_message'] = "Tanggal izin, jam keluar, dan keperluan wajib diisi!";
    header("Location: izin_keluar.php");
    exit;
}


    if (empty($jam_keluar) || empty($keperluan)) {
        $_SESSION['flash_message'] = "Jam keluar dan keperluan harus diisi!";
        header("Location: izin_keluar.php");
        exit;
    }

    // Insert data izin
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
        $pesanTG .= "⏰ <b>Estimasi Kembali:</b> ".($jam_kembali ?: "-")."\n";
        $pesanTG .= "📌 <b>Keperluan:</b>\n<pre>$keperluan</pre>\n";
        $pesanTG .= "👔 <b>Atasan:</b> {$atasan_langsung}\n";
        $pesanTG .= "📅 <b>Tanggal Izin:</b> ".date('d-m-Y', strtotime($tanggal));


        sendTelegram($conn, $pesanTG);

        $_SESSION['flash_message'] = "✅ Data izin keluar berhasil disimpan & Telegram terkirim.";
    } else {
        $_SESSION['flash_message'] = "❌ Gagal menyimpan data izin keluar: " . $insert->error;
    }

    header("Location: izin_keluar.php?tab=data");
    exit;
}

// --- Filter & Pagination Data Tersimpan ---
$tgl_awal = $_GET['tgl_awal'] ?? '';
$tgl_akhir = $_GET['tgl_akhir'] ?? '';

// Jika tidak ada filter, gunakan tanggal hari ini
if (empty($tgl_awal) || empty($tgl_akhir)) {
    $tgl_awal = date('Y-m-d');
    $tgl_akhir = date('Y-m-d');
}

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$start = ($page - 1) * $limit;

// Hitung total data
$stmtCount = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM izin_keluar 
    WHERE user_id=? AND tanggal BETWEEN ? AND ?
");
$stmtCount->bind_param("iss", $user_id, $tgl_awal, $tgl_akhir);
$stmtCount->execute();
$resCount = $stmtCount->get_result()->fetch_assoc();
$total_data = $resCount['total'];
$total_page = ceil($total_data / $limit);

// Ambil data izin keluar user berdasarkan periode & pagination
$stmtIzin = $conn->prepare("
    SELECT * FROM izin_keluar 
    WHERE user_id=? AND tanggal BETWEEN ? AND ?
    ORDER BY created_at DESC LIMIT ?,?
");
$stmtIzin->bind_param("issii", $user_id, $tgl_awal, $tgl_akhir, $start, $limit);
$stmtIzin->execute();
$data_izin = $stmtIzin->get_result();

// Function untuk generate URL pagination
function buildPaginationUrl($page, $tgl_awal, $tgl_akhir) {
    $params = [
        'tab' => 'data',
        'page' => $page,
        'tgl_awal' => $tgl_awal,
        'tgl_akhir' => $tgl_akhir
    ];
    return 'izin_keluar.php?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8" />
<title>Form Izin Keluar</title>
<link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css" />
<link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css" />
<link rel="stylesheet" href="assets/css/style.css" />
<link rel="stylesheet" href="assets/css/components.css" />
<style>
.flash-center {position:fixed;top:20%;left:50%;transform:translate(-50%,-50%);z-index:1050;min-width:300px;max-width:90%;text-align:center;padding:15px;border-radius:8px;font-weight:500;box-shadow:0 5px 15px rgba(0,0,0,0.3);}
.izin-table{font-size:13px;white-space:nowrap;}
.izin-table th,.izin-table td{padding:6px 10px;vertical-align:middle;}
.izin-table thead th{color:#fff!important;background-color:#000!important;}
.pagination-info { font-size: 14px; color: #666; margin-bottom: 10px; }
.pagination { margin-top: 20px; }
.pagination .page-item.active .page-link { background-color: #6777ef; border-color: #6777ef; }
.pagination .page-link { color: #6777ef; }
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
    <!-- Ikon tanda tanya merah -->
    <button type="button" class="btn btn-link text-danger ml-2 p-0" data-toggle="modal" data-target="#prosedurModal" title="Lihat Prosedur">
      <i class="fas fa-question-circle fa-lg"></i>
    </button>
  </div>
  <div class="card-body">
    <ul class="nav nav-tabs" id="izinTab" role="tablist">
      <li class="nav-item"><a class="nav-link active" id="input-tab" data-toggle="tab" href="#input" role="tab">Input Data</a></li>
      <li class="nav-item"><a class="nav-link" id="data-tab" data-toggle="tab" href="#data" role="tab">Data Tersimpan</a></li>
    </ul>

    <div class="tab-content mt-3">
      <!-- TAB INPUT DATA -->
      <div class="tab-pane fade show active" id="input" role="tabpanel">
        <form method="POST" novalidate>
          <div class="row">
            <!-- Kolom Kiri -->
         <div class="col-md-6">

  <div class="form-group">
    <label>Tanggal Izin <span class="text-danger">*</span></label>
    <input type="date" 
           name="tanggal" 
           class="form-control" 
           value="<?= date('Y-m-d') ?>" 
           required>
    <small class="text-muted">Tanggal izin keluar</small>
  </div>

  <div class="form-group">
    <label>Jam Keluar <span class="text-danger">*</span></label>
    <input type="time" name="jam_keluar" class="form-control" required />
    <small class="text-muted">00 sd 11:59 = AM , 12.00 sd 11.59 = PM</small>
  </div>

  <div class="form-group">
    <label>Jam Kembali (Estimasi)</label>
    <input type="time" name="jam_kembali" class="form-control" />
    <small class="text-muted">00 sd 11:59 = AM , 12.00 sd 11.59 = PM</small>
  </div>

</div>

            <!-- Kolom Kanan -->
            <div class="col-md-6">
              <div class="form-group">
                <label>Keperluan / Alasan <span class="text-danger">*</span></label>
                <textarea name="keperluan" class="form-control" rows="5" placeholder="Contoh: Keperluan keluarga, urusan bank, dll." required></textarea>
              </div>
            </div>
          </div>

          <div class="text-right">
            <button type="submit" name="simpan" class="btn btn-primary">
              <i class="fas fa-save"></i> Simpan
            </button>
          </div>
        </form>
      </div>

      <!-- TAB DATA TERSIMPAN -->
      <div class="tab-pane fade" id="data" role="tabpanel">
        <!-- Form Filter -->
        <form method="GET" class="form-inline mb-3">
          <input type="hidden" name="tab" value="data">
          <label class="mr-2">Dari</label>
          <input type="date" name="tgl_awal" class="form-control mr-2" value="<?= htmlspecialchars($tgl_awal) ?>">
          
          <label class="mr-2">Sampai</label>
          <input type="date" name="tgl_akhir" class="form-control mr-2" value="<?= htmlspecialchars($tgl_akhir) ?>">
          
          <button type="submit" class="btn btn-primary mr-2">
            <i class="fas fa-search"></i> Tampilkan
          </button>
          
          <a href="izin_keluar.php?tab=data" class="btn btn-secondary">
            <i class="fas fa-redo"></i> Reset
          </a>
        </form>

        <!-- Informasi Pagination -->
        <div class="pagination-info">
          Menampilkan <?= min($start + 1, $total_data) ?> - <?= min($start + $limit, $total_data) ?> dari <?= $total_data ?> data
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
              <?php if ($data_izin && $data_izin->num_rows > 0): ?>
                <?php $no = $start + 1; while ($izin = $data_izin->fetch_assoc()) : ?>
                <tr>
                  <td class="text-center"><?= $no++ ?></td>
                  <td><?= htmlspecialchars(date('d-m-Y', strtotime($izin['tanggal']))) ?></td>
                  <td class="text-center"><?= htmlspecialchars($izin['jam_keluar']) ?></td>
                  <td class="text-center"><?= !empty($izin['jam_kembali']) ? htmlspecialchars($izin['jam_kembali']) : '-' ?></td>
                  <td class="text-center">
                    <?php if (!empty($izin['jam_kembali_real'])): ?>
                      <span class="text-success font-weight-bold">
                        <?= date('H:i', strtotime($izin['jam_kembali_real'])) ?>
                      </span>
                      <?php if (!empty($izin['keterangan_kembali'])): ?>
                        <br><small class="text-muted"><i><?= htmlspecialchars($izin['keterangan_kembali']) ?></i></small>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="badge badge-warning">Belum Kembali</span>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($izin['keperluan']) ?></td>
                  <td class="text-center"><?= htmlspecialchars(date('d-m-Y H:i', strtotime($izin['created_at']))) ?></td>
                  <td class="text-center">
                    <?php
                    $badgeAts = ($izin['status_atasan']=='disetujui')?'success':(($izin['status_atasan']=='ditolak')?'danger':'secondary');
                    echo "<span class='badge badge-{$badgeAts}'>".ucfirst($izin['status_atasan'])."</span><br>";
                    echo "<small>".($izin['waktu_acc_atasan']?date('d-m-Y H:i',strtotime($izin['waktu_acc_atasan'])):'-')."</small>";
                    ?>
                  </td>
                  <td class="text-center">
                    <?php
                    $badgeSdm = ($izin['status_sdm']=='disetujui')?'success':(($izin['status_sdm']=='ditolak')?'danger':'secondary');
                    echo "<span class='badge badge-{$badgeSdm}'>".ucfirst($izin['status_sdm'])."</span><br>";
                    echo "<small>".($izin['waktu_acc_sdm']?date('d-m-Y H:i',strtotime($izin['waktu_acc_sdm'])):'-')."</small>";
                    ?>
                  </td>
                 <td class="text-center">
  <a href="cetak_izin_keluar.php?id=<?= $izin['id'] ?>" 
     target="_blank" 
     class="btn btn-sm btn-info">
    <i class="fas fa-print"></i> Cetak
  </a>

  <?php if ($izin['status_atasan'] != 'disetujui' || $izin['status_sdm'] != 'disetujui'): ?>
    <br>
    <small class="text-danger">
      <i class="fas fa-exclamation-circle"></i>
      Belum ACC
    </small>
  <?php endif; ?>
</td>

                </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr><td colspan="10" class="text-center">Belum ada data izin keluar pada periode ini.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_page > 1): ?>
        <nav aria-label="Page navigation">
          <ul class="pagination justify-content-center">
            
            <!-- Tombol Previous -->
            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
              <a class="page-link" href="<?= buildPaginationUrl($page - 1, $tgl_awal, $tgl_akhir) ?>" aria-label="Previous">
                <span aria-hidden="true">&laquo;</span>
              </a>
            </li>

            <?php
            // Logika untuk menampilkan nomor halaman
            $start_page = max(1, $page - 2);
            $end_page = min($total_page, $page + 2);

            // Jika di awal, tampilkan lebih banyak halaman ke kanan
            if ($page <= 3) {
                $end_page = min(5, $total_page);
            }

            // Jika di akhir, tampilkan lebih banyak halaman ke kiri
            if ($page >= $total_page - 2) {
                $start_page = max(1, $total_page - 4);
            }

            // Tombol halaman pertama
            if ($start_page > 1) {
                echo '<li class="page-item"><a class="page-link" href="' . buildPaginationUrl(1, $tgl_awal, $tgl_akhir) . '">1</a></li>';
                if ($start_page > 2) {
                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
            }

            // Tombol halaman
            for ($i = $start_page; $i <= $end_page; $i++) {
                $active = ($i == $page) ? 'active' : '';
                echo '<li class="page-item ' . $active . '"><a class="page-link" href="' . buildPaginationUrl($i, $tgl_awal, $tgl_akhir) . '">' . $i . '</a></li>';
            }

            // Tombol halaman terakhir
            if ($end_page < $total_page) {
                if ($end_page < $total_page - 1) {
                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
                echo '<li class="page-item"><a class="page-link" href="' . buildPaginationUrl($total_page, $tgl_awal, $tgl_akhir) . '">' . $total_page . '</a></li>';
            }
            ?>

            <!-- Tombol Next -->
            <li class="page-item <?= ($page >= $total_page) ? 'disabled' : '' ?>">
              <a class="page-link" href="<?= buildPaginationUrl($page + 1, $tgl_awal, $tgl_akhir) ?>" aria-label="Next">
                <span aria-hidden="true">&raquo;</span>
              </a>
            </li>

          </ul>
        </nav>
        <?php endif; ?>

      </div>
    </div>
  </div>
</div>
</div>
</section>
</div>
</div>
</div>

<!-- Modal Prosedur -->
<div class="modal fade" id="prosedurModal" tabindex="-1" role="dialog" aria-labelledby="prosedurModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="prosedurModalLabel">
          <i class="fas fa-info-circle"></i> Prosedur Izin Keluar Karyawan
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body">
        <!-- STEP 1 -->
        <h6 class="mb-2">📌 Tahap 1 : Pengajuan Izin Keluar</h6>
        <ol>
          <li>Karyawan mengisi <b>Jam Keluar</b></li>
          <li>Karyawan mengisi <b>Jam Kembali (Estimasi)</b> (opsional)</li>
          <li>Karyawan mengisi <b>Alasan / Keperluan</b></li>
          <li>Form disimpan sebagai <b>Pengajuan Izin Keluar</b></li>
        </ol>

        <hr>

        <!-- STEP 2 -->
        <h6 class="mb-2">📌 Tahap 2 : Persetujuan</h6>
        <ul>
          <li>Pengajuan akan diperiksa oleh <b>Atasan</b></li>
          <li>Status <b>ACC Atasan</b> harus <span class="text-success font-weight-bold">Disetujui ✅</span></li>
          <li>Selanjutnya diperiksa oleh <b>SDM / HRD</b></li>
          <li>Status <b>ACC SDM</b> harus <span class="text-success font-weight-bold">Disetujui ✅</span></li>
        </ul>

        <hr>

        <!-- STEP 3 -->
        <h6 class="mb-2">📌 Tahap 3 : Cetak & Izin Keluar</h6>
        <ul>
          <li>Jika <b>ACC Atasan</b> dan <b>ACC SDM</b> sudah disetujui</li>
          <li>Karyawan dapat klik tombol <b><i class="fas fa-print"></i> Cetak</b></li>
          <li>Surat izin ditunjukkan kepada <b>Security</b> sebagai bukti izin keluar</li>
        </ul>

        <hr>

        <!-- STEP 4 -->
        <h6 class="mb-2">📌 Tahap 4 : Konfirmasi Kembali ke RS</h6>
        <ul>
          <li>Setelah kembali ke <b>Rumah Sakit</b></li>
          <li>Karyawan <b>wajib mengirim Share Location</b> ke <b>SDM / HRD</b></li>
          <li>Share location digunakan sebagai bukti sudah berada di area RS</li>
        </ul>

        <hr>

        <!-- STEP 5 -->
        <h6 class="mb-2">📌 Tahap 5 : Update Jam Kembali Real</h6>
        <ul>
          <li><b>SDM / HRD</b> akan melakukan <b>Update Jam Kembali Real</b> setelah menerima konfirmasi lokasi</li>
          <li>Data jam kembali akan otomatis muncul di kolom "Jam Kembali (Real)"</li>
          <li>Data izin keluar dinyatakan <b>Selesai</b></li>
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
$(document).ready(function(){
    setTimeout(function(){$("#flashMsg").fadeOut("slow");},3000);

    // Aktifkan tab sesuai parameter ?tab=
    const urlParams = new URLSearchParams(window.location.search);
    const activeTab = urlParams.get('tab');
    if (activeTab === 'data') {
        $('#input-tab').removeClass('active');
        $('#input').removeClass('show active');
        $('#data-tab').addClass('active');
        $('#data').addClass('show active');
    }
});
</script>

</body>
</html>