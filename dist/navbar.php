<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
date_default_timezone_set('Asia/Jakarta'); // WIB
?>

<style>
  /* ========== FULLSCREEN MODAL - FIXED ========== */
  .modal.fade #menuDesktopModal { padding: 0 !important; }
  
  #menuDesktopModal {
    padding: 0 !important;
  }
  
  #menuDesktopModal.show .modal-dialog {
    max-width: 100vw !important;
    width: 100vw !important;
    height: 100vh !important;
    margin: 0 !important;
    transform: none !important;
  }
  
  #menuDesktopModal .modal-dialog {
    max-width: 100vw !important;
    width: 100vw !important;
    height: 100vh !important;
    margin: 0 !important;
  }
  
  #menuDesktopModal .modal-content {
    height: 100vh !important;
    width: 100vw !important;
    border-radius: 0 !important;
    border: none !important;
    background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
  }
  
  #menuDesktopModal .modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 20px 40px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    border-radius: 0 !important;
    flex-shrink: 0;
  }
  
  #menuDesktopModal .modal-body {
    padding: 0 !important;
    height: calc(100vh - 80px) !important;
    overflow: hidden !important;
    flex: 1;
  }
  
  #menuModalContent {
    padding: 20px 35px 30px 35px;
    height: 100% !important;
    overflow-y: auto !important;
    overflow-x: hidden !important;
  }

  /* Search Box Sticky */
  .search-menu-box {
    position: sticky;
    top: 0;
    z-index: 1000;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 18px 35px;
    margin: -20px -35px 20px -35px;
  }

  .search-menu-box input {
    width: 100%;
    padding: 12px 25px;
    border-radius: 50px;
    border: none;
    font-size: 15px;
  }

  /* Menu Grid */
  .menu-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)) !important;
    gap: 16px !important;
    margin-bottom: 15px;
  }

  /* Category Title */
  .menu-category-title {
    margin: 25px 0 18px 0 !important;
    padding: 14px 22px !important;
    font-size: 14px !important;
  }

  /* Menu Card */
  .menu-item-card {
    padding: 18px 12px !important;
  }
  
  .menu-item-card i { 
    font-size: 40px !important; 
  }
  
  .menu-item-card:hover i { 
    transform: scale(1.2) rotateY(360deg) !important; 
  }
  
  /* ========== ICON COLORS ========== */
  .icon-blue { color: #3182ce !important; }
  .icon-purple { color: #805ad5 !important; }
  .icon-pink { color: #d53f8c !important; }
  .icon-red { color: #e53e3e !important; }
  .icon-orange { color: #dd6b20 !important; }
  .icon-yellow { color: #d69e2e !important; }
  .icon-green { color: #38a169 !important; }
  .icon-teal { color: #319795 !important; }
  .icon-cyan { color: #00b5d8 !important; }
  .icon-indigo { color: #5a67d8 !important; }

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
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 15px;
    padding: 15px;
  }

  .menu-item-card {
    background: #fff;
    border: 2px solid transparent;
    border-radius: 15px;
    padding: 20px 15px;
    text-align: center;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    cursor: pointer;
    text-decoration: none;
    color: #2d3748;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    position: relative;
    overflow: hidden;
  }

  .menu-item-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, rgba(103, 119, 239, 0.1), rgba(118, 75, 162, 0.1));
    opacity: 0;
    transition: opacity 0.4s ease;
    z-index: 0;
  }

  .menu-item-card:hover::before {
    opacity: 1;
  }

  .menu-item-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 12px 35px rgba(103, 119, 239, 0.25);
    border-color: rgba(103, 119, 239, 0.3);
  }

  .menu-item-card i {
    font-size: 36px;
    margin-bottom: 12px;
    transition: all 0.4s ease;
    position: relative;
    z-index: 1;
  }

  .menu-item-card:hover i {
    transform: scale(1.15) rotateY(360deg);
  }

  .menu-item-card .menu-title {
    font-size: 13px;
    font-weight: 600;
    margin: 0;
    line-height: 1.4;
    position: relative;
    z-index: 1;
  }

  /* Icon Colors - Professional & Colorful */
  .icon-dashboard { color: #4299e1; }
  .icon-software { color: #9f7aea; }
  .icon-hardware { color: #f56565; }
  .icon-sarpras { color: #ed8936; }
  .icon-offduty { color: #48bb78; }
  .icon-lembur { color: #38b2ac; }
  .icon-keluar { color: #667eea; }
  .icon-pulang { color: #fc8181; }
  .icon-cuti { color: #f6ad55; }
  .icon-jadwal { color: #9ae6b4; }
  .icon-edit { color: #4299e1; }
  .icon-hapus { color: #fc8181; }
  .icon-tte { color: #805ad5; }
  .icon-qrcode { color: #d53f8c; }
  .icon-stamp { color: #f687b3; }
  .icon-spo { color: #4fd1c5; }
  .icon-rapat { color: #63b3ed; }
  .icon-surat { color: #76e4f7; }
  .icon-pemberitahuan { color: #fbb6ce; }
  .icon-handling { color: #f6e05e; }
  .icon-berita { color: #fbd38d; }
  .icon-barang { color: #c4b5fd; }
  .icon-maintenance { color: #9decf9; }
  .icon-bridging { color: #b794f4; }
  .icon-log { color: #90cdf4; }
  .icon-gaji { color: #68d391; }
  .icon-bpjs { color: #f6ad55; }
  .icon-kesehatan { color: #fc8181; }
  .icon-struktural { color: #b794f4; }
  .icon-karyawan { color: #63b3ed; }
  .icon-disposisi { color: #f687b3; }
  .icon-arsip { color: #9ae6b4; }
  .icon-agenda { color: #fbb6ce; }
  .icon-laporan { color: #c4b5fd; }
  .icon-kpi { color: #f6e05e; }
  .icon-kredensial { color: #fc8181; }
  .icon-komite { color: #f687b3; }
  .icon-soal { color: #90cdf4; }
  .icon-indikator { color: #68d391; }
  .icon-imut { color: #fbd38d; }
  .icon-simrs { color: #9decf9; }
  .icon-antrian { color: #b794f4; }
  .icon-absensi { color: #63b3ed; }
  .icon-master { color: #9f7aea; }
  .icon-setting { color: #ed8936; }
  .icon-perusahaan { color: #4299e1; }
  .icon-pengguna { color: #805ad5; }
  .icon-akses { color: #d53f8c; }
  .icon-telegram { color: #0088cc; }
  .icon-whatsapp { color: #25d366; }
  .icon-mail { color: #ea4335; }
  .icon-kategori { color: #f6ad55; }
  .icon-unit { color: #4fd1c5; }
  .icon-jabatan { color: #9ae6b4; }
  .icon-profile { color: #667eea; }
  .icon-pokja { color: #76e4f7; }
  .icon-dokumen { color: #c4b5fd; }

  .menu-category-title {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px 25px;
    margin: 25px 0 20px 0;
    border-radius: 12px;
    font-weight: 700;
    font-size: 15px;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .menu-category-title i {
    margin-right: 10px;
    font-size: 18px;
  }

  .search-menu-box {
    position: sticky;
    top: 0;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 20px 25px;
    z-index: 1000;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
  }

  .search-menu-box input {
    border-radius: 30px;
    padding: 12px 25px;
    border: none;
    font-size: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    background: white;
  }

  .search-menu-box input:focus {
    outline: none;
    box-shadow: 0 6px 25px rgba(255,255,255,0.3);
  }

  .search-menu-box input::placeholder {
    color: #a0aec0;
  }

  /* Modal Menu Desktop Custom - FULLSCREEN */
  #menuDesktopModal .modal-dialog {
    max-width: 100%;
    width: 100%;
    height: 100vh;
    margin: 0;
  }

  #menuDesktopModal .modal-content {
    height: 100vh;
    border-radius: 0;
    border: none;
    background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
  }

  #menuDesktopModal .modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 0;
    padding: 20px 30px;
    border-bottom: none;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
  }

  #menuDesktopModal .modal-header .modal-title {
    font-size: 24px;
    font-weight: 700;
    text-shadow: 0 2px 10px rgba(0,0,0,0.2);
  }

  #menuDesktopModal .modal-body {
    padding: 0;
    height: calc(100vh - 80px);
    overflow-y: auto;
  }

  #menuDesktopModal .modal-footer {
    display: none;
  }

  /* Scrollbar Custom - More Beautiful */
  #menuModalContent::-webkit-scrollbar {
    width: 12px;
  }

  #menuModalContent::-webkit-scrollbar-track {
    background: linear-gradient(135deg, #f7fafc, #edf2f7);
    border-radius: 10px;
  }

  #menuModalContent::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 10px;
    border: 2px solid #f7fafc;
  }

  #menuModalContent::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, #764ba2, #667eea);
  }

  /* Close Button */
  #menuDesktopModal .close {
    font-size: 32px;
    font-weight: 300;
    text-shadow: none;
    opacity: 1;
  }

  #menuDesktopModal .close:hover {
    transform: rotate(90deg);
    transition: transform 0.3s ease;
  }

  /* Menu Content */
  #menuModalContent {
    padding: 30px;
    max-height: calc(100vh - 140px);
    overflow-y: auto;
  }

  /* Animation */
  @keyframes fadeInUp {
    from {
      opacity: 0;
      transform: translateY(30px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  .menu-grid {
    animation: fadeInUp 0.6s ease-out;
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

    <!-- Tombol Menu (Semua Menu) -->
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

<!-- Modal Menu Desktop - LENGKAP -->
<div class="modal fade" id="menuDesktopModal" tabindex="-1" role="dialog" aria-labelledby="menuDesktopModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header text-white">
        <h5 class="modal-title" id="menuDesktopModalLabel">
          <i class="fas fa-th-large"></i> Semua Menu Aplikasi FixPoint
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Tutup">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      
      <div class="modal-body p-0">
        <!-- Menu Content -->
        <div id="menuModalContent">
          
          <!-- Search Box -->
          <div class="search-menu-box">
            <input type="text" class="form-control" id="searchMenuModal" placeholder="🔍 Cari menu aplikasi...">
          </div>
          
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
              <i class="fas fa-tachometer-alt icon-dashboard"></i>
              <p class="menu-title">Dashboard</p>
            </a>
            <?php endif; ?>
            
            <?php if (in_array('dashboard2.php', $allowed_files)): ?>
            <a href="dashboard2.php" class="menu-item-card">
              <i class="fas fa-user-tie icon-dashboard"></i>
              <p class="menu-title">Dashboard Direktur</p>
            </a>
            <?php endif; ?>
          </div>

          <!-- PENGAJUAN / ORDER -->
          <div class="menu-category-title">
            <i class="fas fa-list"></i> PENGAJUAN / ORDER
          </div>
          <div class="menu-grid">
            <?php if (in_array('order_tiket_it_software.php', $allowed_files)): ?>
            <a href="order_tiket_it_software.php" class="menu-item-card">
              <i class="fas fa-code icon-purple"></i>
              <p class="menu-title">Tiket IT Software</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('order_tiket_it_hardware.php', $allowed_files)): ?>
            <a href="order_tiket_it_hardware.php" class="menu-item-card">
              <i class="fas fa-desktop icon-red"></i>
              <p class="menu-title">Tiket IT Hardware</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('order_tiket_sarpras.php', $allowed_files)): ?>
            <a href="order_tiket_sarpras.php" class="menu-item-card">
              <i class="fas fa-wrench icon-orange"></i>
              <p class="menu-title">Tiket Sarpras</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('off_duty.php', $allowed_files)): ?>
            <a href="off_duty.php" class="menu-item-card">
              <i class="fas fa-user-slash icon-green"></i>
              <p class="menu-title">Off-Duty</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('lembur.php', $allowed_files)): ?>
            <a href="lembur.php" class="menu-item-card">
              <i class="fas fa-clock icon-teal"></i>
              <p class="menu-title">Lembur</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('izin_keluar.php', $allowed_files)): ?>
            <a href="izin_keluar.php" class="menu-item-card">
              <i class="fas fa-door-open icon-indigo"></i>
              <p class="menu-title">Izin Keluar</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('izin_pulang_cepat.php', $allowed_files)): ?>
            <a href="izin_pulang_cepat.php" class="menu-item-card">
              <i class="fas fa-running icon-pink"></i>
              <p class="menu-title">Izin Pulang Cepat</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('pengajuan_cuti.php', $allowed_files)): ?>
            <a href="pengajuan_cuti.php" class="menu-item-card">
              <i class="fas fa-calendar-times icon-yellow"></i>
              <p class="menu-title">Pengajuan Cuti</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('ganti_jadwal_dinas.php', $allowed_files)): ?>
            <a href="ganti_jadwal_dinas.php" class="menu-item-card">
              <i class="fas fa-exchange-alt icon-cyan"></i>
              <p class="menu-title">Ganti Jadwal</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('edit_data_simrs.php', $allowed_files)): ?>
            <a href="edit_data_simrs.php" class="menu-item-card">
              <i class="fas fa-edit icon-blue"></i>
              <p class="menu-title">Edit Data SIMRS</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('hapus_data.php', $allowed_files)): ?>
            <a href="hapus_data.php" class="menu-item-card">
              <i class="fas fa-trash icon-red"></i>
              <p class="menu-title">Hapus Data SIMRS</p>
            </a>
            <?php endif; ?>
          </div>

          <!-- DATA PENGAJUAN -->
          <div class="menu-category-title">
            <i class="fas fa-folder-open icon-indigo"></i> DATA PENGAJUAN
          </div>
          <div class="menu-grid">
            <?php if (in_array('data_tiket_it_software.php', $allowed_files)): ?>
            <a href="data_tiket_it_software.php" class="menu-item-card">
              <i class="fas fa-code icon-purple"></i>
              <p class="menu-title">Data Tiket IT Soft</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('data_tiket_it_hardware.php', $allowed_files)): ?>
            <a href="data_tiket_it_hardware.php" class="menu-item-card">
              <i class="fas fa-cogs icon-red"></i>
              <p class="menu-title">Data Tiket IT Hard</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('data_tiket_sarpras.php', $allowed_files)): ?>
            <a href="data_tiket_sarpras.php" class="menu-item-card">
              <i class="fas fa-tools icon-orange"></i>
              <p class="menu-title">Data Tiket Sarpras</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('data_off_duty.php', $allowed_files)): ?>
            <a href="data_off_duty.php" class="menu-item-card">
              <i class="fas fa-calendar-times icon-yellow"></i>
              <p class="menu-title">Data Off-Duty</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('acc_edit_data.php', $allowed_files)): ?>
            <a href="acc_edit_data.php" class="menu-item-card">
              <i class="fas fa-check-circle icon-green"></i>
              <p class="menu-title">Permintaan Edit</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('data_permintaan_hapus_data_simrs.php', $allowed_files)): ?>
            <a href="data_permintaan_hapus_data_simrs.php" class="menu-item-card">
              <i class="fas fa-trash-alt icon-red"></i>
              <p class="menu-title">Hapus Data</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('data_cuti_delegasi.php', $allowed_files)): ?>
            <a href="data_cuti_delegasi.php" class="menu-item-card">
              <i class="fas fa-users icon-teal"></i>
              <p class="menu-title">ACC Cuti Delegasi</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('acc_lembur_atasan.php', $allowed_files)): ?>
            <a href="acc_lembur_atasan.php" class="menu-item-card">
              <i class="fas fa-user-check icon-indigo"></i>
              <p class="menu-title">ACC Lembur Atasan</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('acc_lembur_sdm.php', $allowed_files)): ?>
            <a href="acc_lembur_sdm.php" class="menu-item-card">
              <i class="fas fa-user-shield icon-purple"></i>
              <p class="menu-title">ACC Lembur HR-SDM</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('acc_pulang_cepat_sdm.php', $allowed_files)): ?>
            <a href="acc_pulang_cepat_sdm.php" class="menu-item-card">
              <i class="fas fa-user-check icon-indigo"></i>
              <p class="menu-title">ACC Pulang Cepat SDM</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('data_cuti_atasan.php', $allowed_files)): ?>
            <a href="data_cuti_atasan.php" class="menu-item-card">
              <i class="fas fa-user-tie icon-purple"></i>
              <p class="menu-title">ACC Cuti Atasan</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('data_cuti_hrd.php', $allowed_files)): ?>
            <a href="data_cuti_hrd.php" class="menu-item-card">
              <i class="fas fa-id-badge icon-cyan"></i>
              <p class="menu-title">ACC Cuti HRD</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('acc_keluar_atasan.php', $allowed_files)): ?>
            <a href="acc_keluar_atasan.php" class="menu-item-card">
              <i class="fas fa-user-check icon-indigo"></i>
              <p class="menu-title">ACC Keluar Atasan</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('acc_keluar_sdm.php', $allowed_files)): ?>
            <a href="acc_keluar_sdm.php" class="menu-item-card">
              <i class="fas fa-check-double icon-orange"></i>
              <p class="menu-title">ACC Keluar SDM</p>
            </a>
            <?php endif; ?>
          </div>

          <!-- TTE -->
          <?php
          // Cek status lisensi TTE
          $tte_active = false;
          try {
              $perusahaan_result = $conn->query("SELECT id FROM perusahaan LIMIT 1");
              if ($perusahaan_result) {
                  $perusahaan_row = $perusahaan_result->fetch_assoc();
                  if ($perusahaan_row) {
                      $pid = $perusahaan_row['id'];
                      $tte_result = $conn->query("SELECT id FROM tte_licenses WHERE perusahaan_id = $pid AND status = 'active' LIMIT 1");
                      if ($tte_result && $tte_result->num_rows > 0) {
                          $tte_active = true;
                      }
                  }
              }
          } catch (Exception $e) {
              $tte_active = false;
          }
          ?>

          <?php if ($tte_active): ?>
          <div class="menu-category-title">
            <i class="fas fa-signature"></i> TANDA TANGAN ELEKTRONIK (TTE)
          </div>
          <div class="menu-grid">
            <?php if (in_array('cek_tte.php', $allowed_files)): ?>
            <a href="cek_tte.php" class="menu-item-card">
              <i class="fas fa-search icon-purple"></i>
              <p class="menu-title">Cek TTE</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('bubuhkan_tte.php', $allowed_files)): ?>
            <a href="bubuhkan_tte.php" class="menu-item-card">
              <i class="fas fa-file-signature icon-pink"></i>
              <p class="menu-title">TTE Dokumen</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('dokumen_tte.php', $allowed_files)): ?>
            <a href="dokumen_tte.php" class="menu-item-card">
              <i class="fas fa-folder-open icon-indigo"></i>
              <p class="menu-title">Dokumen Saya</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('dokumen_tte_semua.php', $allowed_files)): ?>
            <a href="dokumen_tte_semua.php" class="menu-item-card">
              <i class="fas fa-archive icon-blue"></i>
              <p class="menu-title">Semua Dok. TTE</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('buat_tte.php', $allowed_files)): ?>
            <a href="buat_tte.php" class="menu-item-card">
              <i class="fas fa-qrcode icon-teal"></i>
              <p class="menu-title">TTE Generate</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('bubuhkan_stampel.php', $allowed_files)): ?>
            <a href="bubuhkan_stampel.php" class="menu-item-card">
              <i class="fas fa-stamp icon-red"></i>
              <p class="menu-title">Stempel Dokumen</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('cek_stampel.php', $allowed_files)): ?>
            <a href="cek_stampel.php" class="menu-item-card">
              <i class="fas fa-certificate icon-orange"></i>
              <p class="menu-title">Cek E-Stemp</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('buat_stampel.php', $allowed_files)): ?>
            <a href="buat_stampel.php" class="menu-item-card">
              <i class="fas fa-file-signature icon-pink"></i>
              <p class="menu-title">Generate E-Stemp</p>
            </a>
            <?php endif; ?>
          </div>
          <?php endif; ?>

          <!-- BUAT DOKUMEN / SURAT -->
          <div class="menu-category-title">
            <i class="fas fa-file-alt icon-teal"></i> BUAT DOKUMEN / SURAT
          </div>
          <div class="menu-grid">
            <?php if (in_array('spo.php', $allowed_files)): ?>
            <a href="spo.php" class="menu-item-card">
              <i class="fas fa-file-medical icon-cyan"></i>
              <p class="menu-title">SPO</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('rapat_bulanan.php', $allowed_files)): ?>
            <a href="rapat_bulanan.php" class="menu-item-card">
              <i class="fas fa-calendar-check icon-blue"></i>
              <p class="menu-title">Rapat Bulanan</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('surat_edaran.php', $allowed_files)): ?>
            <a href="surat_edaran.php" class="menu-item-card">
              <i class="fas fa-bullhorn icon-purple"></i>
              <p class="menu-title">Surat Edaran</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('pemberitahuan.php', $allowed_files)): ?>
            <a href="pemberitahuan.php" class="menu-item-card">
              <i class="fas fa-bell icon-pink"></i>
              <p class="menu-title">Pemberitahuan</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('master_no_surat.php', $allowed_files)): ?>
            <a href="master_no_surat.php" class="menu-item-card">
              <i class="fas fa-list-ol icon-yellow"></i>
              <p class="menu-title">Laporan Surat/SPO</p>
            </a>
            <?php endif; ?>
          </div>

          <!-- IT DEPARTEMEN -->
          <div class="menu-category-title">
            <i class="fas fa-laptop-code icon-teal"></i> IT DEPARTEMEN
          </div>
          <div class="menu-grid">
            <?php if (in_array('data_permintaan_hapus_simrs.php', $allowed_files)): ?>
            <a href="data_permintaan_hapus_simrs.php" class="menu-item-card">
              <i class="fas fa-trash-alt icon-red"></i>
              <p class="menu-title">Data Hapus SIMRS</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('data_permintaan_edit_simrs.php', $allowed_files)): ?>
            <a href="data_permintaan_edit_simrs.php" class="menu-item-card">
              <i class="fas fa-edit icon-blue"></i>
              <p class="menu-title">Data Edit SIMRS</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('handling_time.php', $allowed_files)): ?>
            <a href="handling_time.php" class="menu-item-card">
              <i class="fas fa-stopwatch icon-green"></i>
              <p class="menu-title">Handling Time</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('spo_it.php', $allowed_files)): ?>
            <a href="spo_it.php" class="menu-item-card">
              <i class="fas fa-file-alt icon-teal"></i>
              <p class="menu-title">SPO IT</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('input_spo_it.php', $allowed_files)): ?>
            <a href="input_spo_it.php" class="menu-item-card">
              <i class="fas fa-file-signature icon-pink"></i>
              <p class="menu-title">Input SPO IT</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('berita_acara_it.php', $allowed_files)): ?>
            <a href="berita_acara_it.php" class="menu-item-card">
              <i class="fas fa-scroll icon-orange"></i>
              <p class="menu-title">Berita Acara</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('data_barang_it.php', $allowed_files)): ?>
            <a href="data_barang_it.php" class="menu-item-card">
              <i class="fas fa-boxes icon-indigo"></i>
              <p class="menu-title">Data Barang IT</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('maintenance_rutin.php', $allowed_files)): ?>
            <a href="maintenance_rutin.php" class="menu-item-card">
              <i class="fas fa-sync-alt icon-cyan"></i>
              <p class="menu-title">Maintenance Rutin</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('koneksi_bridging.php', $allowed_files)): ?>
            <a href="koneksi_bridging.php" class="menu-item-card">
              <i class="fas fa-link icon-blue"></i>
              <p class="menu-title">Koneksi Bridging</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('log_login.php', $allowed_files)): ?>
            <a href="log_login.php" class="menu-item-card">
              <i class="fas fa-history icon-purple"></i>
              <p class="menu-title">Log Login</p>
            </a>
            <?php endif; ?>
          </div>

          <!-- SARPRAS -->
          <div class="menu-category-title">
            <i class="fas fa-wrench icon-orange"></i> SARPRAS
          </div>
          <div class="menu-grid">
            <?php if (in_array('handling_time_sarpras.php', $allowed_files)): ?>
            <a href="handling_time_sarpras.php" class="menu-item-card">
              <i class="fas fa-stopwatch icon-green"></i>
              <p class="menu-title">Handling Time</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('data_barang_ac.php', $allowed_files)): ?>
            <a href="data_barang_ac.php" class="menu-item-card">
              <i class="fas fa-boxes icon-indigo"></i>
              <p class="menu-title">Barang Sarpras</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('maintanance_rutin_sarpras.php', $allowed_files)): ?>
            <a href="maintanance_rutin_sarpras.php" class="menu-item-card">
              <i class="fas fa-cogs icon-red"></i>
              <p class="menu-title">Maintenance</p>
            </a>
            <?php endif; ?>
          </div>

          <!-- KEUANGAN -->
          <div class="menu-category-title">
            <i class="fas fa-wallet"></i> KEUANGAN
          </div>
          <div class="menu-grid">
            <?php if (in_array('input_gaji.php', $allowed_files)): ?>
            <a href="input_gaji.php" class="menu-item-card">
              <i class="fas fa-money-bill-wave icon-green"></i>
              <p class="menu-title">Transaksi Gaji</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('data_gaji.php', $allowed_files)): ?>
            <a href="data_gaji.php" class="menu-item-card">
              <i class="fas fa-file-invoice-dollar icon-teal"></i>
              <p class="menu-title">Data Gaji</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('masa_kerja.php', $allowed_files)): ?>
            <a href="masa_kerja.php" class="menu-item-card">
              <i class="fas fa-user-clock icon-yellow"></i>
              <p class="menu-title">Masa Kerja</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('kesehatan.php', $allowed_files)): ?>
            <a href="kesehatan.php" class="menu-item-card">
              <i class="fas fa-heartbeat icon-red"></i>
              <p class="menu-title">Kesehatan</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('fungsional.php', $allowed_files)): ?>
            <a href="fungsional.php" class="menu-item-card">
              <i class="fas fa-user-tag icon-orange"></i>
              <p class="menu-title">Fungsional</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('struktural.php', $allowed_files)): ?>
            <a href="struktural.php" class="menu-item-card">
              <i class="fas fa-sitemap icon-indigo"></i>
              <p class="menu-title">Struktural</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('gaji_pokok.php', $allowed_files)): ?>
            <a href="gaji_pokok.php" class="menu-item-card">
              <i class="fas fa-coins icon-yellow"></i>
              <p class="menu-title">Gaji Pokok</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('potongan_bpjs_kes.php', $allowed_files)): ?>
            <a href="potongan_bpjs_kes.php" class="menu-item-card">
              <i class="fas fa-heartbeat icon-red"></i>
              <p class="menu-title">BPJS Kesehatan</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('potongan_bpjs_jht.php', $allowed_files)): ?>
            <a href="potongan_bpjs_jht.php" class="menu-item-card">
              <i class="fas fa-hand-holding-usd icon-green"></i>
              <p class="menu-title">BPJS TK JHT</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('potongan_bpjs_tk_jp.php', $allowed_files)): ?>
            <a href="potongan_bpjs_tk_jp.php" class="menu-item-card">
              <i class="fas fa-briefcase-medical icon-cyan"></i>
              <p class="menu-title">BPJS TK JP</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('potongan_dana_sosial.php', $allowed_files)): ?>
            <a href="potongan_dana_sosial.php" class="menu-item-card">
              <i class="fas fa-donate icon-pink"></i>
              <p class="menu-title">Dana Sosial</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('pph21.php', $allowed_files)): ?>
            <a href="pph21.php" class="menu-item-card">
              <i class="fas fa-receipt icon-blue"></i>
              <p class="menu-title">PPH21</p>
            </a>
            <?php endif; ?>
          </div>

          <!-- HR / SDM -->
          <div class="menu-category-title">
            <i class="fas fa-users-cog"></i> HR / SDM
          </div>
          <div class="menu-grid">
            <?php if (in_array('data_cuti.php', $allowed_files)): ?>
            <a href="data_cuti.php" class="menu-item-card">
              <i class="fas fa-database icon-purple"></i>
              <p class="menu-title">Data Cuti</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('data_izin_keluar.php', $allowed_files)): ?>
            <a href="data_izin_keluar.php" class="menu-item-card">
              <i class="fas fa-clipboard-check icon-green"></i>
              <p class="menu-title">Data Izin Keluar</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('rekap_catatan_kerja.php', $allowed_files)): ?>
            <a href="rekap_catatan_kerja.php" class="menu-item-card">
              <i class="fas fa-clipboard-list icon-teal"></i>
              <p class="menu-title">Rekap Kerja</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('master_cuti.php', $allowed_files)): ?>
            <a href="master_cuti.php" class="menu-item-card">
              <i class="fas fa-calendar-alt icon-cyan"></i>
              <p class="menu-title">Master Cuti</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('jatah_cuti.php', $allowed_files)): ?>
            <a href="jatah_cuti.php" class="menu-item-card">
              <i class="fas fa-calendar-check icon-blue"></i>
              <p class="menu-title">Jatah Cuti</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('data_karyawan.php', $allowed_files)): ?>
            <a href="data_karyawan.php" class="menu-item-card">
              <i class="fas fa-id-badge icon-cyan"></i>
              <p class="menu-title">Data Karyawan</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('exit_clearance.php', $allowed_files)): ?>
            <a href="exit_clearance.php" class="menu-item-card">
              <i class="fas fa-sign-out-alt icon-red"></i>
              <p class="menu-title">Exit Clearance</p>
            </a>
            <?php endif; ?>
          </div>

          <!-- KESEKTARIATAN -->
          <div class="menu-category-title">
            <i class="fas fa-building icon-indigo"></i> KESEKTARIATAN
          </div>
          <div class="menu-grid">
            <?php if (in_array('surat_masuk.php', $allowed_files)): ?>
            <a href="surat_masuk.php" class="menu-item-card">
              <i class="fas fa-envelope-open-text icon-blue"></i>
              <p class="menu-title">Surat Masuk</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('disposisi.php', $allowed_files)): ?>
            <a href="disposisi.php" class="menu-item-card">
              <i class="fas fa-share-square icon-purple"></i>
              <p class="menu-title">Disposisi Surat</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('surat_keluar.php', $allowed_files)): ?>
            <a href="surat_keluar.php" class="menu-item-card">
              <i class="fas fa-paper-plane icon-pink"></i>
              <p class="menu-title">Surat Keluar</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('arsip_digital.php', $allowed_files)): ?>
            <a href="arsip_digital.php" class="menu-item-card">
              <i class="fas fa-archive icon-blue"></i>
              <p class="menu-title">Arsip Digital</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('agenda_direktur.php', $allowed_files)): ?>
            <a href="agenda_direktur.php" class="menu-item-card">
              <i class="fas fa-calendar-alt icon-cyan"></i>
              <p class="menu-title">Agenda Direktur</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('lihat_agenda.php', $allowed_files)): ?>
            <a href="lihat_agenda.php" class="menu-item-card">
              <i class="fas fa-calendar-check icon-blue"></i>
              <p class="menu-title">Lihat Agenda</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('kategori_arsip.php', $allowed_files)): ?>
            <a href="kategori_arsip.php" class="menu-item-card">
              <i class="fas fa-folder-open icon-indigo"></i>
              <p class="menu-title">Kategori Arsip</p>
            </a>
            <?php endif; ?>
          </div>

          <!-- LAPORAN KERJA -->
          <div class="menu-category-title">
            <i class="fas fa-clipboard-list icon-teal"></i> LAPORAN KERJA
          </div>
          <div class="menu-grid">
            <?php if (in_array('catatan_kerja.php', $allowed_files)): ?>
            <a href="catatan_kerja.php" class="menu-item-card">
              <i class="fas fa-pen-square icon-orange"></i>
              <p class="menu-title">Catatan Kerja</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('laporan_harian.php', $allowed_files)): ?>
            <a href="laporan_harian.php" class="menu-item-card">
              <i class="fas fa-calendar-day icon-green"></i>
              <p class="menu-title">Laporan Harian</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('laporan_bulanan.php', $allowed_files)): ?>
            <a href="laporan_bulanan.php" class="menu-item-card">
              <i class="fas fa-calendar-alt icon-cyan"></i>
              <p class="menu-title">Laporan Bulanan</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('laporan_tahunan.php', $allowed_files)): ?>
            <a href="laporan_tahunan.php" class="menu-item-card">
              <i class="fas fa-calendar icon-cyan"></i>
              <p class="menu-title">Laporan Tahunan</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('data_laporan_harian.php', $allowed_files)): ?>
            <a href="data_laporan_harian.php" class="menu-item-card">
              <i class="fas fa-file-alt icon-teal"></i>
              <p class="menu-title">Data Lap Harian</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('data_laporan_bulanan.php', $allowed_files)): ?>
            <a href="data_laporan_bulanan.php" class="menu-item-card">
              <i class="fas fa-file-invoice icon-teal"></i>
              <p class="menu-title">Data Lap Bulanan</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('data_laporan_tahunan.php', $allowed_files)): ?>
            <a href="data_laporan_tahunan.php" class="menu-item-card">
              <i class="fas fa-file-archive icon-indigo"></i>
              <p class="menu-title">Data Lap Tahunan</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('input_kpi.php', $allowed_files)): ?>
            <a href="input_kpi.php" class="menu-item-card">
              <i class="fas fa-chart-line icon-purple"></i>
              <p class="menu-title">Input KPI</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('master_kpi.php', $allowed_files)): ?>
            <a href="master_kpi.php" class="menu-item-card">
              <i class="fas fa-tasks icon-blue"></i>
              <p class="menu-title">Master KPI</p>
            </a>
            <?php endif; ?>
          </div>

          <!-- AKREDITASI -->
          <div class="menu-category-title">
            <i class="fas fa-award icon-yellow"></i> AKREDITASI
          </div>
          <div class="menu-grid">
            <?php if (in_array('data_dokumen.php', $allowed_files)): ?>
            <a href="data_dokumen.php" class="menu-item-card">
              <i class="fas fa-file-alt icon-teal"></i>
              <p class="menu-title">Data Dokumen</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('input_dokumen.php', $allowed_files)): ?>
            <a href="input_dokumen.php" class="menu-item-card">
              <i class="fas fa-plus-circle icon-green"></i>
              <p class="menu-title">Input Dokumen</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('master_pokja.php', $allowed_files)): ?>
            <a href="master_pokja.php" class="menu-item-card">
              <i class="fas fa-users icon-teal"></i>
              <p class="menu-title">Master Pokja</p>
            </a>
            <?php endif; ?>
          </div>

          <!-- KOMITE KEPERAWATAN -->
          <div class="menu-category-title">
            <i class="fas fa-user-nurse icon-pink"></i> KOMITE KEPERAWATAN
          </div>
          <div class="menu-grid">
            <?php if (in_array('praktek.php', $allowed_files)): ?>
            <a href="praktek.php" class="menu-item-card">
              <i class="fas fa-briefcase-medical icon-cyan"></i>
              <p class="menu-title">Kredensial Praktek</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('wawancara.php', $allowed_files)): ?>
            <a href="wawancara.php" class="menu-item-card">
              <i class="fas fa-comments icon-teal"></i>
              <p class="menu-title">Kredensial Wawancara</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('ujian_tertulis.php', $allowed_files)): ?>
            <a href="ujian_tertulis.php" class="menu-item-card">
              <i class="fas fa-edit icon-blue"></i>
              <p class="menu-title">Kredensial Tertulis</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('hasil_praktek.php', $allowed_files)): ?>
            <a href="hasil_praktek.php" class="menu-item-card">
              <i class="fas fa-chart-bar icon-cyan"></i>
              <p class="menu-title">Hasil Praktek</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('hasil_wawancara.php', $allowed_files)): ?>
            <a href="hasil_wawancara.php" class="menu-item-card">
              <i class="fas fa-poll icon-indigo"></i>
              <p class="menu-title">Hasil Wawancara</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('hasil_ujian.php', $allowed_files)): ?>
            <a href="hasil_ujian.php" class="menu-item-card">
              <i class="fas fa-file-alt icon-teal"></i>
              <p class="menu-title">Hasil Tertulis</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('kegiatan_komite.php', $allowed_files)): ?>
            <a href="kegiatan_komite.php" class="menu-item-card">
              <i class="fas fa-calendar-alt icon-cyan"></i>
              <p class="menu-title">Kegiatan Komite</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('laporan_komite.php', $allowed_files)): ?>
            <a href="laporan_komite.php" class="menu-item-card">
              <i class="fas fa-file-alt icon-teal"></i>
              <p class="menu-title">Laporan Komite</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('judul_soal.php', $allowed_files)): ?>
            <a href="judul_soal.php" class="menu-item-card">
              <i class="fas fa-heading icon-purple"></i>
              <p class="menu-title">Judul Soal</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('input_soal.php', $allowed_files)): ?>
            <a href="input_soal.php" class="menu-item-card">
              <i class="fas fa-pen icon-blue"></i>
              <p class="menu-title">Input Soal</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('master_komponen_praktek.php', $allowed_files)): ?>
            <a href="master_komponen_praktek.php" class="menu-item-card">
              <i class="fas fa-list-ul icon-green"></i>
              <p class="menu-title">Komponen Ujian Praktek</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('data_anggota_komite.php', $allowed_files)): ?>
            <a href="data_anggota_komite.php" class="menu-item-card">
              <i class="fas fa-users icon-teal"></i>
              <p class="menu-title">Anggota Komite</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('jenis_kredensial.php', $allowed_files)): ?>
            <a href="jenis_kredensial.php" class="menu-item-card">
              <i class="fas fa-th-list icon-orange"></i>
              <p class="menu-title">Jenis Kredensial</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('jabatan_komite.php', $allowed_files)): ?>
            <a href="jabatan_komite.php" class="menu-item-card">
              <i class="fas fa-user-tie icon-purple"></i>
              <p class="menu-title">Jabatan Komite</p>
            </a>
            <?php endif; ?>
          </div>

          <!-- INDIKATOR MUTU -->
          <div class="menu-category-title">
            <i class="fas fa-chart-line icon-purple"></i> INDIKATOR MUTU
          </div>
          <div class="menu-grid">
            <?php if (in_array('master_indikator.php', $allowed_files)): ?>
            <a href="master_indikator.php" class="menu-item-card">
              <i class="fas fa-globe icon-blue"></i>
              <p class="menu-title">Master IMN</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('master_indikator_rs.php', $allowed_files)): ?>
            <a href="master_indikator_rs.php" class="menu-item-card">
              <i class="fas fa-hospital icon-red"></i>
              <p class="menu-title">Master IMUT RS</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('master_indikator_unit.php', $allowed_files)): ?>
            <a href="master_indikator_unit.php" class="menu-item-card">
              <i class="fas fa-building icon-indigo"></i>
              <p class="menu-title">Master IMUT Unit</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('input_harian.php', $allowed_files)): ?>
            <a href="input_harian.php" class="menu-item-card">
              <i class="fas fa-keyboard icon-teal"></i>
              <p class="menu-title">Input Imut Harian</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('capaian_imut.php', $allowed_files)): ?>
            <a href="capaian_imut.php" class="menu-item-card">
              <i class="fas fa-chart-bar icon-cyan"></i>
              <p class="menu-title">Capaian Imut</p>
            </a>
            <?php endif; ?>
          </div>

          <!-- LAPORAN SIMRS -->
          <div class="menu-category-title">
            <i class="fas fa-hospital icon-red"></i> LAPORAN SIMRS
          </div>
          <div class="menu-grid">
            <?php if (in_array('semua_antrian.php', $allowed_files)): ?>
            <a href="semua_antrian.php" class="menu-item-card">
              <i class="fas fa-chart-line icon-purple"></i>
              <p class="menu-title">% Semua Antrian</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('mjkn_antrian.php', $allowed_files)): ?>
            <a href="mjkn_antrian.php" class="menu-item-card">
              <i class="fas fa-mobile-alt icon-cyan"></i>
              <p class="menu-title">% Antrian JKN</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('poli_antrian.php', $allowed_files)): ?>
            <a href="poli_antrian.php" class="menu-item-card">
              <i class="fas fa-user-md icon-purple"></i>
              <p class="menu-title">% Antrian Poli</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('erm.php', $allowed_files)): ?>
            <a href="erm.php" class="menu-item-card">
              <i class="fas fa-file-medical icon-cyan"></i>
              <p class="menu-title">E-RM</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('satu_sehat.php', $allowed_files)): ?>
            <a href="satu_sehat.php" class="menu-item-card">
              <i class="fas fa-heartbeat icon-red"></i>
              <p class="menu-title">Satu Sehat</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('progres_kerja.php', $allowed_files)): ?>
            <a href="progres_kerja.php" class="menu-item-card">
              <i class="fas fa-tasks icon-blue"></i>
              <p class="menu-title">Progres Kerja</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('slide_pelaporan.php', $allowed_files)): ?>
            <a href="slide_pelaporan.php" class="menu-item-card">
              <i class="fas fa-file-powerpoint icon-orange"></i>
              <p class="menu-title">Slide Laporan</p>
            </a>
            <?php endif; ?>
          </div>

          <!-- JADWAL -->
          <div class="menu-category-title">
            <i class="fas fa-calendar icon-cyan"></i> JADWAL
          </div>
          <div class="menu-grid">
            <?php if (in_array('absensi.php', $allowed_files)): ?>
            <a href="absensi.php" class="menu-item-card">
              <i class="fas fa-user-check icon-indigo"></i>
              <p class="menu-title">Absensi</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('data_absensi.php', $allowed_files)): ?>
            <a href="data_absensi.php" class="menu-item-card">
              <i class="fas fa-clipboard-list icon-teal"></i>
              <p class="menu-title">Data Absensi</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('jadwal_dinas.php', $allowed_files)): ?>
            <a href="jadwal_dinas.php" class="menu-item-card">
              <i class="fas fa-calendar-plus icon-blue"></i>
              <p class="menu-title">Input Jadwal</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('data_jadwal.php', $allowed_files)): ?>
            <a href="data_jadwal.php" class="menu-item-card">
              <i class="fas fa-calendar-alt icon-cyan"></i>
              <p class="menu-title">Data Jadwal</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('jam_kerja.php', $allowed_files)): ?>
            <a href="jam_kerja.php" class="menu-item-card">
              <i class="fas fa-clock icon-teal"></i>
              <p class="menu-title">Jam Kerja</p>
            </a>
            <?php endif; ?>
          </div>

          <!-- MASTER DATA -->
          <div class="menu-category-title">
            <i class="fas fa-database icon-purple"></i> MASTER DATA
          </div>
          <div class="menu-grid">
            <?php if (in_array('perusahaan.php', $allowed_files)): ?>
            <a href="perusahaan.php" class="menu-item-card">
              <i class="fas fa-building icon-indigo"></i>
              <p class="menu-title">Perusahaan</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('pengguna.php', $allowed_files)): ?>
            <a href="pengguna.php" class="menu-item-card">
              <i class="fas fa-user-cog icon-purple"></i>
              <p class="menu-title">Pengguna</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('hak_akses.php', $allowed_files)): ?>
            <a href="hak_akses.php" class="menu-item-card">
              <i class="fas fa-user-shield icon-purple"></i>
              <p class="menu-title">Hak Akses</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('master_url.php', $allowed_files)): ?>
            <a href="master_url.php" class="menu-item-card">
              <i class="fas fa-link icon-blue"></i>
              <p class="menu-title">Master URL</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('mail_setting.php', $allowed_files)): ?>
            <a href="mail_setting.php" class="menu-item-card">
              <i class="fas fa-envelope"></i>
              <p class="menu-title">Mail Settings</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('tele_setting.php', $allowed_files)): ?>
            <a href="tele_setting.php" class="menu-item-card">
              <i class="fab fa-telegram"></i>
              <p class="menu-title">Telegram Settings</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('wa_setting.php', $allowed_files)): ?>
            <a href="wa_setting.php" class="menu-item-card">
              <i class="fab fa-whatsapp"></i>
              <p class="menu-title">WhatsApp Settings</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('kategori_hardware.php', $allowed_files)): ?>
            <a href="kategori_hardware.php" class="menu-item-card">
              <i class="fas fa-microchip icon-red"></i>
              <p class="menu-title">Kategori Hardware</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('kategori_software.php', $allowed_files)): ?>
            <a href="kategori_software.php" class="menu-item-card">
              <i class="fas fa-laptop-code icon-teal"></i>
              <p class="menu-title">Kategori Software</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('unit_kerja.php', $allowed_files)): ?>
            <a href="unit_kerja.php" class="menu-item-card">
              <i class="fas fa-sitemap icon-indigo"></i>
              <p class="menu-title">Unit Kerja</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('jabatan.php', $allowed_files)): ?>
            <a href="jabatan.php" class="menu-item-card">
              <i class="fas fa-briefcase icon-cyan"></i>
              <p class="menu-title">Jabatan</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('master_poliklinik.php', $allowed_files)): ?>
            <a href="master_poliklinik.php" class="menu-item-card">
              <i class="fas fa-clinic-medical icon-pink"></i>
              <p class="menu-title">Poliklinik</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('master_status_karyawan.php', $allowed_files)): ?>
            <a href="master_status_karyawan.php" class="menu-item-card">
              <i class="fas fa-user-tag icon-orange"></i>
              <p class="menu-title">Status Karyawan</p>
            </a>
            <?php endif; ?>
          </div>

          <!-- SETTING -->
          <div class="menu-category-title">
            <i class="fas fa-cog"></i> SETTING
          </div>
          <div class="menu-grid">
            <?php if (in_array('profile.php', $allowed_files)): ?>
            <a href="profile.php" class="menu-item-card">
              <i class="fas fa-user-circle icon-indigo"></i>
              <p class="menu-title">Akun Saya</p>
            </a>
            <?php endif; ?>

            <?php if (in_array('profile2.php', $allowed_files)): ?>
            <a href="profile2.php" class="menu-item-card">
              <i class="fas fa-key icon-orange"></i>
              <p class="menu-title">Profil Saya</p>
            </a>
            <?php endif; ?>
          </div>

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
        <h5 class="modal-title"><i class="fas fa-comments icon-teal"></i> Chat Pesan</h5>
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
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane icon-pink"></i></button>
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
              <i class="fas fa-laptop-code icon-teal"></i> IT Software
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
              <i class="fas fa-desktop icon-red"></i> IT Hardware
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
        <h5 class="modal-title" id="catatanModalLabel"><i class="fas fa-pen-square icon-orange"></i> Log Book</h5>
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
          html = '<div class="text-muted p-3"><em>Tidak ada pengguna yang online</em></div>';
        } else {
          users.forEach(function (user) {
            html += `
              <div class="d-flex align-items-center justify-content-between py-2 px-3 rounded mb-1" style="cursor:pointer; background:#f8f9fa;" onclick="selectUser(${user.id}, '${user.nama}')">
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
      $('#chat-header').text('Chat dengan: ' + userName);
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