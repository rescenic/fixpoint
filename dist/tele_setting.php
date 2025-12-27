<?php
include 'security.php'; 
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

$user_id = $_SESSION['user_id'];
$current_file = basename(__FILE__); // 

// Cek apakah user boleh mengakses halaman ini
$query = "SELECT 1 FROM akses_menu 
          JOIN menu ON akses_menu.menu_id = menu.id 
          WHERE akses_menu.user_id = '$user_id' AND menu.file_menu = '$current_file'";
$result = mysqli_query($conn, $query);
if (mysqli_num_rows($result) == 0) {
  echo "<script>alert('Anda tidak memiliki akses ke halaman ini.'); window.location.href='dashboard.php';</script>";
  exit;
}


// Ambil data jika sedang edit
$edit_setting = null;
if (isset($_GET['edit_id'])) {
  $edit_id = intval($_GET['edit_id']);
  $get = mysqli_query($conn, "SELECT * FROM setting WHERE id = $edit_id");
  if ($get && mysqli_num_rows($get) > 0) {
    $edit_setting = mysqli_fetch_assoc($get);
  }
}


// Hapus setting
if (isset($_GET['hapus_id'])) {
  $hapus_id = intval($_GET['hapus_id']);
  mysqli_query($conn, "DELETE FROM setting WHERE id = $hapus_id");
  header("Location: tele_setting.php");
  exit;
}
// Simpan atau update
if (isset($_POST['simpan'])) {
  $id = intval($_POST['id'] ?? 0);
  $nama = mysqli_real_escape_string($conn, $_POST['nama']);
  $nilai = mysqli_real_escape_string($conn, $_POST['nilai']);




  if ($id > 0) {
    mysqli_query($conn, "UPDATE setting SET nama = '$nama', nilai = '$nilai' WHERE id = $id");
  } else {
    mysqli_query($conn, "INSERT INTO setting (nama, nilai) VALUES ('$nama', '$nilai')");
  }

  header("Location: tele_setting.php");
  exit;
}

// Ambil semua setting telegram
$all_settings = mysqli_query($conn, "SELECT * FROM setting ORDER BY id DESC");

?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <title>f.i.x.p.o.i.n.t</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
  <link rel="stylesheet" href="assets/modules/bootstrap/css/bootstrap.min.css" />
  <link rel="stylesheet" href="assets/modules/fontawesome/css/all.min.css" />
  <link rel="stylesheet" href="assets/css/style.css" />
  <link rel="stylesheet" href="assets/css/components.css" />
</head>
<body>
<div id="app">
  <div class="main-wrapper main-wrapper-1">
    <?php include 'navbar.php'; ?>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
      <section class="section">
       <div class="section-header d-flex align-items-center">
  <h1 class="mb-0">Pengaturan Telegram</h1>

  <!-- Ikon tanda tanya merah -->
  <button type="button"
          class="btn btn-link text-danger ml-2 p-0"
          data-toggle="modal"
          data-target="#modalHelpTelegram"
          title="Panduan Pengisian">
    <i class="fas fa-question-circle"></i>
  </button>
</div>


        <div class="section-body">
          <div class="card">
            <div class="card-header">
              <h4><?= $edit_setting ? 'Edit Setting' : 'Tambah Setting' ?></h4>
              <div class="card-header-action">
                <a href="javascript:void(0);" class="btn btn-icon btn-info" data-collapse="#formTele">
                  <i class="fas fa-chevron-down"></i>
                </a>
              </div>
            </div>

            <div class="collapse show" id="formTele">
              <div class="card-body">
                <form method="POST">
                  <input type="hidden" name="id" value="<?= $edit_setting['id'] ?? '' ?>">
                  <div class="form-group">
                    <label>Nama</label>
                    <input type="text" name="nama" class="form-control" required value="<?= htmlspecialchars($edit_setting['nama'] ?? '') ?>">
                  </div>
                  <div class="form-group">
                    <label>Nilai</label>
                    <textarea name="nilai" class="form-control" rows="3" required><?= htmlspecialchars($edit_setting['nilai'] ?? '') ?></textarea>
                  </div>
                  <button type="submit" name="simpan" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan
                  </button>
                  <?php if ($edit_setting): ?>
                    <a href="tele_setting.php" class="btn btn-secondary ml-2">Batal</a>
                  <?php endif; ?>
                </form>
              </div>
            </div>
          </div>

          <!-- TABEL DATA -->
          <div class="card mt-4">
            <div class="card-header">
              <h4>Data Telegram Setting</h4>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-striped table-bordered">
                  <thead>
                    <tr>
                      <th>No</th>
                      <th>Nama</th>
                      <th>Token / Nilai</th>
                      <th>Aksi</th>
                    </tr>
                  </thead>
                  <tbody>
                  <?php $no = 1; if ($all_settings && mysqli_num_rows($all_settings) > 0): ?>
  <?php while ($row = mysqli_fetch_assoc($all_settings)) : ?>
    <tr>
      <td><?= $no++ ?></td>
      <td><?= htmlspecialchars($row['nama']) ?></td>
      <td><?= htmlspecialchars($row['nilai']) ?></td>
      <td>
        <a href="tele_setting.php?edit_id=<?= $row['id'] ?>" class="btn btn-sm btn-warning">
          <i class="fas fa-edit"></i> Edit
        </a>
    <a href="tele_setting.php?hapus_id=<?= $row['id'] ?>" onclick="return confirm('Yakin ingin menghapus?')" class="btn btn-sm btn-danger">

          <i class="fas fa-trash"></i> Hapus
        </a>
      </td>
    </tr>
  <?php endwhile; ?>
<?php else: ?>
  <tr>
    <td colspan="4" class="text-center text-danger">Data Tele Setting masih kosong.</td>
  </tr>
<?php endif; ?>

                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <!-- END TABEL -->

        </div>
      </section>
    </div>
  </div>
</div>


<!-- MODAL PANDUAN TELEGRAM SETTING -->
<div class="modal fade" id="modalHelpTelegram" tabindex="-1" role="dialog" aria-labelledby="modalHelpTelegramLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
    <div class="modal-content">

      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="modalHelpTelegramLabel">
          <i class="fas fa-info-circle"></i> Panduan Pengisian Telegram Setting
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>

      <div class="modal-body">

        <h6>📌 1. Telegram Bot Token</h6>
        <ul>
          <li>Isi <b>Nama</b> dengan: <code>telegram_bot_token</code></li>
          <li>Isi <b>Nilai</b> dengan token bot dari <b>@BotFather</b></li>
          <li>Contoh nilai: <code>123456:ABC-DEF1234</code></li>
        </ul>

        <hr>

        <h6>📌 2. Chat ID Grup IT</h6>
        <ul>
          <li>Isi <b>Nama</b> dengan: <code>telegram_chat_id</code></li>
          <li>Isi <b>Nilai</b> dengan Chat ID grup Telegram IT</li>
          <li>Chat ID grup selalu diawali tanda <b>minus (-100...)</b></li>
        </ul>

        <hr>

        <h6>📌 3. Chat ID Grup HRD</h6>
        <ul>
          <li>Isi <b>Nama</b> dengan: <code>telegram_chat_id_hrd</code></li>
          <li>Isi <b>Nilai</b> dengan Chat ID grup Telegram HRD</li>
          <li>Bot harus sudah ditambahkan ke grup HRD</li>
        </ul>

        <hr>

        <h6>📌 4. Catatan Penting</h6>
        <ul>
          <li>Nama setting harus <b>persis</b> (tidak boleh typo)</li>
          <li>Bot Telegram harus memiliki izin kirim pesan</li>
          <li>Satu bot bisa digunakan untuk banyak grup</li>
        </ul>

<!-- JS Scripts -->
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
    $("[data-collapse]").on("click", function () {
      const icon = $(this).find("i");
      $($(this).data("collapse")).collapse("toggle");
      icon.toggleClass("fa-chevron-down fa-chevron-up");
    });
  });
</script>

</body>
</html>
