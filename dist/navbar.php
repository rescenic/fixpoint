<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
date_default_timezone_set('Asia/Jakarta'); // WIB
?>

<style>
  .btn-panduan {
    background-color: #17a2b8 !important;
    color: #fff !important;
    border-radius: 50px;
    padding: 5px 15px;
    font-size: 14px;
    font-weight: 500;
    border: none;
  }

  .btn-panduan:hover,
  .btn-panduan:focus,
  .btn-panduan:active,
  .btn-panduan.active {
    background-color: #138496 !important;
    color: #fff !important;
    box-shadow: none !important;
  }

  .modal-full-width {
    max-width: 100% !important;
    margin: 1rem auto;
  }

  @media (min-width: 1200px) {
    .modal-full-width .modal-content {
      padding: 1rem 2rem;
    }
  }
  
  .modal-body {
    max-height: 80vh;
    overflow-y: auto;
  }

  .modal-full-width {
    max-width: 50% !important;
    margin: 1rem auto;
  }

  .modal-body {
    max-height: 60vh;
    overflow-y: auto;
  }

  .chat-bubble-left {
    background-color: #f1f1f1;
    color: #000;
    padding: 8px 12px;
    border-radius: 15px 15px 15px 0;
    margin-bottom: 5px;
    display: inline-block;
    max-width: 80%;
  }

  .chat-bubble-right {
    background-color: #6777ef;
    color: #fff;
    padding: 8px 12px;
    border-radius: 15px 15px 0 15px;
    margin-bottom: 5px;
    display: inline-block;
    max-width: 80%;
  }

  .chat-message {
    padding: 5px;
    margin-bottom: 5px;
    border-radius: 5px;
  }

  .chat-message.received {
    background-color: #f1f1f1;
    text-align: left;
  }

  .chat-message.sent {
    background-color: #d1ecf1;
    text-align: right;
  }

  /* Style untuk menu modal */
  .menu-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
    padding: 10px;
  }

  .menu-item-card {
    background: #fff;
    border: 1px solid #e3e6f0;
    border-radius: 8px;
    padding: 15px;
    text-align: center;
    transition: all 0.3s ease;
    cursor: pointer;
    text-decoration: none;
    color: #333;
  }

  .menu-item-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    text-decoration: none;
    color: #6777ef;
  }

  .menu-item-card i {
    font-size: 32px;
    margin-bottom: 10px;
    color: #6777ef;
  }

  .menu-item-card .menu-title {
    font-size: 13px;
    font-weight: 600;
    margin: 0;
  }

  .menu-category-title {
    background: #f8f9fc;
    padding: 10px 15px;
    margin: 15px 0 10px 0;
    border-left: 4px solid #6777ef;
    font-weight: bold;
    color: #5a5c69;
  }

  .search-menu-box {
    position: sticky;
    top: 0;
    background: white;
    padding: 15px;
    border-bottom: 1px solid #e3e6f0;
    z-index: 10;
  }
</style>

<div class="navbar-bg"></div>
<nav class="navbar navbar-expand-lg main-navbar">
  <form class="form-inline mr-auto">
    <ul class="navbar-nav mr-3">
      <li>
        <a href="#" data-toggle="sidebar" class="nav-link nav-link-lg">
          <i class="fas fa-bars"></i>
        </a>
      </li>
    </ul>

    <!-- Jam Digital -->
    <div class="text-light font-weight-bold ml-3" id="jam-digital" style="font-size: 16px;"></div>

    <!-- Tombol Panduan Tiket -->
    <button type="button" class="btn btn-panduan ml-3" data-toggle="modal" data-target="#panduanModal">
      <i class="fas fa-info-circle mr-1"></i> Panduan Tiket
    </button>

    <!-- Tombol Catatan Kerja -->
    <button type="button" class="btn btn-flat text-white ml-2" style="background: transparent; border: none;" 
            data-toggle="modal" data-target="#catatanModal" data-toggle="tooltip" title="Catatan Kerja">
      <i class="fas fa-pen-square" style="font-size: 20px;"></i>
    </button>

    <!-- Tombol Pesan -->
    <button type="button" class="btn btn-flat text-white ml-2" style="background: transparent; border: none;" 
            data-toggle="modal" data-target="#pesanModal" data-toggle="tooltip" title="Kirim Pesan">
      <i class="fas fa-envelope" style="font-size: 20px;"></i>
    </button>

    <!-- Tombol Menu (Ganti Fungsi Menu) -->
    <button type="button" class="btn btn-flat text-white ml-2" style="background: transparent; border: none;" 
            data-toggle="modal" data-target="#menuDesktopModal" data-toggle="tooltip" title="Semua Menu">
      <i class="fas fa-th-large" style="font-size: 20px;"></i>
    </button>

  </form>

  <ul class="navbar-nav navbar-right">
    <li class="nav-item d-flex align-items-center mr-3">
      <i class="fas fa-user-circle text-white mr-2" style="font-size: 20px;"></i>
      <span class="text-white font-weight-bold" style="font-size: 15px;">
        <?= isset($_SESSION['nama']) ? htmlspecialchars($_SESSION['nama']) : 'Pengguna' ?>
      </span>
    </li>

    <li class="nav-item">
      <a href="logout.php" class="btn btn-danger btn-sm font-weight-bold" style="display: flex; align-items: center;">
        <i class="fas fa-sign-out-alt mr-1 text-white"></i> 
        <span class="text-white">Keluar</span>
      </a>
    </li>
  </ul>
</nav>

<!-- Modal Menu Desktop -->
<div class="modal fade" id="menuDesktopModal" tabindex="-1" role="dialog" aria-labelledby="menuDesktopModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document" style="max-width: 90%;">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="menuDesktopModalLabel">
          <i class="fas fa-th-large"></i> Semua Menu Aplikasi
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Tutup">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      
      <div class="modal-body p-0">
        <!-- Search Box -->
        <div class="search-menu-box">
          <input type="text" class="form-control" id="searchMenuModal" placeholder="🔍 Cari menu...">
        </div>

        <!-- Menu Content -->
        <div id="menuModalContent" style="padding: 20px;">
          
          <?php
          include 'koneksi.php';
          $user_id = $_SESSION['user_id'];
          
          // Ambil semua file_menu yang boleh diakses user ini
          $allowed_files = [];
          $query = "SELECT menu.file_menu FROM akses_menu 
                    JOIN menu ON akses_menu.menu_id = menu.id 
                    WHERE akses_menu.user_id = '$user_id'";
          $result = mysqli_query($conn, $query);
          while ($row = mysqli_fetch_assoc($result)) {
            $allowed_files[] = $row['file_menu'];
          }
          ?>

          <!-- DASHBOARD -->
          <div class="menu-category-title">
            <i class="fas fa-fire"></i> DASHBOARD
          </div>
          <div class="menu-grid">
            <?php if (in_array('dashboard.php', $allowed_files)): ?>
            <a href="dashboard.php" class="menu-item-card">
              <i class="fas fa-tachometer-alt"></i>
              <p class="menu-title">Dashboard</p>
            </a>
            <?php endif; ?>
            
            <?php if (in_array('dashboard2.php', $allowed_files)): ?>
            <a href="dashboard2.php" class="menu-item-card">
              <i class="fas fa-user-tie"></i>
              <p class="menu-title">Dashboard Direktur</p>
            </a>
            <?php endif; ?>
          </div>

          <!-- PENGAJUAN -->
          <div class="menu-category-title">
            <i class="fas fa-list"></i> PENGAJUAN / ORDER
          </div>
          <div class="menu-grid">
            <?php if (in_array('order_tiket_it_software.php', $allowed_files)): ?>
            <a href="order_tiket_it_software.php" class="menu-item-card">
              <i class="fas fa-code"></i>
              <p class="menu-title">Tiket IT Software</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('order_tiket_it_hardware.php', $allowed_files)): ?>
            <a href="order_tiket_it_hardware.php" class="menu-item-card">
              <i class="fas fa-desktop"></i>
              <p class="menu-title">Tiket IT Hardware</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('order_tiket_sarpras.php', $allowed_files)): ?>
            <a href="order_tiket_sarpras.php" class="menu-item-card">
              <i class="fas fa-wrench"></i>
              <p class="menu-title">Tiket Sarpras</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('off_duty.php', $allowed_files)): ?>
            <a href="off_duty.php" class="menu-item-card">
              <i class="fas fa-user-slash"></i>
              <p class="menu-title">Off-Duty</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('izin_keluar.php', $allowed_files)): ?>
            <a href="izin_keluar.php" class="menu-item-card">
              <i class="fas fa-door-open"></i>
              <p class="menu-title">Izin Keluar</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('pengajuan_cuti.php', $allowed_files)): ?>
            <a href="pengajuan_cuti.php" class="menu-item-card">
              <i class="fas fa-calendar-times"></i>
              <p class="menu-title">Pengajuan Cuti</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('ganti_jadwal_dinas.php', $allowed_files)): ?>
            <a href="ganti_jadwal_dinas.php" class="menu-item-card">
              <i class="fas fa-exchange-alt"></i>
              <p class="menu-title">Ganti Jadwal</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('edit_data_simrs.php', $allowed_files)): ?>
            <a href="edit_data_simrs.php" class="menu-item-card">
              <i class="fas fa-edit"></i>
              <p class="menu-title">Edit Data SIMRS</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('hapus_data.php', $allowed_files)): ?>
            <a href="hapus_data.php" class="menu-item-card">
              <i class="fas fa-trash"></i>
              <p class="menu-title">Hapus Data SIMRS</p>
            </a>
            <?php endif; ?>
          </div>

          <!-- DATA PENGAJUAN -->
          <div class="menu-category-title">
            <i class="fas fa-folder-open"></i> DATA PENGAJUAN
          </div>
          <div class="menu-grid">
            <?php if (in_array('data_tiket_it_software.php', $allowed_files)): ?>
            <a href="data_tiket_it_software.php" class="menu-item-card">
              <i class="fas fa-code"></i>
              <p class="menu-title">Data Tiket IT Soft</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('data_tiket_it_hardware.php', $allowed_files)): ?>
            <a href="data_tiket_it_hardware.php" class="menu-item-card">
              <i class="fas fa-cogs"></i>
              <p class="menu-title">Data Tiket IT Hard</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('data_tiket_sarpras.php', $allowed_files)): ?>
            <a href="data_tiket_sarpras.php" class="menu-item-card">
              <i class="fas fa-tools"></i>
              <p class="menu-title">Data Tiket Sarpras</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('data_off_duty.php', $allowed_files)): ?>
            <a href="data_off_duty.php" class="menu-item-card">
              <i class="fas fa-calendar-times"></i>
              <p class="menu-title">Data Off-Duty</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('acc_edit_data.php', $allowed_files)): ?>
            <a href="acc_edit_data.php" class="menu-item-card">
              <i class="fas fa-check-circle"></i>
              <p class="menu-title">ACC Edit Data</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('data_permintaan_hapus_data_simrs.php', $allowed_files)): ?>
            <a href="data_permintaan_hapus_data_simrs.php" class="menu-item-card">
              <i class="fas fa-trash-alt"></i>
              <p class="menu-title">Permintaan Hapus</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('data_cuti_delegasi.php', $allowed_files)): ?>
            <a href="data_cuti_delegasi.php" class="menu-item-card">
              <i class="fas fa-users"></i>
              <p class="menu-title">ACC Cuti Delegasi</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('data_cuti_atasan.php', $allowed_files)): ?>
            <a href="data_cuti_atasan.php" class="menu-item-card">
              <i class="fas fa-user-tie"></i>
              <p class="menu-title">ACC Cuti Atasan</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('data_cuti_hrd.php', $allowed_files)): ?>
            <a href="data_cuti_hrd.php" class="menu-item-card">
              <i class="fas fa-id-badge"></i>
              <p class="menu-title">ACC Cuti HRD</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('acc_keluar_atasan.php', $allowed_files)): ?>
            <a href="acc_keluar_atasan.php" class="menu-item-card">
              <i class="fas fa-user-check"></i>
              <p class="menu-title">ACC Keluar Atasan</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('acc_keluar_sdm.php', $allowed_files)): ?>
            <a href="acc_keluar_sdm.php" class="menu-item-card">
              <i class="fas fa-check-double"></i>
              <p class="menu-title">ACC Keluar SDM</p>
            </a>
            <?php endif; ?>
          </div>

          <!-- IT DEPARTEMEN -->
          <div class="menu-category-title">
            <i class="fas fa-desktop"></i> IT DEPARTEMEN
          </div>
          <div class="menu-grid">
            <?php if (in_array('handling_time.php', $allowed_files)): ?>
            <a href="handling_time.php" class="menu-item-card">
              <i class="fas fa-stopwatch"></i>
              <p class="menu-title">Handling Time</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('spo_it.php', $allowed_files)): ?>
            <a href="spo_it.php" class="menu-item-card">
              <i class="fas fa-file-alt"></i>
              <p class="menu-title">SPO IT</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('input_spo_it.php', $allowed_files)): ?>
            <a href="input_spo_it.php" class="menu-item-card">
              <i class="fas fa-file-signature"></i>
              <p class="menu-title">Input SPO IT</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('berita_acara_it.php', $allowed_files)): ?>
            <a href="berita_acara_it.php" class="menu-item-card">
              <i class="fas fa-scroll"></i>
              <p class="menu-title">Berita Acara</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('data_barang_it.php', $allowed_files)): ?>
            <a href="data_barang_it.php" class="menu-item-card">
              <i class="fas fa-boxes"></i>
              <p class="menu-title">Data Barang IT</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('maintenance_rutin.php', $allowed_files)): ?>
            <a href="maintenance_rutin.php" class="menu-item-card">
              <i class="fas fa-sync-alt"></i>
              <p class="menu-title">Maintenance Rutin</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('koneksi_bridging.php', $allowed_files)): ?>
            <a href="koneksi_bridging.php" class="menu-item-card">
              <i class="fas fa-link"></i>
              <p class="menu-title">Koneksi Bridging</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('log_login.php', $allowed_files)): ?>
            <a href="log_login.php" class="menu-item-card">
              <i class="fas fa-history"></i>
              <p class="menu-title">Log Login</p>
            </a>
            <?php endif; ?>
          </div>

          <!-- Tambahkan kategori lainnya sesuai kebutuhan... -->

        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Chat Pesan -->
<div class="modal fade" id="pesanModal" tabindex="-1" role="dialog" aria-labelledby="pesanModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg" role="document" style="max-width: 800px;">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="fas fa-comments"></i> Chat Pesan</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Tutup">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body p-0">
        <div class="row no-gutters">
          <!-- Sidebar User List -->
          <div class="col-md-4 border-right" style="max-height: 400px; overflow-y: auto;" id="daftar-pengguna">
            <div class="text-center text-muted p-3"><em>Memuat pengguna...</em></div>
          </div>

          <!-- Chat Area -->
          <div class="col-md-8 d-flex flex-column" style="height: 400px;">
            <div class="p-2 bg-light border-bottom" id="chat-header" style="font-weight: bold;">
              Pilih pengguna untuk mulai chat
            </div>
            <div class="flex-grow-1 p-2" id="chat-body" style="overflow-y: auto; background: #f9f9f9;"></div>
            <div class="p-2 border-top">
              <form id="formChat" method="POST" class="d-flex" style="display: none;">
                <input type="hidden" name="penerima_id" id="penerima_id">
                <input type="text" name="pesan" class="form-control mr-2" placeholder="Tulis pesan..." required>
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i></button>
              </form>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- Modal Panduan Tiket -->
<div class="modal fade" id="panduanModal" tabindex="-1" role="dialog" aria-labelledby="panduanModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-full-width" role="document">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title" id="panduanModalLabel"><i class="fas fa-info-circle"></i> Kategori Layanan IT</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body">
        <div class="row">
          <!-- Kolom IT Software -->
          <div class="col-md-6">
            <h6 class="text-info font-weight-bold mb-3">
              <i class="fas fa-laptop-code"></i> IT Software
            </h6>
            <?php
            $querySoftware = "SELECT nama_kategori FROM kategori_software ORDER BY nama_kategori ASC";
            $resultSoftware = mysqli_query($conn, $querySoftware);

            if (mysqli_num_rows($resultSoftware) > 0) {
              while ($row = mysqli_fetch_assoc($resultSoftware)) {
                echo '
                <div class="mb-2 p-2 rounded text-white" style="background-color: #007bff;">
                  <i class="fas fa-check-circle mr-2"></i> ' . htmlspecialchars($row['nama_kategori']) . '
                </div>';
              }
            } else {
              echo '<div class="text-muted"><em>Data tidak tersedia</em></div>';
            }
            ?>
          </div>

          <!-- Kolom IT Hardware -->
          <div class="col-md-6">
            <h6 class="text-dark font-weight-bold mb-3">
              <i class="fas fa-desktop"></i> IT Hardware
            </h6>
            <?php
            $queryHardware = "SELECT nama_kategori FROM kategori_hardware ORDER BY nama_kategori ASC";
            $resultHardware = mysqli_query($conn, $queryHardware);

            if (mysqli_num_rows($resultHardware) > 0) {
              while ($row = mysqli_fetch_assoc($resultHardware)) {
                echo '
                <div class="mb-2 p-2 rounded text-white" style="background-color: #343a40;">
                  <i class="fas fa-check-circle mr-2"></i> ' . htmlspecialchars($row['nama_kategori']) . '
                </div>';
              }
            } else {
              echo '<div class="text-muted"><em>Data tidak tersedia</em></div>';
            }
            ?>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Catatan Kerja -->
<div class="modal fade" id="catatanModal" tabindex="-1" role="dialog" aria-labelledby="catatanModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg" role="document" style="max-width: 700px;">
    <div class="modal-content">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title" id="catatanModalLabel"><i class="fas fa-pen-square"></i> Log Book</h5>
        <button type="button" class="close text-dark" data-dismiss="modal" aria-label="Tutup">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <form id="formCatatan" method="POST" action="simpan_catatan.php">
        <div class="modal-body">
          <div class="form-group">
            <label for="judul">Judul Catatan</label>
            <input type="text" class="form-control" id="judul" name="judul" placeholder="Masukkan judul catatan" required>
          </div>
          <div class="form-group">
            <label for="isi">Isi Catatan</label>
            <textarea class="form-control" id="isi" name="isi" rows="5" placeholder="Tuliskan isi catatan kerja..." required></textarea>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-warning btn-sm"><i class="fas fa-save"></i> Simpan Catatan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- jQuery & Bootstrap -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.6.2/js/bootstrap.min.js"></script>
<link href="https://stackpath.bootstrapcdn.com/bootstrap/4.6.2/css/bootstrap.min.css" rel="stylesheet">

<!-- Jam Digital -->
<script>
  function updateJamDigital() {
    const hari = ["Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"];
    const bulan = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
    const now = new Date();
    const dayName = hari[now.getDay()];
    const day = String(now.getDate()).padStart(2, '0');
    const month = bulan[now.getMonth()];
    const year = now.getFullYear();
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');
    const fullTime = `${dayName}, ${day} ${month} ${year} - ${hours}:${minutes}:${seconds} WIB`;
    document.getElementById('jam-digital').textContent = fullTime;
  }
  setInterval(updateJamDigital, 1000);
  updateJamDigital();
</script>

<script>
  $(document).ready(function () {
    // Inisialisasi tooltip
    $('[data-toggle="tooltip"]').tooltip();

    // Fix modal backdrop
    $(document).on('hidden.bs.modal', function () {
      if (!$('.modal.show').length) {
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open').css('padding-right', '');
      }
    });

    // Search menu dalam modal
    $('#searchMenuModal').on('keyup', function() {
      var value = $(this).val().toLowerCase();
      $('#menuModalContent .menu-item-card').filter(function() {
        $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
      });
      
      // Hide kategori jika semua menu di dalamnya hidden
      $('#menuModalContent .menu-category-title').each(function() {
        var $category = $(this);
        var $nextGrid = $category.next('.menu-grid');
        var hasVisible = $nextGrid.find('.menu-item-card:visible').length > 0;
        $category.toggle(hasVisible);
        $nextGrid.toggle(hasVisible);
      });
    });

    // Modal chat dibuka
    $('#pesanModal').on('shown.bs.modal', function () {
      const selected = $('#pilih_pengguna').val();
      if (selected) {
        $('#formChat').show();
        loadChat(selected);
      } else {
        $('#formChat').hide();
        $('#chat-body').html('<em class="text-muted">Pilih pengguna untuk mulai chat...</em>');
      }
    });

    // Submit kirim pesan
    $('#formChat').on('submit', function (e) {
      e.preventDefault();
      const pesan = $('input[name="pesan"]').val().trim();
      const ke_id = $('#penerima_id').val();
      const dari_id = "<?= $_SESSION['user_id'] ?? 0; ?>";
      const nama_pengirim = "<?= $_SESSION['nama'] ?? 'Anda'; ?>";

      if (!pesan || !ke_id) return;

      $.post('kirim_pesan.php', $(this).serialize(), function () {
        const data = {
          dari_id: dari_id,
          ke_id: ke_id,
          nama_pengirim: nama_pengirim,
          pesan: pesan
        };
        if (typeof ws !== 'undefined') ws.send(JSON.stringify(data));

        const bubble = `<div class="chat-bubble-right">${pesan}</div>`;
        $('#chat-body').append(bubble);
        $('#chat-body').scrollTop($('#chat-body')[0].scrollHeight);

        $('input[name="pesan"]').val('');
      }).fail(function () {
        alert("Gagal mengirim pesan.");
      });
    });

    window.handleUserChange = function (select) {
      const userId = select.value;
      if (userId) {
        $('#penerima_id').val(userId);
        $('#formChat').show();
        loadChat(userId);
      } else {
        $('#penerima_id').val('');
        $('#formChat').hide();
        $('#chat-body').html('<em class="text-muted">Pilih pengguna untuk mulai chat...</em>');
      }
    };

    window.loadChat = function (userId) {
      $.post('load_chat.php', { penerima_id: userId }, function (res) {
        $('#chat-body').html(res);
        $('#chat-body').scrollTop($('#chat-body')[0].scrollHeight);
      });
    };

    function loadOnlineUsers() {
      $.getJSON('get_online_users.php', function (users) {
        let html = '';
        if (users.length === 0) {
          html = '<div class="text-muted"><em>Tidak ada pengguna yang online</em></div>';
        } else {
          users.forEach(function (user) {
            html += `
              <div class="d-flex align-items-center justify-content-between py-1 px-2 rounded mb-1" style="cursor:pointer; background:#f8f9fa;" onclick="selectUser(${user.id}, '${user.nama}')">
                <div><i class="fas fa-user-circle text-primary mr-2"></i> ${user.nama}</div>
                <span class="badge badge-success" style="width:10px; height:10px; border-radius:50%;"></span>
              </div>`;
          });
        }
        $('#daftar-pengguna').html(html);
      });
    }

    window.selectUser = function (userId, userName) {
      $('#penerima_id').val(userId);
      $('#formChat').show();
      loadChat(userId);
    };

    loadOnlineUsers();
  });

  // WebSocket
  try {
    const ws = new WebSocket("ws://localhost:8081");

    ws.onopen = () => console.log("🟢 WebSocket Connected");

    ws.onmessage = function (event) {
      const data = JSON.parse(event.data);
      const currentChat = $('#penerima_id').val();

      if (data.dari_id == currentChat) {
        const bubble = `<div class="chat-bubble-left">${data.nama_pengirim}: ${data.pesan}</div>`;
        $('#chat-body').append(bubble);
        $('#chat-body').scrollTop($('#chat-body')[0].scrollHeight);
      } else {
        console.log("📨 Pesan baru dari", data.nama_pengirim);
      }
    };

    ws.onerror = function (err) {
      console.error("❌ WebSocket Error:", err);
    };

    ws.onclose = function () {
      console.log("🔴 WebSocket Disconnected");
    };
  } catch (e) {
    console.log("WebSocket tidak tersedia");
  }
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php if (isset($_SESSION['notif'])): ?>
<script>
Swal.fire({
  icon: '<?= $_SESSION['notif']['type']; ?>',
  title: '<?= $_SESSION['notif']['msg']; ?>',
  position: 'center',
  showConfirmButton: false,
  timer: 2000,
  timerProgressBar: true
});
</script>
<?php unset($_SESSION['notif']); endif; ?>